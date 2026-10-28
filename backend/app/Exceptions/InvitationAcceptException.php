<?php

namespace App\Exceptions;

use RuntimeException;

final class InvitationAcceptException extends RuntimeException
{
    /** @param array<string, list<string>> $errors */
    public function __construct(
        public readonly int $httpStatus,
        public readonly string $errorCode,
        string $message,
        public readonly array $errors = [],
    ) {
        parent::__construct($message);
    }
}
