<?php

namespace App\DTOs\Orders;

final readonly class CreateOrderInput
{
    /**
     * @param  array<int, CreateOrderItemInput>  $items
     */
    public function __construct(
        public string $orderNumber,
        public array $items,
        public string $idempotencyKey,
    ) {}
}
