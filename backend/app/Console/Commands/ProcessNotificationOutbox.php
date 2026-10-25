<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

class ProcessNotificationOutbox extends Command
{
    protected $signature = 'outbox:process {--limit=100}';

    protected $description = 'Create in-app notifications from committed outbox events';

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

        $processed = 0;

        while ($processed < $limit && $this->processNext($connection)) {
            $processed++;
        }

        $this->info("Processed {$processed} outbox event(s).");

        return self::SUCCESS;
    }

    private function processNext(Connection $connection): bool
    {
        return $connection->transaction(function () use ($connection): bool {
            $outbox = $connection->table('notification_outbox')
                ->whereNull('processed_at')
                ->whereNull('resolved_at')
                ->whereNull('failed_at')
                ->whereRaw('available_at <= CURRENT_TIMESTAMP')
                ->orderBy('available_at')
                ->orderBy('id')
                ->lock('FOR UPDATE SKIP LOCKED')
                ->first();

            if ($outbox === null) {
                return false;
            }

            $inserted = $connection->table('notifications')->insertOrIgnore([
                'id' => (string) Str::uuid(),
                'user_id' => $outbox->user_id,
                'type' => $outbox->type,
                'event_key' => $outbox->event_key,
                'payload' => $outbox->payload,
            ]);

            if ($inserted === 0) {
                $matchesExistingNotification = $connection->table('notifications')
                    ->where('user_id', $outbox->user_id)
                    ->where('type', $outbox->type)
                    ->where('event_key', $outbox->event_key)
                    ->whereRaw(
                        'payload = CAST(? AS jsonb)',
                        [$outbox->payload],
                    )
                    ->exists();

                if (!$matchesExistingNotification) {
                    throw new LogicException(
                        'Notification key conflicts with existing data.'
                    );
                }
            }

            $connection->table('notification_outbox')
                ->where('id', $outbox->id)
                ->update([
                    'processed_at' => $connection->raw('CURRENT_TIMESTAMP'),
                ]);

            return true;
        });
    }
}