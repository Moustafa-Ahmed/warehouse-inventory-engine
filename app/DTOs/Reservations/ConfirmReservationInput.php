<?php

namespace App\DTOs\Reservations;

final readonly class ConfirmReservationInput
{
    public function __construct(
        public int $reservationId,
        public string $idempotencyKey,
        public ?int $actorId = null,
        public string $source = 'system',
    ) {}
}
