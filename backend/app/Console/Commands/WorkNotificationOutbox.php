<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class WorkNotificationOutbox extends Command
{
    protected $signature = 'outbox:work {--sleep=2} {--limit=100} {--once}';

    protected $description = 'Continuously poll the notification outbox';

    public function handle(): int
    {
        $sleep = filter_var($this->option('sleep'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 30],
        ]);
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 1000],
        ]);

        if ($sleep === false || $limit === false) {
            $this->error('Invalid worker options.');

            return self::INVALID;
        }

        $stop = false;
        $this->trap(fn () => [SIGTERM, SIGINT], function () use (&$stop): void {
            $stop = true;
        });

        do {
            $result = $this->call('outbox:process', ['--limit' => $limit]);

            if ($result !== self::SUCCESS) {
                return $result;
            }

            if ($this->option('once') || $stop) {
                break;
            }

            sleep($sleep);
        } while (! $stop);

        return self::SUCCESS;
    }
}
