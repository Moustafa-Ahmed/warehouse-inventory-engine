<?php

namespace App\DTOs\Fulfillment;

final readonly class PickReservationInput
{
    public function __construct(
        public int $reservationId,
        public int $quantity,
        public string $idempotencyKey,
        public ?int $actorId = null,
        public string $source = 'system',
    ) {}
}
