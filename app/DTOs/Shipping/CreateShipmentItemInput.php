<?php

namespace App\DTOs\Shipping;

final readonly class CreateShipmentItemInput
{
    public function __construct(
        public int $reservationId,
        public int $quantity,
    ) {}
}
