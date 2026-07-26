<?php

namespace App\DTOs\Orders;

final readonly class CreateOrderItemInput
{
    public function __construct(
        public int $productId,
        public int $orderedQuantity,
    ) {}
}
