<?php

namespace App\Support;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Pagination\Cursor;
use Illuminate\Support\Facades\Crypt;
use JsonException;

final class MessagingCursor
{
    public function encode(string $scope, ?Cursor $position): string
    {
        return Crypt::encryptString(json_encode([
            'version' => 1,
            'scope' => $scope,
            'position' => $position?->encode(),
        ], JSON_THROW_ON_ERROR));
    }

    /** @param list<string> $parameters */
    public function decode(string $token, string $scope, array $parameters, string $requestId): ?Cursor
    {
        $binary = base64_decode($token, true);

        if ($binary === false || base64_encode($binary) !== $token) {
            $this->invalid($requestId);
        }

        try {
            $data = json_decode(Crypt::decryptString($token), true, 16, JSON_THROW_ON_ERROR);
        } catch (DecryptException|JsonException) {
            $this->invalid($requestId);
        }

        if (
            ! is_array($data) || ($data['version'] ?? null) !== 1
            || ($data['scope'] ?? null) !== $scope || ! array_key_exists('position', $data)
        ) {
            $this->invalid($requestId);
        }

        if ($data['position'] === null) {
            return null;
        }

        $cursor = is_string($data['position']) ? Cursor::fromEncoded($data['position']) : null;

        if ($cursor === null || ! $cursor->pointsToNextItems()) {
            $this->invalid($requestId);
        }

        $values = $cursor->toArray();
        unset($values['_pointsToNextItems']);

        if (array_keys($values) !== $parameters) {
            $this->invalid($requestId);
        }

        foreach ($values as $value) {
            if (! is_int($value) && ! is_string($value)) {
                $this->invalid($requestId);
            }
        }

        return $cursor;
    }

    public function invalid(string $requestId): never
    {
        throw new HttpResponseException(ApiErrorResponse::make(
            422,
            'invalid_cursor',
            'The cursor is invalid for this request.',
            $requestId,
        ));
    }
}
