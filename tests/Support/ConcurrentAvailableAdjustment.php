<?php

namespace Tests\Support;

use App\DTOs\Inventory\AdjustInventoryInput;
use App\Exceptions\InsufficientSourceQuantityException;
use App\Services\Inventory\InventoryService;
use Closure;

final class ConcurrentAvailableAdjustment
{
    /**
     * @return Closure(): string
     */
    public static function make(
        int $productId,
        int $warehouseId,
        int $actorId,
        string $idempotencyKey,
    ): Closure {
        return static function () use (
            $productId,
            $warehouseId,
            $actorId,
            $idempotencyKey,
        ): string {
            usleep(100_000);

            try {
                app(InventoryService::class)->adjust(new AdjustInventoryInput(
                    productId: $productId,
                    warehouseId: $warehouseId,
                    quantityChange: -1,
                    reason: 'Concurrent final-unit adjustment',
                    idempotencyKey: $idempotencyKey,
                    actorId: $actorId,
                ));

                return 'succeeded';
            } catch (InsufficientSourceQuantityException) {
                return 'rejected';
            }
        };
    }
}
