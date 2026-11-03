<?php

namespace App\Console\Commands;

use App\Support\WorkerHeartbeat;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class MonitoringRuntime extends Command
{
    protected $signature = 'monitoring:runtime';

    protected $description = 'Read worker heartbeats, outbox lag and queue summaries without private payloads';

    public function handle(WorkerHeartbeat $heartbeats): int
    {
        try {
            $workers = $heartbeats->snapshot();
            $pending = DB::table('notification_outbox')
                ->whereNull('processed_at')->whereNull('resolved_at')
                ->where(fn ($query) => $query->whereNull('failed_at')
                    ->orWhereColumn('replay_requested_at', '>', 'failed_at'))
                ->selectRaw('COUNT(*) AS total, MIN(created_at) AS oldest')->first();
            $lag = $pending->oldest === null ? 0 : max(0, now()->timestamp - CarbonImmutable::parse($pending->oldest)->timestamp);
            $failedOutbox = DB::table('notification_outbox')
                ->whereNull('processed_at')->whereNull('resolved_at')->whereNotNull('failed_at')
                ->where(fn ($query) => $query->whereNull('replay_requested_at')
                    ->orWhereColumn('replay_requested_at', '<=', 'failed_at'))->count();
            $queueConnection = DB::connection(config('queue.connections.database.connection'));
            $failed = $queueConnection->table('failed_jobs')->count();
            $slackFailed = $queueConnection->table('failed_jobs')->where('queue', 'slack')->count();
            $queues = $queueConnection->table('jobs')->whereIn('queue', ['default', 'slack', 'broadcasts'])
                ->select('queue')->selectRaw('COUNT(*) AS total')->groupBy('queue')
                ->pluck('total', 'queue')->map(fn ($count): int => (int) $count)->all();
            $healthy = collect($workers)->every(fn (array $worker): bool => $worker['status'] === 'ok')
                && $lag <= 300 && $failed === 0 && $failedOutbox === 0;
            $this->line(json_encode([
                'checked_at' => now()->toISOString(),
                'status' => $healthy ? 'ok' : 'degraded',
                'workers' => $workers,
                'outbox' => ['pending' => (int) $pending->total, 'oldest_pending_seconds' => $lag, 'failed_unresolved' => $failedOutbox],
                'queue' => ['waiting_or_reserved' => $queues, 'failed_total' => $failed, 'failed_slack' => $slackFailed],
            ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

            return $healthy ? self::SUCCESS : self::FAILURE;
        } catch (Throwable) {
            $this->line(json_encode(['status' => 'unknown', 'error_code' => 'runtime_metrics_unavailable']));

            return self::INVALID;
        }
    }
}
