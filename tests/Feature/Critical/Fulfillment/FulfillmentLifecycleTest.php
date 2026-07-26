<?php

use App\DTOs\Fulfillment\PackReservationInput;
use App\DTOs\Fulfillment\PickReservationInput;
use App\DTOs\Fulfillment\ReturnPickedInventoryInput;
use App\DTOs\Fulfillment\UnpackReservationInput;
use App\DTOs\Reservations\ReleaseReservationInput;
use App\DTOs\Reservations\ReserveOrderItemInput;
use App\DTOs\Shipping\CreateShipmentInput;
use App\DTOs\Shipping\CreateShipmentItemInput;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\Operation;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Reservation;
use App\Models\ReservationTransition;
use App\Models\Shipment;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Fulfillment\FulfillmentService;
use App\Services\Orders\OrderItemProgressCalculator;
use App\Services\Reservations\ReservationService;
use App\Services\Shipping\ShipmentService;
use Illuminate\Support\Facades\Event;

it('conserves quantity through fulfillment, reversals, and shipment preparation', function () {
    $product = Product::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $actor = User::factory()->create();
    $orderItem = OrderItem::factory()
        ->for($product)
        ->outstanding(10)
        ->create();
    $balance = InventoryBalance::factory()
        ->for($product)
        ->for($warehouse)
        ->create(['available_quantity' => 10]);
    $reservations = app(ReservationService::class);
    $fulfillment = app(FulfillmentService::class);
    $reservationResult = $reservations->reserve(new ReserveOrderItemInput(
        orderItemId: $orderItem->id,
        warehouseId: $warehouse->id,
        idempotencyKey: 'lifecycle-reserve',
        source: 'lifecycle_test',
    ));
    $reservation = Reservation::query()->findOrFail($reservationResult->reservationId);

    expectOrderItemConservation($orderItem);

    $reservations->release(new ReleaseReservationInput(
        reservationId: $reservation->id,
        quantity: 2,
        cancelOrderDemand: true,
        reason: 'Customer cancelled two units',
        idempotencyKey: 'lifecycle-cancel',
        actorId: $actor->id,
        source: 'lifecycle_test',
    ));
    expectOrderItemConservation($orderItem);

    $fulfillment->pick(new PickReservationInput(
        reservationId: $reservation->id,
        quantity: 6,
        idempotencyKey: 'lifecycle-pick',
        actorId: $actor->id,
        source: 'lifecycle_test',
    ));
    expectOrderItemConservation($orderItem);

    $fulfillment->pack(new PackReservationInput(
        reservationId: $reservation->id,
        quantity: 4,
        idempotencyKey: 'lifecycle-pack',
        actorId: $actor->id,
        source: 'lifecycle_test',
    ));
    expectOrderItemConservation($orderItem);

    $fulfillment->unpack(new UnpackReservationInput(
        reservationId: $reservation->id,
        quantity: 1,
        reason: 'Correct packing label',
        idempotencyKey: 'lifecycle-unpack',
        actorId: $actor->id,
        source: 'lifecycle_test',
    ));
    expectOrderItemConservation($orderItem);

    $fulfillment->returnPicked(new ReturnPickedInventoryInput(
        reservationId: $reservation->id,
        quantity: 1,
        reason: 'Return one inspected unit to stock',
        idempotencyKey: 'lifecycle-return-picked',
        actorId: $actor->id,
        source: 'lifecycle_test',
    ));
    expectOrderItemConservation($orderItem);

    $beforeFailedPack = [
        'order_item' => orderItemQuantities($orderItem),
        'reservation' => reservationQuantities($reservation),
        'balance' => balanceQuantities($balance),
        'movements' => InventoryMovement::query()->count(),
        'transitions' => ReservationTransition::query()->count(),
        'operations' => Operation::query()->count(),
    ];
    $transitionEvent = 'eloquent.creating: '.ReservationTransition::class;
    Event::listen(
        $transitionEvent,
        fn (): never => throw new RuntimeException('Injected fulfillment history failure.'),
    );

    try {
        expect(fn () => $fulfillment->pack(new PackReservationInput(
            reservationId: $reservation->id,
            quantity: 1,
            idempotencyKey: 'lifecycle-pack-rollback',
            actorId: $actor->id,
            source: 'lifecycle_test',
        )))->toThrow(RuntimeException::class, 'Injected fulfillment history failure.');
    } finally {
        Event::forget($transitionEvent);
    }

    expect(orderItemQuantities($orderItem))->toBe($beforeFailedPack['order_item'])
        ->and(reservationQuantities($reservation))->toBe($beforeFailedPack['reservation'])
        ->and(balanceQuantities($balance))->toBe($beforeFailedPack['balance'])
        ->and(InventoryMovement::query()->count())->toBe($beforeFailedPack['movements'])
        ->and(ReservationTransition::query()->count())->toBe($beforeFailedPack['transitions'])
        ->and(Operation::query()->count())->toBe($beforeFailedPack['operations']);
    expectOrderItemConservation($orderItem);

    $shipmentResult = app(ShipmentService::class)->create(new CreateShipmentInput(
        orderId: $orderItem->order_id,
        warehouseId: $warehouse->id,
        items: [new CreateShipmentItemInput($reservation->id, 2)],
        idempotencyKey: 'lifecycle-create-shipment',
    ));
    $shipment = Shipment::query()->findOrFail($shipmentResult->shipmentId);

    expectOrderItemConservation($orderItem);

    expect($orderItem->refresh()->cancelled_quantity)->toBe(2)
        ->and($orderItem->reserved_quantity)->toBe(2)
        ->and($orderItem->picked_quantity)->toBe(2)
        ->and($orderItem->packed_quantity)->toBe(3)
        ->and($orderItem->shipped_quantity)->toBe(0)
        ->and($reservation->refresh()->released_quantity)->toBe(3)
        ->and($balance->refresh()->available_quantity)->toBe(3)
        ->and($balance->reserved_quantity)->toBe(2)
        ->and($balance->picked_quantity)->toBe(2)
        ->and($balance->packed_quantity)->toBe(3)
        ->and($shipment->items()->sole()->quantity)->toBe(2)
        ->and($shipment->items()->sole()->delivered_quantity)->toBe(0)
        ->and(InventoryMovement::query()->count())->toBe(6)
        ->and(ReservationTransition::query()->count())->toBe(6)
        ->and(Operation::query()->count())->toBe(7);
});

