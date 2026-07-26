<?php

namespace App\Exceptions;

use RuntimeException;

final class InsufficientReservedQuantityException extends RuntimeException
{
    public function __construct(
        public readonly int $reservationId,
        public readonly int $requestedQuantity,
        public readonly int $reservedQuantity,
    ) {
        parent::__construct(
            "Cannot release {$requestedQuantity} units from reservation [{$reservationId}]; only {$reservedQuantity} units remain reserved."
        );
    }
}
