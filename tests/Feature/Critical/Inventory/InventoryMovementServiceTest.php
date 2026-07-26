<?php

use App\DTOs\Inventory\Movement;
use App\Enums\Inventory\MovementBucket;
use App\Exceptions\InsufficientSourceQuantityException;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\Operation;
use App\Models\Product;
use App\Models\Warehouse;
use App\Services\Inventory\InventoryMovementService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

it('keeps projection buckets consistent with internal and external movements', function () {
    $service = app(InventoryMovementService::class);
    $product = Product::factory()->create();
    $warehouse = Warehouse::factory()->create();

    DB::transaction(function () use ($service, $product, $warehouse): void {
        $service->apply(
            Operation::factory()->create(),
            new Movement(
                productId: $product->id,
                quantity: 10,
                sourceWarehouseId: null,
                sourceBucket: null,
                destinationWarehouseId: $warehouse->id,
                destinationBucket: MovementBucket::Available,
                businessReferenceType: 'stock_receipt',
                businessReferenceId: 'receipt-001',
            ),
        );

        $service->apply(
            Operation::factory()->create(),
            new Movement(
                productId: $product->id,
                quantity: 4,
                sourceWarehouseId: $warehouse->id,
                sourceBucket: MovementBucket::Available,
                destinationWarehouseId: $warehouse->id,
                destinationBucket: MovementBucket::Reserved,
                businessReferenceType: 'reservation',
                businessReferenceId: 'reservation-001',
            ),
        );

        $service->apply(
            Operation::factory()->create(),
            new Movement(
                productId: $product->id,
                quantity: 2,
                sourceWarehouseId: $warehouse->id,
                sourceBucket: MovementBucket::Reserved,
                destinationWarehouseId: $warehouse->id,
                destinationBucket: MovementBucket::Picked,
                businessReferenceType: 'pick',
                businessReferenceId: 'pick-001',
            ),
        );

        $service->apply(
            Operation::factory()->create(),
            new Movement(
                productId: $product->id,
                quantity: 2,
                sourceWarehouseId: $warehouse->id,
                sourceBucket: MovementBucket::Picked,
                destinationWarehouseId: $warehouse->id,
                destinationBucket: MovementBucket::Packed,
                businessReferenceType: 'pack',
                businessReferenceId: 'pack-001',
            ),
        );

        $service->apply(
            Operation::factory()->create(),
            new Movement(
                productId: $product->id,
                quantity: 2,
                sourceWarehouseId: $warehouse->id,
                sourceBucket: MovementBucket::Packed,
                destinationWarehouseId: null,
                destinationBucket: MovementBucket::Shipped,
                businessReferenceType: 'shipment',
                businessReferenceId: 'shipment-001',
            ),
        );
    });

    $balance = InventoryBalance::query()->sole();

    expect($balance->available_quantity)->toBe(6)
        ->and($balance->reserved_quantity)->toBe(2)
        ->and($balance->picked_quantity)->toBe(0)
        ->and($balance->packed_quantity)->toBe(0)
        ->and(
            $balance->available_quantity
            + $balance->reserved_quantity
            + $balance->picked_quantity
            + $balance->packed_quantity
        )->toBe(8)
        ->and(InventoryMovement::query()->count())->toBe(5)
        ->and(InventoryMovement::query()->latest('id')->value('destination_bucket'))
        ->toBe(MovementBucket::Shipped);
});

it('rejects a movement that exceeds its locked source bucket', function () {
    $service = app(InventoryMovementService::class);
    $product = Product::factory()->create();
    $warehouse = Warehouse::factory()->create();

    DB::transaction(
        fn (): InventoryMovement => $service->apply(
            Operation::factory()->create(),
            new Movement(
                productId: $product->id,
                quantity: 3,
                sourceWarehouseId: null,
                sourceBucket: null,
                destinationWarehouseId: $warehouse->id,
                destinationBucket: MovementBucket::Available,
                businessReferenceType: 'stock_receipt',
                businessReferenceId: 'receipt-002',
            ),
        ),
    );

    expect(fn () => DB::transaction(
        fn (): InventoryMovement => $service->apply(
            Operation::factory()->create(),
            new Movement(
                productId: $product->id,
                quantity: 4,
                sourceWarehouseId: $warehouse->id,
                sourceBucket: MovementBucket::Available,
                destinationWarehouseId: $warehouse->id,
                destinationBucket: MovementBucket::Reserved,
                businessReferenceType: 'reservation',
                businessReferenceId: 'reservation-002',
            ),
        ),
    ))->toThrow(InsufficientSourceQuantityException::class);

    $balance = InventoryBalance::query()->sole();

    expect($balance->available_quantity)->toBe(3)
        ->and($balance->reserved_quantity)->toBe(0)
        ->and(InventoryMovement::query()->count())->toBe(1);
});

