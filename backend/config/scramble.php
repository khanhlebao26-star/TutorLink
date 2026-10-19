<?php

use Dedoc\Scramble\OpenApiVersion;
use Dedoc\Scramble\SecurityDocumentation\MiddlewareAuthSecurityStrategy;

return [
    'api_path' => 'api/v1',
    'export_path' => '../docs/api/openapi.json',
    'openapi_version' => OpenApiVersion::V3_1,
    'info' => [
        'version' => env('API_VERSION', '1.0.0'),
        'description' => 'Generated from registered Laravel API routes. Conditional D23 endpoints are omitted until approved and implemented.',
    ],
    'ui' => [
        'title' => 'TutorLink API',
    ],
    'security_strategy' => MiddlewareAuthSecurityStrategy::class,
];
