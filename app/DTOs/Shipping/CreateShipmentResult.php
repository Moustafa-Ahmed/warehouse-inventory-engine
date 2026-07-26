<?php

namespace App\DTOs\Shipping;

final readonly class CreateShipmentResult
{
    /**
     * @param  array<int, array{
     *     shipment_item_id: int,
     *     reservation_id: int,
     *     quantity: int
     * }>  $items
     */
    public function __construct(
        public int $operationId,
        public int $shipmentId,
        public int $orderId,
        public int $warehouseId,
        public array $items,
    ) {}
}
