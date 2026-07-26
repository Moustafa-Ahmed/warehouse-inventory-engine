<?php

namespace App\Services\Inventory;

use App\DTOs\Inventory\Movement;
use App\Enums\Inventory\MovementBucket;
use App\Exceptions\InsufficientSourceQuantityException;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\Operation;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

final class InventoryMovementService
{
    public function apply(Operation $operation, Movement $movement): InventoryMovement
    {
        if (DB::connection()->transactionLevel() === 0) {
            throw new LogicException('InventoryMovementService must execute inside the caller transaction.');
        }

        $this->validate($movement);
        $balances = $this->resolveAndLockBalances($movement);
        $this->ensureSourceQuantityIsAvailable($movement, $balances);

        $inventoryMovement = InventoryMovement::query()->create([
            'operation_id' => $operation->id,
            'product_id' => $movement->productId,
            'source_warehouse_id' => $movement->sourceWarehouseId,
            'source_bucket' => $movement->sourceBucket,
            'destination_warehouse_id' => $movement->destinationWarehouseId,
            'destination_bucket' => $movement->destinationBucket,
            'quantity' => $movement->quantity,
            'business_reference_type' => $movement->businessReferenceType,
            'business_reference_id' => $movement->businessReferenceId,
            'actor_id' => $movement->actorId,
            'metadata' => $movement->metadata,
        ]);

        $this->applyProjectionChanges($movement, $balances);

        return $inventoryMovement;
    }

    private function validate(Movement $movement): void
    {
        if ($movement->quantity < 1) {
            throw new InvalidArgumentException('Movement quantity must be positive.');
        }

        $sourceExists = $movement->sourceWarehouseId !== null || $movement->sourceBucket !== null;
        $destinationExists = $movement->destinationWarehouseId !== null || $movement->destinationBucket !== null;

        if (! $sourceExists && ! $destinationExists) {
            throw new InvalidArgumentException('An inventory movement requires at least one endpoint.');
        }

        $this->validateWarehouseEndpoint(
            $movement->sourceWarehouseId,
            $movement->sourceBucket,
            false,
        );
        $this->validateWarehouseEndpoint(
            $movement->destinationWarehouseId,
            $movement->destinationBucket,
            true,
        );
    }

    private function validateWarehouseEndpoint(
        ?int $warehouseId,
        ?MovementBucket $bucket,
        bool $allowsExternalShipment,
    ): void {
        if ($warehouseId !== null && ($bucket === null || $bucket === MovementBucket::Shipped)) {
            throw new InvalidArgumentException('A warehouse endpoint requires a mutable warehouse bucket.');
        }

        if (
            $warehouseId === null
            && $bucket !== null
            && (! $allowsExternalShipment || $bucket !== MovementBucket::Shipped)
        ) {
            throw new InvalidArgumentException('The external endpoint bucket is invalid.');
        }
    }

    /**
     * @return Collection<int, InventoryBalance>
     */
    private function resolveAndLockBalances(Movement $movement): Collection
    {
        $warehouseIds = array_values(array_unique(array_filter([
            $movement->sourceWarehouseId,
            $movement->destinationWarehouseId,
        ], fn (?int $warehouseId): bool => $warehouseId !== null)));
        sort($warehouseIds);

        foreach ($warehouseIds as $warehouseId) {
            InventoryBalance::query()->firstOrCreate([
                'product_id' => $movement->productId,
                'warehouse_id' => $warehouseId,
            ]);
        }

        return InventoryBalance::query()
            ->where('product_id', $movement->productId)
            ->whereIn('warehouse_id', $warehouseIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('warehouse_id');
    }

    /**
     * @param  Collection<int, InventoryBalance>  $balances
     */
    private function ensureSourceQuantityIsAvailable(Movement $movement, Collection $balances): void
    {
        if ($movement->sourceWarehouseId === null || $movement->sourceBucket === null) {
            return;
        }

        $sourceBalance = $balances->get($movement->sourceWarehouseId);
        $sourceColumn = $this->quantityColumn($movement->sourceBucket);
        $availableQuantity = $sourceBalance->{$sourceColumn};

        if ($availableQuantity < $movement->quantity) {
            throw new InsufficientSourceQuantityException(
                $movement->productId,
                $movement->sourceWarehouseId,
                $movement->sourceBucket,
                $movement->quantity,
                $availableQuantity,
            );
        }
    }

    /**
     * @param  Collection<int, InventoryBalance>  $balances
     */
    private function applyProjectionChanges(Movement $movement, Collection $balances): void
    {
        /** @var array<int, array<string, int>> $deltas */
        $deltas = [];

        if ($movement->sourceWarehouseId !== null && $movement->sourceBucket !== null) {
            $sourceColumn = $this->quantityColumn($movement->sourceBucket);
            $deltas[$movement->sourceWarehouseId][$sourceColumn] = -$movement->quantity;
        }

        if ($movement->destinationWarehouseId !== null && $movement->destinationBucket !== null) {
            $destinationColumn = $this->quantityColumn($movement->destinationBucket);
            $deltas[$movement->destinationWarehouseId][$destinationColumn] =
                ($deltas[$movement->destinationWarehouseId][$destinationColumn] ?? 0)
                + $movement->quantity;
        }

        foreach ($balances as $balance) {
            $changes = [];

            foreach ($deltas[$balance->warehouse_id] ?? [] as $column => $delta) {
                $changes[$column] = $balance->{$column} + $delta;
            }

            if ($changes !== []) {
                $balance->forceFill($changes)->save();
            }
        }
    }

    private function quantityColumn(MovementBucket $bucket): string
    {
        return match ($bucket) {
            MovementBucket::Available => 'available_quantity',
            MovementBucket::Reserved => 'reserved_quantity',
            MovementBucket::Picked => 'picked_quantity',
            MovementBucket::Packed => 'packed_quantity',
            MovementBucket::Shipped => throw new InvalidArgumentException(
                'Shipped is an external movement classification, not a warehouse balance bucket.'
            ),
        };
    }
}
