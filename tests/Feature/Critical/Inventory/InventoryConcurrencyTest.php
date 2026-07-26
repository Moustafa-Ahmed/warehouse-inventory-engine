<?php

use App\DTOs\Inventory\ReceiveStockInput;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\Operation;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Inventory\InventoryService;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Facades\Event;
use Tests\Support\ConcurrentAvailableAdjustment;

/**
 * Child processes use independent MySQL connections so this test exercises actual row-lock contention.
 */
it('allows only one concurrent operation to consume the final available unit', function () {
    $service = app(InventoryService::class);
    $product = Product::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $actor = User::factory()->create();

    $service->receive(new ReceiveStockInput(
        productId: $product->id,
        warehouseId: $warehouse->id,
        quantity: 1,
        sourceReference: 'final-unit-receipt',
        idempotencyKey: 'final-unit-receipt',
        actorId: $actor->id,
    ));

    $outcomes = Concurrency::run([
        ConcurrentAvailableAdjustment::make(
            $product->id,
            $warehouse->id,
            $actor->id,
            'final-unit-adjustment-001',
        ),
        ConcurrentAvailableAdjustment::make(
            $product->id,
            $warehouse->id,
            $actor->id,
            'final-unit-adjustment-002',
        ),
    ]);
    sort($outcomes);

    expect($outcomes)->toBe(['rejected', 'succeeded'])
        ->and(InventoryBalance::query()->sole()->available_quantity)->toBe(0)
        ->and(InventoryMovement::query()->count())->toBe(2)
        ->and(Operation::query()->count())->toBe(2);
});

it('rolls back a ledger insert when projection persistence fails', function () {
    $service = app(InventoryService::class);
    $product = Product::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $eventName = 'eloquent.updating: '.InventoryBalance::class;

    Event::listen(
        $eventName,
        fn (): never => throw new RuntimeException('Injected projection failure.'),
    );

    try {
        expect(fn () => $service->receive(new ReceiveStockInput(
            productId: $product->id,
            warehouseId: $warehouse->id,
            quantity: 5,
            sourceReference: 'rollback-between-ledger-and-projection',
            idempotencyKey: 'rollback-between-ledger-and-projection',
        )))->toThrow(RuntimeException::class);
    } finally {
        Event::forget($eventName);
    }

    expect(Operation::query()->doesntExist())->toBeTrue()
        ->and(InventoryMovement::query()->doesntExist())->toBeTrue()
        ->and(InventoryBalance::query()->doesntExist())->toBeTrue();
});
