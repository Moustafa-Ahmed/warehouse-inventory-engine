<?php

namespace App\DTOs\Inventory;

final readonly class ReceiveStockResult
{
    public function __construct(
        public int $operationId,
        public int $movementId,
        public int $productId,
        public int $warehouseId,
        public int $receivedQuantity,
        public int $availableQuantity,
    ) {}
}
