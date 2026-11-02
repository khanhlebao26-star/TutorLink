<?php

namespace App\Support;

use Illuminate\Routing\Route;
use Illuminate\Support\Arr;
use Laravel\Pulse\Entry;
use Laravel\Pulse\Value;
use Laravel\Telescope\IncomingEntry;

final class MonitoringPrivacy
{
    public static function telescope(IncomingEntry $entry): bool
    {
        $content = $entry->content;

        $entry->user = null;
        $entry->tags = [];

        if ($entry->type === 'request') {
            $entry->content = [
                ...Arr::only($content, [
                    'method',
                    'controller_action',
                    'response_status',
                    'duration',
                    'memory',
                ]),
                'uri' => self::routeTemplate(),
                'middleware' => [],
                'headers' => [],
                'payload' => [],
                'session' => [],
                'response_headers' => [],
                'response' => '[REDACTED]',
                'ip_address' => '[REDACTED]',
            ];

            return (int) ($content['response_status'] ?? 200) >= 500
                || self::sampleRequest();
        }

        if ($entry->type === 'query') {
            if (! ($content['slow'] ?? false)) {
                return false;
            }

            $fingerprint = hash('sha256', (string) ($content['hash'] ?? ''));

            $entry->content = [
                ...Arr::only($content, [
                    'connection',
                    'driver',
                    'time',
                    'slow',
                    'file',
                    'line',
                ]),
                'sql' => 'query:'.$fingerprint,
                'bindings' => [],
                'hash' => $fingerprint,
            ];

            $entry->tags = ['slow'];

            return true;
        }

        if ($entry->type === 'exception') {
            $entry->content = [
                ...Arr::only($content, [
                    'class',
                    'file',
                    'line',
                ]),
                'message' => '[REDACTED]',
                'context' => [],
                'trace' => [],
                'line_preview' => [],
            ];

            return true;
        }

        return false;
    }

    public static function pulse(Entry|Value $entry): bool
    {
        if (! $entry instanceof Entry) {
            return false;
        }

        if ($entry->type === 'slow_query') {
            $key = json_decode($entry->key, true, flags: JSON_THROW_ON_ERROR);

            $entry->key = json_encode([
                'query:'.hash('sha256', (string) $key[0]),
                null,
            ], JSON_THROW_ON_ERROR);

            return true;
        }

        if ($entry->type === 'slow_request') {
            $entry->key = json_encode([
                request()->method(),
                self::routeTemplate(),
                null,
            ], JSON_THROW_ON_ERROR);

            return true;
        }

        if ($entry->type === 'exception') {
            $key = json_decode($entry->key, true, flags: JSON_THROW_ON_ERROR);

            $entry->key = json_encode([
                (string) $key[0],
                null,
            ], JSON_THROW_ON_ERROR);

            return true;
        }

        return false;
    }

    private static function routeTemplate(): string
    {
        $route = request()->route();

        return $route instanceof Route
            ? '/'.ltrim($route->uri(), '/')
            : '/[unmatched]';
    }

    private static function sampleRequest(): bool
    {
        $rate = max(
            0.0,
            min(1.0, (float) config('monitoring.request_sample_rate')),
        );

        return random_int(1, 1_000_000) <= (int) ($rate * 1_000_000);
    }
}
