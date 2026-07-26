<?php

namespace App\Services\Inventory;

use App\DTOs\Inventory\AdjustInventoryInput;
use App\DTOs\Inventory\AdjustInventoryResult;
use App\DTOs\Inventory\Movement;
use App\DTOs\Inventory\ReceiveStockInput;
use App\DTOs\Inventory\ReceiveStockResult;
use App\Enums\Inventory\MovementBucket;
use App\Enums\Operations\Type;
use App\Models\InventoryBalance;
use App\Models\Operation;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Operations\OperationService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class InventoryService
{
    public function __construct(
        private readonly OperationService $operations,
        private readonly InventoryMovementService $movements,
    ) {}

    public function receive(ReceiveStockInput $input): ReceiveStockResult
    {
        $this->validateReceiveStockInput($input);

        $result = DB::transaction(
            fn (): array => $this->operations->execute(
                Type::ReceiveStock,
                $input->idempotencyKey,
                [
                    'product_id' => $input->productId,
                    'warehouse_id' => $input->warehouseId,
                    'quantity' => $input->quantity,
                    'source_reference' => $input->sourceReference,
                    'actor_id' => $input->actorId,
                ],
                fn (Operation $operation): array => $this->applyReceipt($operation, $input),
            ),
            attempts: 3,
        );

        return new ReceiveStockResult(
            operationId: $result['operation_id'],
            movementId: $result['movement_id'],
            productId: $result['product_id'],
            warehouseId: $result['warehouse_id'],
            receivedQuantity: $result['received_quantity'],
            availableQuantity: $result['available_quantity'],
        );
    }

    public function adjust(AdjustInventoryInput $input): AdjustInventoryResult
    {
        $this->validateAdjustmentInput($input);

        $result = DB::transaction(
            fn (): array => $this->operations->execute(
                Type::AdjustInventory,
                $input->idempotencyKey,
                [
                    'product_id' => $input->productId,
                    'warehouse_id' => $input->warehouseId,
                    'quantity_change' => $input->quantityChange,
                    'reason' => $input->reason,
                    'actor_id' => $input->actorId,
                ],
                fn (Operation $operation): array => $this->applyAdjustment($operation, $input),
            ),
            attempts: 3,
        );

        return new AdjustInventoryResult(
            operationId: $result['operation_id'],
            movementId: $result['movement_id'],
            productId: $result['product_id'],
            warehouseId: $result['warehouse_id'],
            quantityChange: $result['quantity_change'],
            availableQuantity: $result['available_quantity'],
            reason: $result['reason'],
        );
    }

    private function validateReceiveStockInput(ReceiveStockInput $input): void
    {
        if ($input->quantity < 1) {
            throw new InvalidArgumentException('Received quantity must be positive.');
        }

        if ($input->sourceReference === '') {
            throw new InvalidArgumentException('A source reference is required.');
        }

        if ($input->idempotencyKey === '') {
            throw new InvalidArgumentException('An idempotency key is required.');
        }
    }

    private function validateAdjustmentInput(AdjustInventoryInput $input): void
    {
        if ($input->quantityChange === 0) {
            throw new InvalidArgumentException('Adjustment quantity must not be zero.');
        }

        if (trim($input->reason) === '') {
            throw new InvalidArgumentException('An adjustment reason is required.');
        }

        if ($input->idempotencyKey === '') {
            throw new InvalidArgumentException('An idempotency key is required.');
        }
    }

    /**
     * @return array{
     *     operation_id: int,
     *     movement_id: int,
     *     product_id: int,
     *     warehouse_id: int,
     *     received_quantity: int,
     *     available_quantity: int
     * }
     */
    private function applyReceipt(Operation $operation, ReceiveStockInput $input): array
    {
        $product = Product::query()->findOrFail($input->productId);
        $warehouse = Warehouse::query()->findOrFail($input->warehouseId);

        if (! $product->is_active) {
            throw new InvalidArgumentException('Stock cannot be received for an inactive product.');
        }

        if (! $warehouse->is_active) {
            throw new InvalidArgumentException('Stock cannot be received into an inactive warehouse.');
        }

        $movement = $this->movements->apply(
            $operation,
            new Movement(
                productId: $product->id,
                quantity: $input->quantity,
                sourceWarehouseId: null,
                sourceBucket: null,
                destinationWarehouseId: $warehouse->id,
                destinationBucket: MovementBucket::Available,
                businessReferenceType: 'stock_receipt',
                businessReferenceId: $input->sourceReference,
                actorId: $input->actorId,
            ),
        );

        $availableQuantity = InventoryBalance::query()
            ->where('product_id', $product->id)
            ->where('warehouse_id', $warehouse->id)
            ->valueOrFail('available_quantity');

        return [
            'operation_id' => $operation->id,
            'movement_id' => $movement->id,
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'received_quantity' => $input->quantity,
            'available_quantity' => $availableQuantity,
        ];
    }

    /**
     * @return array{
     *     operation_id: int,
     *     movement_id: int,
     *     product_id: int,
     *     warehouse_id: int,
     *     quantity_change: int,
     *     available_quantity: int,
     *     reason: string
     * }
     */
    private function applyAdjustment(Operation $operation, AdjustInventoryInput $input): array
    {
        $product = Product::query()->findOrFail($input->productId);
        $warehouse = Warehouse::query()->findOrFail($input->warehouseId);
        $actor = User::query()->findOrFail($input->actorId);

        if (! $product->is_active) {
            throw new InvalidArgumentException('Inventory cannot be adjusted for an inactive product.');
        }

        if (! $warehouse->is_active) {
            throw new InvalidArgumentException('Inventory cannot be adjusted at an inactive warehouse.');
        }

        $isIncrease = $input->quantityChange > 0;

        $movement = $this->movements->apply(
            $operation,
            new Movement(
                productId: $product->id,
                quantity: abs($input->quantityChange),
                sourceWarehouseId: $isIncrease ? null : $warehouse->id,
                sourceBucket: $isIncrease ? null : MovementBucket::Available,
                destinationWarehouseId: $isIncrease ? $warehouse->id : null,
                destinationBucket: $isIncrease ? MovementBucket::Available : null,
                businessReferenceType: 'inventory_adjustment',
                businessReferenceId: (string) $operation->id,
                actorId: $actor->id,
                metadata: ['reason' => $input->reason],
            ),
        );

        $availableQuantity = InventoryBalance::query()
            ->where('product_id', $product->id)
            ->where('warehouse_id', $warehouse->id)
            ->valueOrFail('available_quantity');

        return [
            'operation_id' => $operation->id,
            'movement_id' => $movement->id,
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity_change' => $input->quantityChange,
            'available_quantity' => $availableQuantity,
            'reason' => $input->reason,
        ];
    }
}
