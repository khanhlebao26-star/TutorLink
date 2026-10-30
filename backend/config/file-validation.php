<?php

return [
    'max_bytes' => 5_000_000,

    'archive_timeout_seconds' => 10,

    'archive' => [
        'max_file_bytes' => 25 * 1024 * 1024,
        'max_total_bytes' => 50 * 1024 * 1024,
        'max_files' => 1000,
    ],
];