it('rolls back the movement and projection when the caller transaction fails', function () {
    $service = app(InventoryMovementService::class);
    $product = Product::factory()->create();
    $warehouse = Warehouse::factory()->create();

    DB::transaction(
        fn (): InventoryMovement => $service->apply(
            Operation::factory()->create(),
            new Movement(
                productId: $product->id,
                quantity: 10,
                sourceWarehouseId: null,
                sourceBucket: null,
                destinationWarehouseId: $warehouse->id,
                destinationBucket: MovementBucket::Available,
                businessReferenceType: 'stock_receipt',
                businessReferenceId: 'receipt-003',
            ),
        ),
    );

    expect(fn () => DB::transaction(function () use ($service, $product, $warehouse): void {
        $service->apply(
            Operation::factory()->create(),
            new Movement(
                productId: $product->id,
                quantity: 4,
                sourceWarehouseId: $warehouse->id,
                sourceBucket: MovementBucket::Available,
                destinationWarehouseId: $warehouse->id,
                destinationBucket: MovementBucket::Reserved,
                businessReferenceType: 'reservation',
                businessReferenceId: 'reservation-003',
            ),
        );

        throw new RuntimeException('Injected caller failure.');
    }))->toThrow(RuntimeException::class);

    $balance = InventoryBalance::query()->sole();

    expect($balance->available_quantity)->toBe(10)
        ->and($balance->reserved_quantity)->toBe(0)
        ->and(InventoryMovement::query()->count())->toBe(1);
});

it('locks every affected balance in ascending balance id order', function () {
    $service = app(InventoryMovementService::class);
    $product = Product::factory()->create();
    $sourceWarehouse = Warehouse::factory()->create();
    $destinationWarehouse = Warehouse::factory()->create();
    $destinationBalance = InventoryBalance::factory()
        ->for($product)
        ->for($destinationWarehouse)
        ->create();
    $sourceBalance = InventoryBalance::factory()
        ->for($product)
        ->for($sourceWarehouse)
        ->create();

    DB::transaction(
        fn (): InventoryMovement => $service->apply(
            Operation::factory()->create(),
            new Movement(
                productId: $product->id,
                quantity: 5,
                sourceWarehouseId: null,
                sourceBucket: null,
                destinationWarehouseId: $sourceWarehouse->id,
                destinationBucket: MovementBucket::Available,
                businessReferenceType: 'stock_receipt',
                businessReferenceId: 'receipt-004',
            ),
        ),
    );

    DB::flushQueryLog();
    DB::enableQueryLog();

    DB::transaction(
        fn (): InventoryMovement => $service->apply(
            Operation::factory()->create(),
            new Movement(
                productId: $product->id,
                quantity: 2,
                sourceWarehouseId: $sourceWarehouse->id,
                sourceBucket: MovementBucket::Available,
                destinationWarehouseId: $destinationWarehouse->id,
                destinationBucket: MovementBucket::Available,
                businessReferenceType: 'warehouse_transfer',
                businessReferenceId: 'transfer-001',
            ),
        ),
    );

    $lockQuery = collect(DB::getQueryLog())->first(function (array $query): bool {
        $sql = Str::lower($query['query']);

        return Str::contains($sql, ['inventory_balances', 'for update'])
            && Str::contains($sql, 'order by');
    });

    DB::disableQueryLog();

    expect($sourceBalance->id)->toBeGreaterThan($destinationBalance->id)
        ->and($lockQuery)->not->toBeNull()
        ->and(Str::lower($lockQuery['query']))->toContain('order by `id` asc')
        ->and(Str::lower($lockQuery['query']))->toContain('for update')
        ->and($sourceBalance->refresh()->available_quantity)->toBe(3)
        ->and($destinationBalance->refresh()->available_quantity)->toBe(2);
});

it('requires at least one warehouse endpoint', function () {
    $service = app(InventoryMovementService::class);
    $product = Product::factory()->create();

    expect(fn () => DB::transaction(
        fn (): InventoryMovement => $service->apply(
            Operation::factory()->create(),
            new Movement(
                productId: $product->id,
                quantity: 1,
                sourceWarehouseId: null,
                sourceBucket: null,
                destinationWarehouseId: null,
                destinationBucket: MovementBucket::Shipped,
                businessReferenceType: 'invalid_external_movement',
                businessReferenceId: 'invalid-external-movement',
            ),
        ),
    ))->toThrow(
        InvalidArgumentException::class,
        'An inventory movement requires at least one warehouse endpoint.',
    );
});

it('rejects malformed movement endpoints at the database boundary', function (
    ?string $sourceWarehouse,
    ?string $sourceBucket,
    ?string $destinationWarehouse,
    ?string $destinationBucket,
) {
    $operation = Operation::factory()->create();
    $product = Product::factory()->create();
    $warehouse = Warehouse::factory()->create();

    expect(fn () => DB::table('inventory_movements')->insert([
        'operation_id' => $operation->id,
        'product_id' => $product->id,
        'source_warehouse_id' => $sourceWarehouse === 'warehouse'
            ? $warehouse->id
            : null,
        'source_bucket' => $sourceBucket,
        'destination_warehouse_id' => $destinationWarehouse === 'warehouse'
            ? $warehouse->id
            : null,
        'destination_bucket' => $destinationBucket,
        'quantity' => 1,
        'business_reference_type' => 'database_constraint_test',
        'business_reference_id' => (string) Str::uuid(),
        'created_at' => now(),
    ]))->toThrow(QueryException::class);
})->with([
    'source warehouse without bucket' => ['warehouse', null, null, null],
    'source shipped bucket inside warehouse' => ['warehouse', 'shipped', null, null],
    'destination warehouse without bucket' => [null, null, 'warehouse', null],
    'destination mutable bucket without warehouse' => [null, null, null, 'available'],
    'destination shipped bucket inside warehouse' => [null, null, 'warehouse', 'shipped'],
    'external source to external shipped destination' => [null, null, null, 'shipped'],
    'no endpoint' => [null, null, null, null],
]);
