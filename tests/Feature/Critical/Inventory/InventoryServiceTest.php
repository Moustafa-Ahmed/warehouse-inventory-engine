<?php

use App\DTOs\Inventory\AdjustInventoryInput;
use App\DTOs\Inventory\Movement;
use App\DTOs\Inventory\ReceiveStockInput;
use App\Enums\Inventory\MovementBucket;
use App\Exceptions\InsufficientSourceQuantityException;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\Operation;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Inventory\InventoryMovementService;
use App\Services\Inventory\InventoryService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

it('returns the original receipt without inflating inventory when replayed', function () {
    $service = app(InventoryService::class);
    $product = Product::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $actor = User::factory()->create();
    $input = new ReceiveStockInput(
        productId: $product->id,
        warehouseId: $warehouse->id,
        quantity: 7,
        sourceReference: 'supplier-delivery-001',
        idempotencyKey: 'receive-stock-001',
        actorId: $actor->id,
    );

    $firstResult = $service->receive($input);
    $replayedResult = $service->receive($input);
    $balance = InventoryBalance::query()->sole();
    $movement = InventoryMovement::query()->sole();

    expect($replayedResult)->toEqual($firstResult)
        ->and($firstResult->receivedQuantity)->toBe(7)
        ->and($firstResult->availableQuantity)->toBe(7)
        ->and($balance->available_quantity)->toBe(7)
        ->and($movement->actor_id)->toBe($actor->id)
        ->and($movement->business_reference_type)->toBe('stock_receipt')
        ->and($movement->business_reference_id)->toBe('supplier-delivery-001')
        ->and(Operation::query()->count())->toBe(1)
        ->and(InventoryMovement::query()->count())->toBe(1);
});

it('rolls back the operation, movement, and new balance when receipt persistence fails', function () {
    $service = app(InventoryService::class);
    $product = Product::factory()->create();
    $warehouse = Warehouse::factory()->create();

    expect(fn () => $service->receive(new ReceiveStockInput(
        productId: $product->id,
        warehouseId: $warehouse->id,
        quantity: 5,
        sourceReference: 'supplier-delivery-002',
        idempotencyKey: 'receive-stock-002',
        actorId: 999_999,
    )))->toThrow(QueryException::class)
        ->and(Operation::query()->doesntExist())->toBeTrue()
        ->and(InventoryMovement::query()->doesntExist())->toBeTrue()
        ->and(InventoryBalance::query()->doesntExist())->toBeTrue();
});

it('prevents a negative adjustment from consuming committed stock', function () {
    $service = app(InventoryService::class);
    $movements = app(InventoryMovementService::class);
    $product = Product::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $actor = User::factory()->create();

    $service->receive(new ReceiveStockInput(
        productId: $product->id,
        warehouseId: $warehouse->id,
        quantity: 10,
        sourceReference: 'supplier-delivery-003',
        idempotencyKey: 'receive-stock-003',
        actorId: $actor->id,
    ));

    DB::transaction(
        fn (): InventoryMovement => $movements->apply(
            Operation::factory()->create(),
            new Movement(
                productId: $product->id,
                quantity: 6,
                sourceWarehouseId: $warehouse->id,
                sourceBucket: MovementBucket::Available,
                destinationWarehouseId: $warehouse->id,
                destinationBucket: MovementBucket::Reserved,
                businessReferenceType: 'reservation',
                businessReferenceId: 'reservation-adjustment-test',
            ),
        ),
    );

    $result = $service->adjust(new AdjustInventoryInput(
        productId: $product->id,
        warehouseId: $warehouse->id,
        quantityChange: -4,
        reason: 'Remove damaged available stock',
        idempotencyKey: 'adjust-inventory-001',
        actorId: $actor->id,
    ));

    expect(fn () => $service->adjust(new AdjustInventoryInput(
        productId: $product->id,
        warehouseId: $warehouse->id,
        quantityChange: -1,
        reason: 'Attempt to consume committed stock',
        idempotencyKey: 'adjust-inventory-002',
        actorId: $actor->id,
    )))->toThrow(InsufficientSourceQuantityException::class);

    $balance = InventoryBalance::query()->sole();
    $adjustmentMovement = InventoryMovement::query()->latest('id')->firstOrFail();

    expect($result->quantityChange)->toBe(-4)
        ->and($result->availableQuantity)->toBe(0)
        ->and($balance->available_quantity)->toBe(0)
        ->and($balance->reserved_quantity)->toBe(6)
        ->and($adjustmentMovement->destination_warehouse_id)->toBeNull()
        ->and($adjustmentMovement->destination_bucket)->toBeNull()
        ->and($adjustmentMovement->actor_id)->toBe($actor->id)
        ->and($adjustmentMovement->metadata)->toBe(['reason' => 'Remove damaged available stock'])
        ->and(InventoryMovement::query()->count())->toBe(3)
        ->and(Operation::query()->count())->toBe(3);
});
