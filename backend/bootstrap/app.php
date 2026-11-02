<?php

use App\Http\Middleware\NormalizeBeD04Request;
use App\Support\ApiErrorResponse;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
        $isMessageSend = fn (Request $request): bool => $request->isMethod('POST')
            && $request->is('api/v1/conversations/*/messages');
        $isTaskRequest = fn (Request $request): bool => $request->is(
            'api/v1/programs/*/tasks',
            'api/v1/tasks/*',
        );
        $isBeD04Request = NormalizeBeD04Request::isBeD04Request(...);
        $middleware->trimStrings(except: [$isMessageSend, $isTaskRequest, $isBeD04Request]);
        $middleware->convertEmptyStringsToNull(except: [$isMessageSend, $isBeD04Request]);
        $middleware->appendToGroup('api', NormalizeBeD04Request::class);
        $middleware->redirectGuestsTo(
            fn (Request $request) => $request->is('api/*') ? null : route('login'),
        );
        $middleware->alias([
            'abilities' => CheckAbilities::class,
            'ability' => CheckForAnyAbility::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $isTrustRequest = fn (Request $request): bool => $request->is(
            'api/v1/programs/*/review',
            'api/v1/reports',
            'api/v1/me/reports',
            'api/v1/me/reports/*',
            'api/v1/admin/reports',
            'api/v1/admin/reports/*',
            'api/v1/admin/reviews',
            'api/v1/admin/reviews/*',
            'api/v1/admin/audit-logs',
            'api/v1/admin/audit-logs/*',
        );
        $error = function (Request $request, int $status, string $code, string $message, array $errors = []): JsonResponse {
            $requestId = $request->attributes->get('request_id') ?: (string) Str::uuid();

            return ApiErrorResponse::make($status, $code, $message, $requestId, $errors);
        };
        $exceptions->render(function (ValidationException $exception, Request $request) use ($isTrustRequest, $error): ?JsonResponse {
            return $isTrustRequest($request)
                ? $error($request, 422, 'validation_failed', 'The given data was invalid.', $exception->errors())
                : null;
        });
        $exceptions->render(function (HttpExceptionInterface $exception, Request $request) use ($isTrustRequest, $error): ?JsonResponse {
            if (! $isTrustRequest($request)) {
                return null;
            }
            $status = $exception->getStatusCode();
            $code = match ($status) {
                403 => 'permission_denied',
                404 => 'resource_not_found',
                405 => 'method_not_allowed',
                419 => 'csrf_token_mismatch',
                default => 'request_failed',
            };

            return $error($request, $status, $code, 'The request could not be completed.')->withHeaders($exception->getHeaders());
        });
        $exceptions->render(function (Throwable $exception, Request $request) use ($isTrustRequest, $error): ?JsonResponse {
            return $isTrustRequest($request) ? $error($request, 500, 'internal_error', 'The request could not be completed.') : null;
        });
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
