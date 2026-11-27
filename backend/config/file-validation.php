<?php

return [
    'private_disks' => array_filter(explode(',', env('FILE_PRIVATE_DISKS', 'local,s3'))),

    'user_quota_bytes' => (int) env('FILE_USER_QUOTA_BYTES', 250_000_000),

    'max_bytes' => 5_000_000,

    'archive_timeout_seconds' => 10,

    'archive' => [
        'max_file_bytes' => 25 * 1024 * 1024,
        'max_total_bytes' => 50 * 1024 * 1024,
        'max_files' => 1000,
    ],
];