function expectOrderItemConservation(OrderItem $orderItem): void
{
    $orderItem->refresh();
    $progress = app(OrderItemProgressCalculator::class)->calculate(
        orderedQuantity: $orderItem->ordered_quantity,
        cancelledQuantity: $orderItem->cancelled_quantity,
        reservedQuantity: $orderItem->reserved_quantity,
        pickedQuantity: $orderItem->picked_quantity,
        packedQuantity: $orderItem->packed_quantity,
        shippedQuantity: $orderItem->shipped_quantity,
        deliveredQuantity: $orderItem->delivered_quantity,
    );

    expect(
        $progress->cancelledQuantity
        + $progress->outstandingQuantity
        + $progress->reservedQuantity
        + $progress->pickedQuantity
        + $progress->packedQuantity
        + $progress->shippedQuantity
    )->toBe($progress->orderedQuantity);
}

/**
 * @return array<string, int>
 */
function orderItemQuantities(OrderItem $orderItem): array
{
    $orderItem->refresh();

    return $orderItem->only([
        'ordered_quantity',
        'cancelled_quantity',
        'reserved_quantity',
        'picked_quantity',
        'packed_quantity',
        'shipped_quantity',
        'delivered_quantity',
    ]);
}

/**
 * @return array<string, int>
 */
function reservationQuantities(Reservation $reservation): array
{
    $reservation->refresh();

    return $reservation->only([
        'reserved_quantity',
        'picked_quantity',
        'packed_quantity',
        'shipped_quantity',
        'released_quantity',
    ]);
}

/**
 * @return array<string, int>
 */
function balanceQuantities(InventoryBalance $balance): array
{
    $balance->refresh();

    return $balance->only([
        'available_quantity',
        'reserved_quantity',
        'picked_quantity',
        'packed_quantity',
    ]);
}
