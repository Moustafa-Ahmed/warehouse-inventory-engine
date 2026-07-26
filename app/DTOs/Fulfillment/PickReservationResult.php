<?php

namespace App\DTOs\Fulfillment;

final readonly class PickReservationResult
{
    public function __construct(
        public int $operationId,
        public int $reservationId,
        public int $pickedQuantity,
        public int $remainingReservedQuantity,
        public int $totalPickedQuantity,
        public int $outstandingQuantity,
    ) {}
}
