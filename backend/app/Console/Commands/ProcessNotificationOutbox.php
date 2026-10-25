<?php

namespace App\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;
use Throwable;

class ProcessNotificationOutbox extends Command
{
    protected $signature = 'outbox:process {--limit=100}';

    protected $description = 'Process notification outbox with bounded retries';

    public function handle(): int
    {
        $limit = filter_var(
            $this->option('limit'),
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1, 'max_range' => 1000]],
        );

        if ($limit === false) {
            $this->error('The limit must be an integer between 1 and 1000.');

            return self::INVALID;
        }

        $connection = DB::connection();

        if ($connection->getDriverName() !== 'pgsql') {
            $this->error('This command requires PostgreSQL.');

            return self::FAILURE;
        }

        $handled = 0;

        try {
            while ($handled < $limit && $this->processNext($connection)) {
                $handled++;
            }
        } catch (Throwable) {
            // Do not print exceptions containing SQL, bindings or payload.
            $this->error('Outbox worker stopped: infrastructure_or_runtime_error.');

            return self::FAILURE;
        }

        $this->info("Handled {$handled} outbox event(s).");

        return self::SUCCESS;
    }

    private function processNext(Connection $connection): bool
    {
        return $connection->transaction(function () use ($connection): bool {
            $now = $this->databaseNow($connection);
            $expiredBefore = $this->stamp($now->subMinutes(60));

            $outbox = $connection->table('notification_outbox')
                ->whereNull('processed_at')
                ->whereNull('resolved_at')
                ->where(function ($query): void {
                    $query->whereNull('failed_at')
                        ->orWhereColumn('replay_requested_at', '>', 'failed_at');
                })
                ->where(function ($query) use ($now, $expiredBefore): void {
                    $query->where('available_at', '<=', $this->stamp($now))
                        ->orWhere('attempt_count', '>=', 10)
                        ->orWhere(function ($query) use ($expiredBefore): void {
                            $query->where('attempt_count', '>', 0)
                                ->where('retry_started_at', '<=', $expiredBefore);
                        });
                })
                ->orderBy('available_at')
                ->orderBy('id')
                ->lock('FOR UPDATE SKIP LOCKED')
                ->first();

            if ($outbox === null) {
                return false;
            }

            $now = $this->databaseNow($connection);

            $startedAt = (int) $outbox->attempt_count === 0
                ? $now
                : CarbonImmutable::parse($outbox->retry_started_at);

            $deadline = $startedAt->addMinutes(60);

            if ((int) $outbox->attempt_count >= 10) {
                $this->markFailed(
                    $connection,
                    $outbox,
                    'retry_attempts_exhausted',
                    $now,
                );

                return true;
            }

            if ($now->greaterThanOrEqualTo($deadline)) {
                $this->markFailed(
                    $connection,
                    $outbox,
                    'retry_window_expired',
                    $now,
                );

                return true;
            }

            $attempt = (int) $outbox->attempt_count + 1;

            $connection->table('notification_outbox')
                ->where('id', $outbox->id)
                ->update([
                    'attempt_count' => $attempt,
                    'retry_started_at' => $this->stamp($startedAt),
                ]);

            try {
                // The nested transaction creates a PostgreSQL savepoint.
                // The outer transaction continues holding the outbox row lock.
                $connection->transaction(function () use ($connection, $outbox): void {
                    $this->createNotification($connection, $outbox);
                });
            } catch (QueryException | LogicException $exception) {
                if (
                    $exception instanceof QueryException
                    && str_starts_with(
                        (string) ($exception->errorInfo[0] ?? ''),
                        '08',
                    )
                ) {
                    // A broken connection cannot persist retry metadata.
                    throw $exception;
                }

                $errorCode = $exception instanceof LogicException
                    ? 'notification_key_conflict'
                    : 'notification_write_failed';

                $now = $this->databaseNow($connection);

                if ($attempt >= 10 || $now->greaterThanOrEqualTo($deadline)) {
                    $this->markFailed(
                        $connection,
                        $outbox,
                        $errorCode,
                        $now,
                    );

                    return true;
                }

                // Equal jitter: increasing range, capped at five minutes.
                $maximumDelay = min(300, 10 * (2 ** min($attempt - 1, 5)));

                $delay = random_int(
                    (int) ceil($maximumDelay / 2),
                    $maximumDelay,
                );

                $availableAt = $now->addSeconds($delay);

                if ($availableAt->greaterThan($deadline)) {
                    $availableAt = $deadline;
                }

                $connection->table('notification_outbox')
                    ->where('id', $outbox->id)
                    ->update([
                        'last_error_code' => $errorCode,
                        'available_at' => $this->stamp($availableAt),
                    ]);
            }

            return true;
        }, 3);
    }

    private function createNotification(Connection $connection, object $outbox): void
    {
        $inserted = $connection->table('notifications')->insertOrIgnore([
            'id' => (string) Str::uuid(),
            'user_id' => $outbox->user_id,
            'type' => $outbox->type,
            'event_key' => $outbox->event_key,
            'payload' => $outbox->payload,
        ]);

        if ($inserted === 0) {
            $matches = $connection->table('notifications')
                ->where('user_id', $outbox->user_id)
                ->where('type', $outbox->type)
                ->where('event_key', $outbox->event_key)
                ->whereRaw('payload = CAST(? AS jsonb)', [$outbox->payload])
                ->exists();

            if (!$matches) {
                throw new LogicException(
                    'Notification key conflicts with existing data.'
                );
            }
        }

        $timestamp = $this->stamp($this->databaseNow($connection));

        $connection->table('notification_outbox')
            ->where('id', $outbox->id)
            ->update([
                'processed_at' => $timestamp,
                'last_error_code' => null,
                'resolved_at' => $outbox->failed_at !== null
                    ? $timestamp
                    : null,
            ]);
    }

    private function markFailed(
        Connection $connection,
        object $outbox,
        string $errorCode,
        CarbonImmutable $now,
    ): void {
        if ($outbox->replay_requested_at !== null) {
            $minimum = CarbonImmutable::parse($outbox->replay_requested_at)
                ->addMicrosecond();

            if ($now->lessThan($minimum)) {
                $now = $minimum;
            }
        }

        $connection->table('notification_outbox')
            ->where('id', $outbox->id)
            ->update([
                'failed_at' => $this->stamp($now),
                'last_error_code' => $errorCode,
            ]);
    }

    private function databaseNow(Connection $connection): CarbonImmutable
    {
        $result = $connection->selectOne(
            'SELECT clock_timestamp() AS current_time'
        );

        return CarbonImmutable::parse($result->current_time);
    }

    private function stamp(CarbonImmutable $time): string
    {
        return $time->format('Y-m-d H:i:s.uP');
    }
}