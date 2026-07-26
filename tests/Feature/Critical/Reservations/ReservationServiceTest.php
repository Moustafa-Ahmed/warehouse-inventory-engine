<?php

use App\DTOs\Reservations\ReleaseReservationInput;
use App\DTOs\Reservations\ReserveOrderItemInput;
use App\Enums\Inventory\MovementBucket;
use App\Enums\Reservations\Kind;
use App\Enums\Reservations\Status;
use App\Exceptions\InsufficientReservedQuantityException;
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

it('releases reserved inventory with an explicit demand outcome', function (
    bool $cancelOrderDemand,
    int $expectedCancelledQuantity,
    int $expectedOutstandingQuantity,
) {
    [$orderItem, $warehouse, $balance, $reservation] = releasableReservationContext(
        orderedQuantity: 10,
        availableQuantity: 4,
        reservedQuantity: 6,
    );

    $result = app(ReservationService::class)->release(new ReleaseReservationInput(
        reservationId: $reservation->id,
        quantity: 2,
        cancelOrderDemand: $cancelOrderDemand,
        reason: 'Customer changed the order',
        idempotencyKey: 'release-'.($cancelOrderDemand ? 'cancel' : 'retain').'-001',
        source: 'test',
    ));
    $movement = InventoryMovement::query()->sole();
    $transition = ReservationTransition::query()->sole();

    expect($result->releasedQuantity)->toBe(2)
        ->and($result->cancelledQuantity)->toBe($expectedCancelledQuantity)
        ->and($result->remainingReservedQuantity)->toBe(4)
        ->and($result->outstandingQuantity)->toBe($expectedOutstandingQuantity)
        ->and($balance->refresh()->available_quantity)->toBe(6)
        ->and($balance->reserved_quantity)->toBe(4)
        ->and($orderItem->refresh()->reserved_quantity)->toBe(4)
        ->and($orderItem->cancelled_quantity)->toBe($expectedCancelledQuantity)
        ->and($reservation->refresh()->reserved_quantity)->toBe(4)
        ->and($reservation->released_quantity)->toBe(2)
        ->and($reservation->status)->toBe(Status::Open)
        ->and($movement->source_bucket)->toBe(MovementBucket::Reserved)
        ->and($movement->destination_bucket)->toBe(MovementBucket::Available)
        ->and($transition->before_reserved_quantity)->toBe(6)
        ->and($transition->after_reserved_quantity)->toBe(4);
})->with([
    'release while retaining demand' => [false, 0, 6],
    'release and cancel demand' => [true, 2, 4],
]);

it('cannot release picked or packed inventory through reserved release', function () {
    [$orderItem, $warehouse, $balance, $reservation] = releasableReservationContext(
        orderedQuantity: 10,
        availableQuantity: 0,
        reservedQuantity: 2,
        pickedQuantity: 3,
        packedQuantity: 1,
    );

    expect(fn () => app(ReservationService::class)->release(new ReleaseReservationInput(
        reservationId: $reservation->id,
        quantity: 3,
        cancelOrderDemand: true,
        reason: 'Invalid committed-stage release',
        idempotencyKey: 'release-committed-001',
        source: 'test',
    )))->toThrow(InsufficientReservedQuantityException::class)
        ->and($balance->refresh()->reserved_quantity)->toBe(2)
        ->and($balance->picked_quantity)->toBe(3)
        ->and($balance->packed_quantity)->toBe(1)
        ->and($orderItem->refresh()->reserved_quantity)->toBe(2)
        ->and($orderItem->picked_quantity)->toBe(3)
        ->and($orderItem->packed_quantity)->toBe(1)
        ->and($reservation->refresh()->reserved_quantity)->toBe(2)
        ->and($reservation->picked_quantity)->toBe(3)
        ->and($reservation->packed_quantity)->toBe(1)
        ->and(InventoryMovement::query()->doesntExist())->toBeTrue()
        ->and(ReservationTransition::query()->doesntExist())->toBeTrue()
        ->and(Operation::query()->doesntExist())->toBeTrue();
});

it('replays a completed cancellation without releasing twice', function () {
    [$orderItem, $warehouse, $balance, $reservation] = releasableReservationContext(
        orderedQuantity: 5,
        availableQuantity: 0,
        reservedQuantity: 5,
    );
    $input = new ReleaseReservationInput(
        reservationId: $reservation->id,
        quantity: 5,
        cancelOrderDemand: true,
        reason: 'Cancel entire order item',
        idempotencyKey: 'release-replay-001',
        source: 'test',
    );
    $service = app(ReservationService::class);

    $result = $service->release($input);
    $replayedResult = $service->release($input);

    expect($replayedResult)->toEqual($result)
        ->and($result->cancelledQuantity)->toBe(5)
        ->and($result->outstandingQuantity)->toBe(0)
        ->and($balance->refresh()->available_quantity)->toBe(5)
        ->and($balance->reserved_quantity)->toBe(0)
        ->and($orderItem->refresh()->cancelled_quantity)->toBe(5)
        ->and($reservation->refresh()->status)->toBe(Status::Released)
        ->and($reservation->released_quantity)->toBe(5)
        ->and(InventoryMovement::query()->count())->toBe(1)
        ->and(ReservationTransition::query()->count())->toBe(1)
        ->and(Operation::query()->count())->toBe(1);
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

/**
 * @return array{OrderItem, Warehouse, InventoryBalance, Reservation}
 */
function releasableReservationContext(
    int $orderedQuantity,
    int $availableQuantity,
    int $reservedQuantity,
    int $pickedQuantity = 0,
    int $packedQuantity = 0,
): array {
    $product = Product::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $orderItem = OrderItem::factory()
        ->for($product)
        ->create([
            'ordered_quantity' => $orderedQuantity,
            'cancelled_quantity' => 0,
            'reserved_quantity' => $reservedQuantity,
            'picked_quantity' => $pickedQuantity,
            'packed_quantity' => $packedQuantity,
            'shipped_quantity' => 0,
            'delivered_quantity' => 0,
        ]);
    $balance = InventoryBalance::factory()
        ->for($product)
        ->for($warehouse)
        ->create([
            'available_quantity' => $availableQuantity,
            'reserved_quantity' => $reservedQuantity,
            'picked_quantity' => $pickedQuantity,
            'packed_quantity' => $packedQuantity,
        ]);
    $reservation = Reservation::factory()
        ->for($orderItem)
        ->for($warehouse)
        ->create([
            'kind' => Kind::Confirmed,
            'status' => Status::Open,
            'requested_quantity' => $orderedQuantity,
            'reserved_quantity' => $reservedQuantity,
            'picked_quantity' => $pickedQuantity,
            'packed_quantity' => $packedQuantity,
            'shipped_quantity' => 0,
            'released_quantity' => 0,
        ]);

    return [$orderItem, $warehouse, $balance, $reservation];
}
