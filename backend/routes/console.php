<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::useCache('database');

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
