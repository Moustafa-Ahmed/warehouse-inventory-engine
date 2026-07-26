<?php

namespace App\DTOs\Reservations;

final readonly class ReleaseReservationInput
{
    public function __construct(
        public int $reservationId,
        public int $quantity,
        public bool $cancelOrderDemand,
        public string $reason,
        public string $idempotencyKey,
        public ?int $actorId = null,
        public string $source = 'system',
    ) {}
}
