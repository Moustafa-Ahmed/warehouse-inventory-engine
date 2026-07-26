<?php

namespace App\DTOs\Reservations;

final readonly class ReserveOrderItemResult
{
    public function __construct(
        public int $operationId,
        public ?int $reservationId,
        public int $requestedQuantity,
        public int $allocatedQuantity,
        public int $outstandingQuantity,
        public bool $fullyAllocated,
    ) {}
}
