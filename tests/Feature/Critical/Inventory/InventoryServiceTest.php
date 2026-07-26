<?php

use App\DTOs\Inventory\AdjustInventoryInput;
use App\DTOs\Inventory\Movement;
use App\DTOs\Inventory\ReceiveStockInput;
use App\DTOs\Inventory\TransferStockInput;
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
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Facades\DB;
use Tests\Support\ConcurrentStockTransfer;

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

it('transfers only available stock and leaves committed buckets untouched', function () {
    $service = app(InventoryService::class);
    $movements = app(InventoryMovementService::class);
    $product = Product::factory()->create();
    $sourceWarehouse = Warehouse::factory()->create();
    $destinationWarehouse = Warehouse::factory()->create();
    $destinationBalance = InventoryBalance::factory()
        ->for($product)
        ->for($destinationWarehouse)
        ->create();

    $service->receive(new ReceiveStockInput(
        productId: $product->id,
        warehouseId: $sourceWarehouse->id,
        quantity: 10,
        sourceReference: 'supplier-delivery-004',
        idempotencyKey: 'receive-stock-004',
    ));

    DB::transaction(
        fn (): InventoryMovement => $movements->apply(
            Operation::factory()->create(),
            new Movement(
                productId: $product->id,
                quantity: 6,
                sourceWarehouseId: $sourceWarehouse->id,
                sourceBucket: MovementBucket::Available,
                destinationWarehouseId: $sourceWarehouse->id,
                destinationBucket: MovementBucket::Reserved,
                businessReferenceType: 'reservation',
                businessReferenceId: 'reservation-transfer-test',
            ),
        ),
    );

    expect(fn () => $service->transfer(new TransferStockInput(
        productId: $product->id,
        sourceWarehouseId: $sourceWarehouse->id,
        destinationWarehouseId: $destinationWarehouse->id,
        quantity: 5,
        idempotencyKey: 'transfer-stock-001',
    )))->toThrow(InsufficientSourceQuantityException::class);

    $result = $service->transfer(new TransferStockInput(
        productId: $product->id,
        sourceWarehouseId: $sourceWarehouse->id,
        destinationWarehouseId: $destinationWarehouse->id,
        quantity: 4,
        idempotencyKey: 'transfer-stock-002',
    ));
    $sourceBalance = InventoryBalance::query()
        ->where('warehouse_id', $sourceWarehouse->id)
        ->sole();

    expect($result->sourceAvailableQuantity)->toBe(0)
        ->and($result->destinationAvailableQuantity)->toBe(4)
        ->and($sourceBalance->available_quantity)->toBe(0)
        ->and($sourceBalance->reserved_quantity)->toBe(6)
        ->and($destinationBalance->refresh()->available_quantity)->toBe(4)
        ->and(InventoryMovement::query()->count())->toBe(3)
        ->and(Operation::query()->count())->toBe(3);
});

it('completes concurrent opposite-direction transfers without corrupting balances', function () {
    $service = app(InventoryService::class);
    $product = Product::factory()->create();
    $firstWarehouse = Warehouse::factory()->create();
    $secondWarehouse = Warehouse::factory()->create();

    $service->receive(new ReceiveStockInput(
        productId: $product->id,
        warehouseId: $firstWarehouse->id,
        quantity: 10,
        sourceReference: 'supplier-delivery-005',
        idempotencyKey: 'receive-stock-005',
    ));
    $service->receive(new ReceiveStockInput(
        productId: $product->id,
        warehouseId: $secondWarehouse->id,
        quantity: 10,
        sourceReference: 'supplier-delivery-006',
        idempotencyKey: 'receive-stock-006',
    ));

    $results = Concurrency::run([
        ConcurrentStockTransfer::make(
            $product->id,
            $firstWarehouse->id,
            $secondWarehouse->id,
            3,
            'opposite-transfer-001',
        ),
        ConcurrentStockTransfer::make(
            $product->id,
            $secondWarehouse->id,
            $firstWarehouse->id,
            4,
            'opposite-transfer-002',
        ),
    ]);

    $balances = InventoryBalance::query()
        ->where('product_id', $product->id)
        ->get()
        ->keyBy('warehouse_id');

    expect($results)->toHaveCount(2)
        ->and($balances->get($firstWarehouse->id)->available_quantity)->toBe(11)
        ->and($balances->get($secondWarehouse->id)->available_quantity)->toBe(9)
        ->and(InventoryMovement::query()->count())->toBe(4)
        ->and(Operation::query()->count())->toBe(4);
});
