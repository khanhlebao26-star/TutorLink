<?php

declare(strict_types=1);

use Dedoc\Scramble\Attributes\ExcludeAllRoutesFromDocs;
use Dedoc\Scramble\Attributes\ExcludeRouteFromDocs;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;

require dirname(__DIR__).'/vendor/autoload.php';

$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$specPath = $argv[1] ?? dirname(__DIR__, 2).'/docs/api/openapi.json';

if (! is_file($specPath)) {
    fwrite(STDERR, "OpenAPI document not found: {$specPath}\n");
    exit(1);
}

$specContents = file_get_contents($specPath);

if ($specContents === false) {
    fwrite(STDERR, "Unable to read OpenAPI document: {$specPath}\n");
    exit(1);
}

$spec = json_decode($specContents, true, flags: JSON_THROW_ON_ERROR);
$apiPath = trim((string) config('scramble.api_path', 'api/v1'), '/');
$apiPrefix = '/'.$apiPath;

if (! is_array($spec) || ! is_array($spec['paths'] ?? null)) {
    fwrite(STDERR, "OpenAPI document must contain a paths object.\n");
    exit(1);
}

$paths = $spec['paths'];
$servers = $spec['servers'] ?? [];

if (! is_array($servers) || ! is_array($servers[0] ?? [])) {
    fwrite(STDERR, "OpenAPI servers must be an array of server objects.\n");
    exit(1);
}

$server = $servers[0] ?? [];
$serverUrl = $server['url'] ?? $apiPrefix;

if (! is_string($serverUrl)) {
    fwrite(STDERR, "OpenAPI server URL must be a string.\n");
    exit(1);
}

$serverPath = parse_url($serverUrl, PHP_URL_PATH);

if ($serverPath === false) {
    fwrite(STDERR, "OpenAPI server URL is invalid.\n");
    exit(1);
}

$serverPrefix = is_string($serverPath) ? '/'.trim($serverPath, '/') : '';
$methods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];
$expected = [];
$actual = [];

foreach (Route::getRoutes() as $route) {
    $uri = '/'.trim($route->uri(), '/');

    if (! str_starts_with($uri, $apiPrefix.'/') && $uri !== $apiPrefix) {
        continue;
    }

    if (isRouteExcludedFromOpenApi($route)) {
        continue;
    }

    foreach (array_intersect($methods, $route->methods()) as $method) {
        $expected[] = $method.' '.$uri;
    }
}

foreach ($paths as $path => $operations) {
    if (! is_string($path) || ! is_array($operations)) {
        fwrite(STDERR, "OpenAPI paths must map path strings to operation objects.\n");
        exit(1);
    }

    $uri = rtrim($serverPrefix, '/').'/'.ltrim($path, '/');
    $uri = rtrim($uri, '/') ?: '/';

    foreach ($operations as $operationMethod => $operation) {
        if (! is_string($operationMethod) || ! is_array($operation)) {
            continue;
        }

        $method = strtoupper($operationMethod);

        if (in_array($method, $methods, true)) {
            $actual[] = $method.' '.$uri;
        }
    }
}

$expected = array_values(array_unique($expected));
$actual = array_values(array_unique($actual));
$missing = array_values(array_diff($expected, $actual));
$extra = array_values(array_diff($actual, $expected));

if ($missing !== [] || $extra !== []) {
    fwrite(STDERR, "OpenAPI operations do not match registered {$apiPrefix} routes.\n");

    foreach ($missing as $operation) {
        fwrite(STDERR, "Missing from OpenAPI: {$operation}\n");
    }

    foreach ($extra as $operation) {
        fwrite(STDERR, "Not registered in Laravel: {$operation}\n");
    }

    exit(1);
}

printf("OpenAPI route parity passed (%d operations).\n", count($expected));

function isRouteExcludedFromOpenApi(LaravelRoute $route): bool
{
    $controllerAction = $route->getAction('controller');

    if (! is_string($controllerAction) || ! str_contains($controllerAction, '@')) {
        return false;
    }

    [$controllerClass, $controllerMethod] = explode('@', $controllerAction, 2);

    if (! class_exists($controllerClass)) {
        return false;
    }

    if ((new ReflectionClass($controllerClass))->getAttributes(ExcludeAllRoutesFromDocs::class) !== []) {
        return true;
    }

    return method_exists($controllerClass, $controllerMethod)
        && (new ReflectionMethod($controllerClass, $controllerMethod))->getAttributes(ExcludeRouteFromDocs::class) !== [];
}
