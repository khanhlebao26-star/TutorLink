<?php

namespace App\Http\Middleware;

use App\Support\RealtimeSessionChannels;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        $browserActor = Auth::guard('web')->user();

        if (
            $browserActor !== null
            && $request->hasSession()
            && app(RealtimeSessionChannels::class)->isRevoked(
                (int) $browserActor->id,
                $request->session()->getId(),
            )
        ) {
            return response()->json([
                'message' => 'This browser session has been revoked.',
                'code' => 'session_revoked',
            ], 401);
        }

        if ($request->user()?->status !== 'active') {
            return response()->json([
                'message' => 'This account is not available.',
            ], 403);
        }

        return $next($request);
    }
}
