<?php

use App\DTOs\Reservations\ReserveOrderItemInput;
use App\Enums\Inventory\MovementBucket;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\Operation;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Reservation;
use App\Models\ReservationTransition;
use App\Models\Warehouse;
use App\Services\Reservations\ReservationService;

it('allocates available inventory and reports the partial result explicitly', function () {
    [$orderItem, $warehouse, $balance] = reservationContext(10, 6);

    $result = app(ReservationService::class)->reserve(new ReserveOrderItemInput(
        orderItemId: $orderItem->id,
        warehouseId: $warehouse->id,
        idempotencyKey: 'reserve-partial-001',
        source: 'test',
    ));
    $movement = InventoryMovement::query()->sole();
    $transition = ReservationTransition::query()->sole();

    expect($result->requestedQuantity)->toBe(10)
        ->and($result->allocatedQuantity)->toBe(6)
        ->and($result->outstandingQuantity)->toBe(4)
        ->and($result->fullyAllocated)->toBeFalse()
        ->and($result->reservationId)->not->toBeNull()
        ->and($balance->refresh()->available_quantity)->toBe(0)
        ->and($balance->reserved_quantity)->toBe(6)
        ->and($orderItem->refresh()->reserved_quantity)->toBe(6)
        ->and($movement->source_bucket)->toBe(MovementBucket::Available)
        ->and($movement->destination_bucket)->toBe(MovementBucket::Reserved)
        ->and($movement->quantity)->toBe(6)
        ->and($transition->before_reserved_quantity)->toBe(0)
        ->and($transition->after_reserved_quantity)->toBe(6);
});

it('records zero allocation without creating a reservation or movement', function () {
    [$orderItem, $warehouse, $balance] = reservationContext(5, 0);

    $result = app(ReservationService::class)->reserve(new ReserveOrderItemInput(
        orderItemId: $orderItem->id,
        warehouseId: $warehouse->id,
        idempotencyKey: 'reserve-zero-001',
        source: 'test',
    ));

    expect($result->requestedQuantity)->toBe(5)
        ->and($result->allocatedQuantity)->toBe(0)
        ->and($result->outstandingQuantity)->toBe(5)
        ->and($result->fullyAllocated)->toBeFalse()
        ->and($result->reservationId)->toBeNull()
        ->and($balance->refresh()->available_quantity)->toBe(0)
        ->and($orderItem->refresh()->reserved_quantity)->toBe(0)
        ->and(Reservation::query()->doesntExist())->toBeTrue()
        ->and(ReservationTransition::query()->doesntExist())->toBeTrue()
        ->and(InventoryMovement::query()->doesntExist())->toBeTrue()
        ->and(Operation::query()->count())->toBe(1);
});

it('replays a completed full reservation without allocating twice', function () {
    [$orderItem, $warehouse, $balance] = reservationContext(5, 5);
    $input = new ReserveOrderItemInput(
        orderItemId: $orderItem->id,
        warehouseId: $warehouse->id,
        idempotencyKey: 'reserve-replay-001',
        source: 'test',
    );
    $service = app(ReservationService::class);

    $result = $service->reserve($input);
    $replayedResult = $service->reserve($input);

    expect($replayedResult)->toEqual($result)
        ->and($result->allocatedQuantity)->toBe(5)
        ->and($result->outstandingQuantity)->toBe(0)
        ->and($result->fullyAllocated)->toBeTrue()
        ->and($balance->refresh()->reserved_quantity)->toBe(5)
        ->and($orderItem->refresh()->reserved_quantity)->toBe(5)
        ->and(Reservation::query()->count())->toBe(1)
        ->and(ReservationTransition::query()->count())->toBe(1)
        ->and(InventoryMovement::query()->count())->toBe(1)
        ->and(Operation::query()->count())->toBe(1);
});

it('rolls back every reservation effect when history persistence fails', function () {
    [$orderItem, $warehouse, $balance] = reservationContext(5, 5);
    ReservationTransition::creating(function (): never {
        throw new RuntimeException('Injected history failure.');
    });

    try {
        expect(fn () => app(ReservationService::class)->reserve(new ReserveOrderItemInput(
            orderItemId: $orderItem->id,
            warehouseId: $warehouse->id,
            idempotencyKey: 'reserve-rollback-001',
            source: 'test',
        )))->toThrow(RuntimeException::class, 'Injected history failure.');
    } finally {
        ReservationTransition::flushEventListeners();
    }

    expect($balance->refresh()->available_quantity)->toBe(5)
        ->and($balance->reserved_quantity)->toBe(0)
        ->and($orderItem->refresh()->reserved_quantity)->toBe(0)
        ->and(Reservation::query()->doesntExist())->toBeTrue()
        ->and(ReservationTransition::query()->doesntExist())->toBeTrue()
        ->and(InventoryMovement::query()->doesntExist())->toBeTrue()
        ->and(Operation::query()->doesntExist())->toBeTrue();
});

/**
 * @return array{OrderItem, Warehouse, InventoryBalance}
 */
function reservationContext(
    int $orderedQuantity,
    int $availableQuantity,
): array {
    $product = Product::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $orderItem = OrderItem::factory()
        ->for($product)
        ->outstanding($orderedQuantity)
        ->create();
    $balance = InventoryBalance::factory()
        ->for($product)
        ->for($warehouse)
        ->create(['available_quantity' => $availableQuantity]);

    return [$orderItem, $warehouse, $balance];
}
