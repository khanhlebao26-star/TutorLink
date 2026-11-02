<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class NormalizeBeD04Request
{
    /**
     * Normalize exactly the strings that reach validation and hashing.
     * Body/note whitespace is preserved; title/reason are trimmed.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->isBeD04Request($request) || ! $request->isJson() || $request->isMethod('GET')) {
            return $next($request);
        }

        $raw = json_decode($request->getContent());

        if (! $raw instanceof \stdClass) {
            return $next($request);
        }

        $payload = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $payload = $this->normalizeValue($payload);

        foreach (['title', 'reason'] as $field) {
            if (isset($payload[$field]) && is_string($payload[$field])) {
                $payload[$field] = trim($payload[$field]);
            }
        }

        $request->json()->replace($payload);

        return $next($request);
    }

    public static function isBeD04Request(Request $request): bool
    {
        return $request->is(
            'api/v1/programs/*/overview',
            'api/v1/programs/*/progress-records',
            'api/v1/progress-records/*',
            'api/v1/programs/*/announcements',
            'api/v1/announcements/*',
            'api/v1/programs/*/fee-records',
            'api/v1/fee-records/*',
        );
    }

    private function normalizeValue(mixed $value): mixed
    {
        if (is_string($value)) {
            return str_replace(["\r\n", "\r"], "\n", $value);
        }

        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = $this->normalizeValue($item);
            }
        }

        return $value;
    }
}
