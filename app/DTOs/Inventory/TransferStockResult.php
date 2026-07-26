<?php

namespace App\DTOs\Inventory;

final readonly class TransferStockResult
{
    public function __construct(
        public int $operationId,
        public int $movementId,
        public int $productId,
        public int $sourceWarehouseId,
        public int $destinationWarehouseId,
        public int $transferredQuantity,
        public int $sourceAvailableQuantity,
        public int $destinationAvailableQuantity,
    ) {}
}
