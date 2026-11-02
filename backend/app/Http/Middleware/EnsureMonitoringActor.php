<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMonitoringActor
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = app(EnsureActiveApiActor::class)->handle($request, $next);

        // Livewire persistent middleware only halts on exceptions or redirects.
        if ($response->getStatusCode() >= 400) {
            throw new HttpResponseException($response);
        }

        return $response;
    }
}
