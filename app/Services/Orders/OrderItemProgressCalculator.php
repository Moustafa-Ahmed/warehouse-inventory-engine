<?php

namespace App\Services\Orders;

use App\DTOs\Orders\OrderItemProgress;
use InvalidArgumentException;

final class OrderItemProgressCalculator
{
    public function calculate(
        int $orderedQuantity,
        int $cancelledQuantity,
        int $reservedQuantity,
        int $pickedQuantity,
        int $packedQuantity,
        int $shippedQuantity,
        int $deliveredQuantity,
    ): OrderItemProgress {
        $quantities = [
            'ordered' => $orderedQuantity,
            'cancelled' => $cancelledQuantity,
            'reserved' => $reservedQuantity,
            'picked' => $pickedQuantity,
            'packed' => $packedQuantity,
            'shipped' => $shippedQuantity,
            'delivered' => $deliveredQuantity,
        ];

        foreach ($quantities as $name => $quantity) {
            if ($quantity < 0) {
                throw new InvalidArgumentException("The {$name} quantity cannot be negative.");
            }
        }

        $allocatedQuantity = $reservedQuantity
            + $pickedQuantity
            + $packedQuantity
            + $shippedQuantity;
        $outstandingQuantity = $orderedQuantity
            - $cancelledQuantity
            - $allocatedQuantity;

        if ($outstandingQuantity < 0) {
            throw new InvalidArgumentException('Order item quantities exceed the ordered quantity.');
        }

        if ($deliveredQuantity > $shippedQuantity) {
            throw new InvalidArgumentException('Delivered quantity cannot exceed shipped quantity.');
        }

        return new OrderItemProgress(
            orderedQuantity: $orderedQuantity,
            cancelledQuantity: $cancelledQuantity,
            outstandingQuantity: $outstandingQuantity,
            allocatedQuantity: $allocatedQuantity,
            reservedQuantity: $reservedQuantity,
            pickedQuantity: $pickedQuantity,
            packedQuantity: $packedQuantity,
            shippedQuantity: $shippedQuantity,
            deliveredQuantity: $deliveredQuantity,
            unshippedUncancelledQuantity: $orderedQuantity - $cancelledQuantity - $shippedQuantity,
            undeliveredShippedQuantity: $shippedQuantity - $deliveredQuantity,
        );
    }
}
