<?php

namespace App\DTOs\Orders;

final readonly class CreateOrderResult
{
    /**
     * @param  array<int, array{
     *     order_item_id: int,
     *     product_id: int,
     *     ordered_quantity: int,
     *     outstanding_quantity: int
     * }>  $items
     */
    public function __construct(
        public int $operationId,
        public int $orderId,
        public string $orderNumber,
        public array $items,
    ) {}
}
