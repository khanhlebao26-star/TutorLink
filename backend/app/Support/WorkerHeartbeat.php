<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

final class WorkerHeartbeat
{
    public const PROCESSES = ['outbox-worker', 'slack-worker', 'broadcast-worker', 'scheduler'];

    /** @var array<string, int> */
    private array $writtenAt = [];

    public function beat(string $process): bool
    {
        if (! in_array($process, self::PROCESSES, true)) {
            return false;
        }
        $now = now()->timestamp;
        if ($now - ($this->writtenAt[$process] ?? 0) < 30) {
            return true;
        }
        try {
            $stored = Cache::store('database')->put($this->key($process), $now, 86400);
            if ($stored) {
                $this->writtenAt[$process] = $now;
            }

            return $stored;
        } catch (Throwable) {
            try {
                Log::channel('single')->warning('Worker heartbeat unavailable.', ['monitoring_internal' => true]);
            } catch (Throwable) {
            }

            return false;
        }
    }

    public function snapshot(): array
    {
        $result = [];
        foreach (self::PROCESSES as $process) {
            $at = Cache::store('database')->get($this->key($process));
            $age = is_int($at) ? now()->timestamp - $at : null;
            $result[$process] = [
                'last_seen_at' => is_int($at) ? gmdate('Y-m-d\TH:i:s\Z', $at) : null,
                'age_seconds' => $age,
                'status' => match (true) {
                    $age === null => 'missing',
                    $age < 0 => 'clock_skew',
                    $age > 90 => 'stale',
                    default => 'ok',
                },
            ];
        }

        return $result;
    }

    private function key(string $process): string
    {
        return 'monitoring:heartbeat:'.hash('sha256', config('app.env').'|'.$process);
    }
}
