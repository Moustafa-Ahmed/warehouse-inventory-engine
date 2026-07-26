<?php

namespace Tests\Support;

use App\DTOs\Inventory\TransferStockInput;
use App\DTOs\Inventory\TransferStockResult;
use App\Services\Inventory\InventoryService;
use Closure;

final class ConcurrentStockTransfer
{
    /**
     * @return Closure(): TransferStockResult
     */
    public static function make(
        int $productId,
        int $sourceWarehouseId,
        int $destinationWarehouseId,
        int $quantity,
        string $idempotencyKey,
    ): Closure {
        return static function () use (
            $productId,
            $sourceWarehouseId,
            $destinationWarehouseId,
            $quantity,
            $idempotencyKey,
        ): TransferStockResult {
            usleep(100_000);

            return app(InventoryService::class)->transfer(new TransferStockInput(
                productId: $productId,
                sourceWarehouseId: $sourceWarehouseId,
                destinationWarehouseId: $destinationWarehouseId,
                quantity: $quantity,
                idempotencyKey: $idempotencyKey,
            ));
        };
    }
}
