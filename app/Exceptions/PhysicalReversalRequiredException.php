<?php

namespace App\Exceptions;

use RuntimeException;

final class PhysicalReversalRequiredException extends RuntimeException
{
    public function __construct(
        public readonly int $orderItemId,
        public readonly int $requestedReduction,
        public readonly int $reducibleQuantity,
        public readonly int $pickedQuantity,
        public readonly int $packedQuantity,
    ) {
        parent::__construct(
            "Cannot reduce order item [{$orderItemId}] by {$requestedReduction}; only {$reducibleQuantity} units are outstanding or reserved. Picked or packed inventory must be physically reversed first."
        );
    }
}
