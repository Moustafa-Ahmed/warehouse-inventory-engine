<?php

namespace App\DTOs\Fulfillment;

final readonly class ReturnPickedInventoryInput
{
    public function __construct(
        public int $reservationId,
        public int $quantity,
        public string $reason,
        public string $idempotencyKey,
        public int $actorId,
        public string $source = 'administrator',
    ) {}
}
