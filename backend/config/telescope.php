<?php

use App\Http\Middleware\EnsureMonitoringActor;
use Laravel\Telescope\Http\Middleware\Authorize;
use Laravel\Telescope\Watchers;

return [
    'enabled' => env('TELESCOPE_ENABLED', false),

    'path' => 'telescope',

    'storage' => [
        'database' => [
            'connection' => env('DB_CONNECTION', 'pgsql'),
            'chunk' => 1000,
        ],
    ],

    'middleware' => [
        'web',
        EnsureMonitoringActor::class,
        Authorize::class,
    ],

    'ignore_paths' => [
        'telescope*',
        'pulse*',
        'livewire*',
    ],

    'watchers' => [
        Watchers\RequestWatcher::class => [
            'enabled' => true,
            'size_limit' => 1,
        ],

        Watchers\QueryWatcher::class => [
            'enabled' => true,
            'slow' => (int) env('MONITORING_SLOW_QUERY_MS', 500),
        ],

        Watchers\ExceptionWatcher::class => true,
    ],
];
