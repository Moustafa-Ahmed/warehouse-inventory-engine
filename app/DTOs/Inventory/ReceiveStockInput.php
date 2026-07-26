<?php

namespace App\DTOs\Inventory;

final readonly class ReceiveStockInput
{
    public function __construct(
        public int $productId,
        public int $warehouseId,
        public int $quantity,
        public string $sourceReference,
        public string $idempotencyKey,
        public ?int $actorId = null,
    ) {}
}
