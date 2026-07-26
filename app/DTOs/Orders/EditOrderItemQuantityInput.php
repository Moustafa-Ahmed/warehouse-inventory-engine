<?php

namespace App\DTOs\Orders;

final readonly class EditOrderItemQuantityInput
{
    public function __construct(
        public int $orderItemId,
        public int $quantityChange,
        public string $reason,
        public string $idempotencyKey,
        public ?int $actorId = null,
        public string $source = 'system',
    ) {}
}
