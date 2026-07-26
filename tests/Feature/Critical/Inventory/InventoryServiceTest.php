<?php

use App\DTOs\Inventory\ReceiveStockInput;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\Operation;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Inventory\InventoryService;
use Illuminate\Database\QueryException;

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
