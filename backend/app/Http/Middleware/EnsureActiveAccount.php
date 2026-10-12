<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        if (in_array($request->user()?->status, ['inactive', 'cancelled'], true)) {
            return response()->json(['message' => 'This account is not available.'], 403);
        }

        return $next($request);
    }
}
