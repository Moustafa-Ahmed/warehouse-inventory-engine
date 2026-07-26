<?php

namespace App\DTOs\Fulfillment;

final readonly class PackReservationResult
{
    public function __construct(
        public int $operationId,
        public int $reservationId,
        public int $packedQuantity,
        public int $remainingPickedQuantity,
        public int $totalPackedQuantity,
        public int $outstandingQuantity,
    ) {}
}
