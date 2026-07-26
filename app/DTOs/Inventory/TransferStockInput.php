<?php

namespace App\DTOs\Inventory;

final readonly class TransferStockInput
{
    public function __construct(
        public int $productId,
        public int $sourceWarehouseId,
        public int $destinationWarehouseId,
        public int $quantity,
        public string $idempotencyKey,
        public ?int $actorId = null,
    ) {}
}
