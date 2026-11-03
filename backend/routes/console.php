<?php

use App\Support\WorkerHeartbeat;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Laravel\Pulse\Contracts\Storage;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::useCache('database');

Schedule::call(fn () => app(WorkerHeartbeat::class)->beat('scheduler'))
    ->name('monitoring:heartbeat:scheduler')
    ->everyThirtySeconds();

Schedule::command('invitations:expire')
    ->everyMinute()
    ->withoutOverlapping(5);

Schedule::command('outbox:monitor')
    ->everyMinute()
    ->withoutOverlapping(5);

Schedule::command('outbox:purge', ['--execute' => true])
    ->dailyAt('02:00')
    ->timezone('UTC')
    ->withoutOverlapping(120);

Artisan::command('monitoring:prune', function (): int {
    if (config('telescope.enabled')) {
        $exitCode = $this->call('telescope:prune', [
            '--hours' => 24,
        ]);

        if ($exitCode !== 0) {
            return $exitCode;
        }
    }

    if (config('pulse.enabled')) {
        app(Storage::class)->trim();
    }

    return 0;
})->purpose('Prune monitoring data according to retention settings');

Schedule::command('monitoring:prune')
    ->hourly()
    ->withoutOverlapping(10);
