<?php

namespace App\Support;

use App\Jobs\SendSlackOperationalAlert;
use Closure;
use Illuminate\Cache\DatabaseStore;
use Illuminate\Cache\Repository;
use Illuminate\Database\Connection;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use LogicException;
use Throwable;

final class MonitoringAlerts
{
    private bool $handling = false;

    private bool $slackJob = false;

    public function setSlackJob(bool $active): void
    {
        $this->slackJob = $active;
    }

    public function critical(MessageLogged $event): void
    {
        if ($event->context['monitoring_internal'] ?? false) {
            return;
        }

        $exception = $event->context['exception'] ?? null;

        $criticalLevel = in_array(
            $event->level,
            ['critical', 'alert', 'emergency'],
            true,
        );

        $reportedException = $event->level === 'error'
            && $exception instanceof Throwable;

        if (! $criticalLevel && ! $reportedException) {
            return;
        }

        $source = $exception instanceof Throwable
            ? $exception::class.'|'.$exception->getFile().'|'.$exception->getLine()
            : 'critical-log';

        $fingerprint = hash('sha256', $source);

        $this->safely(function () use ($fingerprint): void {
            [$connection, $cache] = $this->storage();

            $this->enqueue(
                $connection,
                $cache,
                'critical',
                $fingerprint,
                1,
                0,
            );
        });
    }

    public function slowQuery(QueryExecuted $event): void
    {
        if ($event->time < (int) config('monitoring.slow_query_ms', 500)) {
            return;
        }

        if (
            preg_match(
                '/\b(jobs|failed_jobs|cache|cache_locks|telescope_entries|telescope_entries_tags|telescope_monitoring|pulse_entries|pulse_aggregates|pulse_values)\b/i',
                $event->sql,
            ) === 1
        ) {
            return;
        }

        // Match the fingerprint format used by Telescope privacy filtering.
        $fingerprint = hash('sha256', md5($event->sql));
        $duration = (int) ceil($event->time);

        $this->safely(function () use ($fingerprint, $duration): void {
            [$connection, $cache] = $this->storage();

            $key = 'monitoring:slow:'.hash(
                'sha256',
                config('app.env').'|'.$fingerprint,
            );

            $cache->lock($key.':lock', 10)->block(
                1,
                function () use ($connection, $cache, $key, $fingerprint, $duration): void {
                    $now = now()->timestamp;

                    $window = max(
                        1,
                        (int) config(
                            'monitoring.alerts.slow_query_window_seconds',
                            300,
                        ),
                    );

                    $minimum = max(
                        1,
                        (int) config('monitoring.alerts.slow_query_count', 5),
                    );

                    $samples = array_values(array_filter(
                        $cache->get($key, []),
                        fn (array $sample): bool => $sample['at'] > $now - $window,
                    ));

                    $samples[] = ['at' => $now, 'ms' => $duration];

                    // Only the latest threshold-sized sample is needed.
                    $samples = array_slice($samples, -$minimum);

                    $cache->put($key, $samples, $window);

                    if (count($samples) < $minimum) {
                        return;
                    }

                    $this->enqueue(
                        $connection,
                        $cache,
                        'slow_query',
                        $fingerprint,
                        count($samples),
                        max(array_column($samples, 'ms')),
                    );
                },
            );
        });
    }

    private function safely(Closure $callback): void
    {
        if (
            ! config('monitoring.alerts.enabled')
            || config('monitoring.alerts.suppressed')
            || $this->slackJob
            || $this->handling
        ) {
            return;
        }

        $this->handling = true;

        try {
            $callback();
        } catch (Throwable) {
            try {
                Log::channel('single')->warning(
                    'Monitoring alert enqueue failed.',
                    ['monitoring_internal' => true],
                );
            } catch (Throwable) {
                // Monitoring failure must not break the application request.
            }
        } finally {
            $this->handling = false;
        }
    }

    /** @return array{Connection, Repository} */
    private function storage(): array
    {
        $connection = DB::connection(
            config('queue.connections.database.connection'),
        );

        $cache = Cache::store('database');
        $store = $cache->getStore();

        if (
            ! $store instanceof DatabaseStore
            || $connection !== $store->getConnection()
        ) {
            throw new LogicException(
                'Monitoring queue and cache must share a database connection.',
            );
        }

        return [$connection, $cache];
    }

    private function enqueue(
        Connection $connection,
        Repository $cache,
        string $kind,
        string $fingerprint,
        int $count,
        int $maxMs,
    ): void {
        $marker = 'monitoring:cooldown:'.hash(
            'sha256',
            config('app.env').'|'.$kind.'|'.$fingerprint,
        );

        $request = app()->bound('request') ? request() : null;
        $requestId = $request?->attributes->get('request_id');

        $requestId = is_string($requestId) && Str::isUuid($requestId)
            ? $requestId
            : null;

        $job = new SendSlackOperationalAlert(
            (string) Str::uuid(),
            $kind,
            [
                'fingerprint' => $fingerprint,
                'count' => $count,
                'max_ms' => $maxMs,
                'observed_at' => now()->timestamp,
                'request_id' => $requestId,
            ],
        );

        $connection->transaction(function () use ($cache, $marker, $job): void {
            $seconds = max(
                1,
                (int) config('monitoring.alerts.cooldown_seconds', 600),
            );

            if (! $cache->add($marker, true, $seconds)) {
                return;
            }

            Bus::dispatch($job->beforeCommit());
        });
    }
}
