<?php

namespace App\DTOs\Shipping;

final readonly class CreateShipmentInput
{
    /**
     * @param  array<int, CreateShipmentItemInput>  $items
     */
    public function __construct(
        public int $orderId,
        public int $warehouseId,
        public array $items,
        public string $idempotencyKey,
    ) {}
}
