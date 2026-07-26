<?php

namespace App\DTOs\Reservations;

final readonly class ReleaseReservationResult
{
    public function __construct(
        public int $operationId,
        public int $reservationId,
        public int $releasedQuantity,
        public int $cancelledQuantity,
        public int $remainingReservedQuantity,
        public int $outstandingQuantity,
    ) {}
}
