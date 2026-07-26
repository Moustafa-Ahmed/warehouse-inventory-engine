<?php

namespace App\DTOs\Reservations;

final readonly class ReserveOrderItemInput
{
    public function __construct(
        public int $orderItemId,
        public int $warehouseId,
        public string $idempotencyKey,
        public ?int $actorId = null,
        public string $source = 'system',
    ) {}
}
