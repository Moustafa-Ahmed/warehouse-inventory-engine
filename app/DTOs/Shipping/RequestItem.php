<?php

namespace App\DTOs\Shipping;

final readonly class RequestItem
{
    public function __construct(
        public int $shipmentItemId,
        public int $quantity,
    ) {}
}
