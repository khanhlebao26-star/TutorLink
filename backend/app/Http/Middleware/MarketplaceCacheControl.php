<?php

namespace App\Http\Middleware;

use App\Support\PublicCatalogCache;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class MarketplaceCacheControl
{
    public function handle(Request $request, Closure $next): Response
    {
        $public = $request->isMethod('GET') && $request->is('api/v1/catalog/categories', 'api/v1/catalog/specializations', 'api/v1/listings', 'api/v1/listings/*', 'api/v1/tutors/*')
            && ! $request->headers->has('Authorization') && ! $request->headers->has('Cookie') && $request->user() === null;
        if (! $public) {
            $response = $next($request);
            $response->headers->set('Cache-Control', 'private, no-store');

            return $response;
        }
        $key = null;
        try {
            $key = app(PublicCatalogCache::class)->key($request);
            $cached = $key === null ? null : Cache::get($key);
            if (is_array($cached) && isset($cached['body'])) {
                return response($cached['body'], 200, $this->headers('HIT'));
            }
        } catch (Throwable) {
            // Cache is optional. Database/authorization errors in the endpoint still fail closed.
        }
        $response = $next($request);
        if ($response->getStatusCode() === 200 && str_contains((string) $response->headers->get('Content-Type'), 'application/json')) {
            if ($key !== null) {
                try {
                    Cache::put($key, ['body' => $response->getContent()], PublicCatalogCache::TTL_SECONDS);
                } catch (Throwable) {
                    $key = null;
                }
            }
            foreach ($this->headers($key === null ? 'BYPASS' : 'MISS') as $name => $value) {
                $response->headers->set($name, $value);
            }
        } else {
            $response->headers->set('Cache-Control', 'private, no-store');
        }

        return $response;
    }

    /** @return array<string,string> */
    private function headers(string $state): array
    {
        return ['Content-Type' => 'application/json', 'Cache-Control' => 'public, no-cache, must-revalidate', 'Vary' => 'Cookie, Authorization', 'X-Marketplace-Cache' => $state];
    }
}
