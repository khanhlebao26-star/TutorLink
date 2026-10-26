<?php

namespace App\Exceptions;

use RuntimeException;

final class ProgramActionException extends RuntimeException
{
    /** @param array<string, mixed> $errors */
    public function __construct(
        public readonly int $httpStatus,
        public readonly string $errorCode,
        string $message,
        public readonly array $errors = [],
    ) {
        parent::__construct($message);
    }
}
