<?php

namespace App\DTOs\Fulfillment;

final readonly class ReturnPickedInventoryResult
{
    public function __construct(
        public int $operationId,
        public int $reservationId,
        public int $returnedQuantity,
        public int $remainingPickedQuantity,
        public int $outstandingQuantity,
    ) {}
}
