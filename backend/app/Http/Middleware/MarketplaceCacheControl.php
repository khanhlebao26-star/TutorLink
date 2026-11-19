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
        $cache = null;
        try {
            $key = app(PublicCatalogCache::class)->key($request);
            if ($key !== null) {
                $cache = Cache::store(config('cache.marketplace_store'));
                $cached = $cache->get($key);
                if (is_array($cached) && isset($cached['body'])) {
                    return response($cached['body'], 200, $this->headers('HIT'));
                }
            }
        } catch (Throwable) {
            // Cache is optional. Database/authorization errors in the endpoint still fail closed.
            $cache = null;
            $key = null;
        }
        $lock = null;
        if ($cache !== null && $key !== null) {
            try {
                $lock = $cache->lock($key.':build', 15);
                if (! $lock->block(1)) {
                    $lock = null;
                    $key = null;
                } else {
                    $cached = $cache->get($key);
                    if (is_array($cached) && isset($cached['body'])) {
                        $lock->release();

                        return response($cached['body'], 200, $this->headers('HIT'));
                    }
                }
            } catch (Throwable) {
                try {
                    $lock?->release();
                } catch (Throwable) {
                }
                $lock = null;
                $key = null;
            }
        }
        try {
            $response = $next($request);
            if ($response->getStatusCode() === 200 && str_contains((string) $response->headers->get('Content-Type'), 'application/json')) {
                if ($cache !== null && $key !== null) {
                    try {
                        if (! $cache->put($key, ['body' => $response->getContent()], app(PublicCatalogCache::class)->ttl($request))) {
                            $key = null;
                        }
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
        } finally {
            try {
                $lock?->release();
            } catch (Throwable) {
                // Cache failure must not replace an API response.
            }
        }
    }

    /** @return array<string,string> */
    private function headers(string $state): array
    {
        return ['Content-Type' => 'application/json', 'Cache-Control' => 'public, no-cache, must-revalidate', 'Vary' => 'Cookie, Authorization', 'X-Marketplace-Cache' => $state];
    }
}
