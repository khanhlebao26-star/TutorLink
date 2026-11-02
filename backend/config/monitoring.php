<?php

return [
    'request_sample_rate' => (float) env('MONITORING_REQUEST_SAMPLE_RATE', 0.1),

    'slow_query_ms' => (int) env('MONITORING_SLOW_QUERY_MS', 500),

    'alerts' => [
        'enabled' => env('MONITORING_ALERTS_ENABLED', false),

        'suppressed' => env('MONITORING_ALERTS_SUPPRESSED', false),

        'cooldown_seconds' => (int) env(
            'MONITORING_ALERT_COOLDOWN_SECONDS',
            600,
        ),

        'slow_query_count' => (int) env(
            'MONITORING_SLOW_QUERY_ALERT_COUNT',
            5,
        ),

        'slow_query_window_seconds' => (int) env(
            'MONITORING_SLOW_QUERY_WINDOW_SECONDS',
            300,
        ),
    ],
];
