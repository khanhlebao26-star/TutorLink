<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

final class ApiErrorResponse
{
    public static function staleVersion(int $currentVersion, string $requestId): JsonResponse
    {
        return self::make(
            409,
            'stale_version',
            'The resource changed. Reload before updating.',
            $requestId,
            ['current_version' => $currentVersion],
        );
    }

    /** @param array<string, mixed> $errors */
    public static function make(
        int $status,
        string $code,
        string $message,
        string $requestId,
        array $errors = [],
    ): JsonResponse {
        return response()->json([
            'code' => $code,
            'message' => $message,
            'request_id' => $requestId,
            'errors' => $errors === [] ? new \stdClass : $errors,
        ], $status, ['X-Request-ID' => $requestId]);
    }
}
