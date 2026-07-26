<?php

namespace App\DTOs\Reservations;

use App\Enums\Reservations\Kind;

final readonly class ConfirmReservationResult
{
    public function __construct(
        public int $operationId,
        public int $reservationId,
        public Kind $kind,
    ) {}
}
