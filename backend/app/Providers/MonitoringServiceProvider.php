<?php

namespace App\Providers;

use App\Jobs\SendSlackOperationalAlert;
use App\Models\User;
use App\Support\AdminPermissions;
use App\Support\MonitoringAlerts;
use App\Support\MonitoringPrivacy;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;
use Laravel\Pulse\Facades\Pulse;
use Laravel\Telescope\Telescope;

class MonitoringServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MonitoringAlerts::class);

        Telescope::filter(MonitoringPrivacy::telescope(...));
        Pulse::filter(MonitoringPrivacy::pulse(...));
    }

    public function boot(): void
    {
        Event::listen(
            MessageLogged::class,
            fn (MessageLogged $event) => app(MonitoringAlerts::class)
                ->critical($event),
        );

        Event::listen(
            QueryExecuted::class,
            fn (QueryExecuted $event) => app(MonitoringAlerts::class)
                ->slowQuery($event),
        );

        Queue::before(function (JobProcessing $event): void {
            $isSlack = $event->job->getQueue() === 'slack'
                || $event->job->resolveName() === SendSlackOperationalAlert::class;

            app(MonitoringAlerts::class)->setSlackJob($isSlack);
        });

        Queue::looping(function (): void {
            app(MonitoringAlerts::class)->setSlackJob(false);
        });

        $this->app->booted(function (): void {
            $canView = static fn (?User $user): bool => $user !== null
                && ! $user->trashed()
                && $user->status === 'active'
                && $user->hasPermission(AdminPermissions::VIEW_MONITORING);

            Gate::define('viewMonitoring', $canView);
            Gate::define('viewTelescope', $canView);
            Gate::define('viewPulse', $canView);

            Telescope::auth(
                static fn (Request $request): bool => Gate::allows('viewTelescope'),
            );
        });
    }
}
