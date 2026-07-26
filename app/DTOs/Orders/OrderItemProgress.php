<?php

namespace App\DTOs\Orders;

final readonly class OrderItemProgress
{
    public function __construct(
        public int $orderedQuantity,
        public int $cancelledQuantity,
        public int $outstandingQuantity,
        public int $allocatedQuantity,
        public int $reservedQuantity,
        public int $pickedQuantity,
        public int $packedQuantity,
        public int $shippedQuantity,
        public int $deliveredQuantity,
        public int $unshippedUncancelledQuantity,
        public int $undeliveredShippedQuantity,
    ) {}
}
