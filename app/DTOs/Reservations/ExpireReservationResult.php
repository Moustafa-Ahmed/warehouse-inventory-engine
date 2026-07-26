<?php

namespace App\DTOs\Reservations;

final readonly class ExpireReservationResult
{
    public function __construct(
        public int $operationId,
        public int $reservationId,
        public int $releasedQuantity,
        public int $outstandingQuantity,
    ) {}
}
