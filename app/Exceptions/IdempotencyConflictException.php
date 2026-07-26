<?php

namespace App\Exceptions;

use RuntimeException;

final class IdempotencyConflictException extends RuntimeException
{
    public function __construct(public readonly string $idempotencyKey)
    {
        parent::__construct("The idempotency key [{$idempotencyKey}] has already been used for a different operation.");
    }
}
