<?php

namespace App\Support;

use App\Jobs\SendSlackOperationalAlert;
use Carbon\CarbonImmutable;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

final class NotificationOutboxAlerts
{
    public function failed(Connection $connection, object $outbox): bool
    {
        if (
            $outbox->processed_at !== null
            || $outbox->resolved_at !== null
            || $outbox->failed_at === null
        ) {
            return false;
        }

        if (
            $outbox->replay_requested_at !== null
            && CarbonImmutable::parse($outbox->replay_requested_at)
                ->greaterThan(CarbonImmutable::parse($outbox->failed_at))
        ) {
            return false;
        }

        $failedCount = $connection->table('notification_outbox')
            ->whereNull('processed_at')
            ->whereNull('resolved_at')
            ->whereNotNull('failed_at')
            ->where(function ($query): void {
                $query->whereNull('replay_requested_at')
                    ->orWhereColumn(
                        'replay_requested_at',
                        '<=',
                        'failed_at',
                    );
            })
            ->count();

        $allowedErrorCodes = [
            'notification_key_conflict',
            'notification_write_failed',
            'retry_attempts_exhausted',
            'retry_window_expired',
        ];

        $errorCode = in_array(
            $outbox->last_error_code,
            $allowedErrorCodes,
            true,
        )
            ? $outbox->last_error_code
            : 'notification_write_failed';

        $job = new SendSlackOperationalAlert(
            (string) Str::uuid(),
            'outbox_failed',
            [
                'failed_count' => $failedCount,
                'outbox_id' => $outbox->id,
                'event_type' => $outbox->type,
                'error_code' => $errorCode,
                'failed_at' => CarbonImmutable::parse(
                    $outbox->failed_at,
                )->timestamp,
            ],
        );

        return $this->enqueue(
            $connection,
            'failed:' . $outbox->id . ':' . $outbox->failed_at,
            86400,
            $job,
        );
    }

    public function pending(Connection $connection): bool
    {
        $summary = $connection->table('notification_outbox')
            ->whereNull('processed_at')
            ->whereNull('resolved_at')
            ->where(function ($query): void {
                $query->whereNull('failed_at')
                    ->orWhereColumn(
                        'replay_requested_at',
                        '>',
                        'failed_at',
                    );
            })
            ->whereRaw(
                "created_at < clock_timestamp() - INTERVAL '5 minutes'"
            )
            ->selectRaw('COUNT(*) AS pending_count')
            ->selectRaw(
                'CEIL(EXTRACT(EPOCH FROM
                (clock_timestamp() - MIN(created_at))
            )) AS oldest_age'
            )
            ->first();

        if ((int) $summary->pending_count === 0) {
            return false;
        }

        $job = new SendSlackOperationalAlert(
            (string) Str::uuid(),
            'outbox_pending',
            [
                'pending_count' => (int) $summary->pending_count,
                'oldest_pending_seconds' => (int) $summary->oldest_age,
                'checked_at' => now()->timestamp,
            ],
        );

        return $this->enqueue(
            $connection,
            'pending',
            600,
            $job,
        );
    }

    private function enqueue(
        Connection $connection,
        string $key,
        int $seconds,
        SendSlackOperationalAlert $job,
    ): bool {
        $cache = Cache::store('database');

        $queueConnection = DB::connection(
            config('queue.connections.database.connection'),
        );

        if (
            $connection->transactionLevel() === 0
            || $connection !== $queueConnection
            || $connection !== $cache->getStore()->getConnection()
        ) {
            throw new LogicException(
                'Outbox, queue and alert markers must share one database connection and transaction.'
            );
        }

        $marker = 'notification-alert:' . hash('sha256', $key);

        if (!$cache->add($marker, true, $seconds)) {
            return false;
        }

        Bus::dispatch($job->beforeCommit());

        return true;
    }
}