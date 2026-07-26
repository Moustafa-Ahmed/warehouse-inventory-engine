<?php

namespace App\DTOs\Reservations;

final readonly class ExpireReservationInput
{
    public function __construct(
        public int $reservationId,
        public string $idempotencyKey,
        public string $source = 'expiration_sweep',
    ) {}
}
