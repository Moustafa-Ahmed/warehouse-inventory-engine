<?php

namespace App\DTOs\Fulfillment;

final readonly class UnpackReservationResult
{
    public function __construct(
        public int $operationId,
        public int $reservationId,
        public int $unpackedQuantity,
        public int $remainingPackedQuantity,
        public int $totalPickedQuantity,
        public int $outstandingQuantity,
    ) {}
}
