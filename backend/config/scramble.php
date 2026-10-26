<?php

use App\Http\Middleware\EnsureActiveApiActor;
use Dedoc\Scramble\OpenApiVersion;
use Dedoc\Scramble\SecurityDocumentation\MiddlewareAuthSecurityStrategy;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Illuminate\Support\Str;

return [
    'api_path' => 'api/v1',
    'export_path' => '../docs/api/openapi.json',
    'openapi_version' => OpenApiVersion::V3_1,
    'info' => [
        'version' => env('API_VERSION', '1.0.0'),
        'description' => 'Generated from registered Laravel API routes. First-party clients use Sanctum session cookies: GET /sanctum/csrf-cookie before login or mutations, then send the session cookie and X-XSRF-TOKEN on state-changing requests. Conditional D23 endpoints are omitted until approved and implemented.',
    ],
    'ui' => [
        'title' => 'TutorLink API',
    ],
    'security_strategy' => [
        MiddlewareAuthSecurityStrategy::class,
        [
            'middleware' => ['auth', 'auth:*', EnsureActiveApiActor::class],
            'scheme' => SecurityScheme::apiKey('cookie', env('SESSION_COOKIE', Str::slug((string) env('APP_NAME', 'laravel')).'-session'))
                ->as('sessionCookie')
                ->setDescription('Sanctum session cookie. Browser mutations also require X-XSRF-TOKEN after GET /sanctum/csrf-cookie.'),
        ],
    ],
];
