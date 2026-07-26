<?php

namespace App\DTOs\Inventory;

use App\Enums\Inventory\MovementBucket;

final readonly class Movement
{
    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function __construct(
        public int $productId,
        public int $quantity,
        public ?int $sourceWarehouseId,
        public ?MovementBucket $sourceBucket,
        public ?int $destinationWarehouseId,
        public ?MovementBucket $destinationBucket,
        public string $businessReferenceType,
        public string $businessReferenceId,
        public ?int $actorId = null,
        public ?array $metadata = null,
    ) {}
}
