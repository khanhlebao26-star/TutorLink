<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\ApiErrorResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveApiActor
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $request->header('X-Request-ID');
        $requestId = is_string($requestId) && Str::isUuid($requestId)
            ? strtolower($requestId)
            : (string) Str::uuid();

        $request->attributes->set('request_id', $requestId);
        $actor = Auth::guard('web')->user();

        if (! $actor instanceof User) {
            return ApiErrorResponse::make(
                401,
                'unauthenticated',
                'Authentication is required.',
                $requestId,
            );
        }

        Auth::shouldUse('web');
        $request->setUserResolver(fn (): User => $actor);

        if ($actor->status !== 'active') {
            return ApiErrorResponse::make(
                403,
                'account_inactive',
                'This account is not available.',
                $requestId,
            );
        }

        $response = $next($request);
        $response->headers->set('X-Request-ID', $requestId);

        return $response;
    }
}
