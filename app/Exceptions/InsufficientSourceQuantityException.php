<?php

namespace App\Exceptions;

use App\Enums\Inventory\MovementBucket;
use RuntimeException;

final class InsufficientSourceQuantityException extends RuntimeException
{
    public function __construct(
        public readonly int $productId,
        public readonly int $warehouseId,
        public readonly MovementBucket $bucket,
        public readonly int $requestedQuantity,
        public readonly int $availableQuantity,
    ) {
        parent::__construct(
            "Cannot move {$requestedQuantity} units from {$bucket->value}; only {$availableQuantity} units are available."
        );
    }
}
