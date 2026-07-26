<?php

namespace App\DTOs\Inventory;

final readonly class AdjustInventoryResult
{
    public function __construct(
        public int $operationId,
        public int $movementId,
        public int $productId,
        public int $warehouseId,
        public int $quantityChange,
        public int $availableQuantity,
        public string $reason,
    ) {}
}
