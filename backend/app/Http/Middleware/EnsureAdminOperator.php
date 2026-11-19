<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\AdminAuthorization;
use App\Support\AdminPermissions;
use App\Support\RealtimeSessionChannels;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminOperator
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()) {
            return redirect()->route('filament.admin.auth.login');
        }
        $actor = User::find($request->user()->id);
        abort_unless($actor && app(AdminAuthorization::class)->allows($actor, AdminPermissions::VIEW_DASHBOARD), 403);
        abort_if($request->hasSession() && app(RealtimeSessionChannels::class)->isRevoked($actor->id, $request->session()->getId()), 403);
        Auth::guard('web')->setUser($actor);
        $request->setUserResolver(fn () => $actor);
        $response = $next($request);
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }
}
