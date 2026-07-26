<?php

namespace App\DTOs\Inventory;

final readonly class AdjustInventoryInput
{
    public function __construct(
        public int $productId,
        public int $warehouseId,
        public int $quantityChange,
        public string $reason,
        public string $idempotencyKey,
        public int $actorId,
    ) {}
}
