<?php

return [
    'host' => env('CLAMAV_HOST', 'clamav'),
    'port' => (int) env('CLAMAV_PORT', 3310),
    'timeout_seconds' => (float) env('CLAMAV_TIMEOUT_SECONDS', 10),

    'max_bytes' => 10 * 1024 * 1024,

    'archive' => [
        'max_file_bytes' => 25 * 1024 * 1024,
        'max_total_bytes' => 50 * 1024 * 1024,
        'max_files' => 1000,
    ],
];
