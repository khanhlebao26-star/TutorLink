<?php

use App\Http\Middleware\EnsureMonitoringActor;
use Laravel\Pulse\Http\Middleware\Authorize;
use Laravel\Pulse\Recorders;

return [
    'enabled' => env('PULSE_ENABLED', false),

    'path' => 'pulse',

    'middleware' => [
        'web',
        EnsureMonitoringActor::class,
        Authorize::class,
    ],

    'storage' => [
        'driver' => 'database',

        'trim' => [
            'keep' => '7 days',
        ],

        'database' => [
            'connection' => env('DB_CONNECTION', 'pgsql'),
            'chunk' => 1000,
        ],
    ],

    'recorders' => [
        // The vendor cache card still needs display settings when recording is disabled.
        Recorders\SlowJobs::class => ['enabled' => false, 'sample_rate' => 1, 'threshold' => 1000, 'groups' => []],
        Recorders\SlowOutgoingRequests::class => ['enabled' => false, 'sample_rate' => 1, 'threshold' => 1000, 'groups' => []],
        Recorders\Queues::class => ['enabled' => false, 'sample_rate' => 1],
        Recorders\CacheInteractions::class => ['enabled' => false, 'sample_rate' => 1, 'groups' => []],
        Recorders\SlowQueries::class => [
            'enabled' => true,
            'sample_rate' => 1,
            'threshold' => (int) env('MONITORING_SLOW_QUERY_MS', 500),
            'location' => false,
            'ignore' => [
                '/\b(telescope_entries|telescope_entries_tags|telescope_monitoring|pulse_entries|pulse_aggregates|pulse_values)\b/i',
            ],
        ],

        Recorders\SlowRequests::class => [
            'enabled' => true,
            'sample_rate' => 1,
            'threshold' => 1000,
            'ignore' => [
                '#^/(telescope|pulse|livewire)(/|$)#',
            ],
        ],

        Recorders\Exceptions::class => [
            'enabled' => true,
            'sample_rate' => 1,
            'location' => false,
            'ignore' => [],
        ],
    ],
];
