<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

final class ApiErrorResponse
{
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
