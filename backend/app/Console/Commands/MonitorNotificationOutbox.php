<?php

namespace App\Console\Commands;

use App\Support\NotificationOutboxAlerts;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class MonitorNotificationOutbox extends Command
{
    protected $signature = 'outbox:monitor';

    protected $description = 'Queue throttled operational alerts for pending and failed outbox events';

    public function handle(NotificationOutboxAlerts $alerts): int
    {
        $connection = DB::connection();

        if ($connection->getDriverName() !== 'pgsql') {
            $this->error('PostgreSQL is required.');

            return self::FAILURE;
        }

        $queued = 0;

        try {
            $connection->table('notification_outbox')
                ->whereNull('processed_at')->whereNull('resolved_at')
                ->whereNotNull('failed_at')
                ->where(fn ($query) => $query->whereNull('replay_requested_at')
                    ->orWhereColumn('replay_requested_at', '<=', 'failed_at'))
                ->chunkById(100, function ($rows) use ($connection, $alerts, &$queued): void {
                    foreach ($rows as $candidate) {
                        $queued += (int) $connection->transaction(function () use ($connection, $alerts, $candidate): bool {
                            $row = $connection->table('notification_outbox')
                                ->where('id', $candidate->id)
                                ->lock('FOR UPDATE SKIP LOCKED')->first();

                            return $row !== null && $alerts->failed($connection, $row);
                        });
                    }
                });

            $queued += (int) $connection->transaction(fn () => $alerts->pending($connection));
        } catch (Throwable) {
            $this->error('Outbox monitoring failed: monitor_unavailable.');

            return self::FAILURE;
        }

        $this->info("Queued {$queued} operational alert(s).");

        return self::SUCCESS;
    }
}
