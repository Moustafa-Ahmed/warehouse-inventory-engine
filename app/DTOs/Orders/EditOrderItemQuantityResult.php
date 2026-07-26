<?php

namespace App\DTOs\Orders;

final readonly class EditOrderItemQuantityResult
{
    public function __construct(
        public int $operationId,
        public int $orderItemId,
        public int $previousOrderedQuantity,
        public int $orderedQuantity,
        public int $quantityChange,
        public int $releasedReservedQuantity,
        public int $outstandingQuantity,
    ) {}
}
