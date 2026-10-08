<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->status === 'suspended') {
            return response()->json(['message' => 'This account is suspended.'], 403);
        }

        return $next($request);
    }
}
