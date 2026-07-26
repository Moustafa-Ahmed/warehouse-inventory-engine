<?php

use App\DTOs\Fulfillment\UnpackReservationInput;
use App\Enums\Inventory\MovementBucket;
use App\Enums\Reservations\Kind;
use App\Enums\Reservations\Status;
use App\Enums\Shipments\Status as ShipmentStatus;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\Operation;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Reservation;
use App\Models\ReservationTransition;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use App\Models\Warehouse;
use App\Services\Fulfillment\FulfillmentService;

it('unpacks only packed inventory not assigned to a pending shipment', function () {
    $product = Product::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $orderItem = OrderItem::factory()
        ->for($product)
        ->create([
            'ordered_quantity' => 6,
            'packed_quantity' => 6,
        ]);
    $reservation = Reservation::factory()
        ->for($orderItem)
        ->for($warehouse)
        ->create([
            'kind' => Kind::Confirmed,
            'status' => Status::Open,
            'requested_quantity' => 6,
            'reserved_quantity' => 0,
            'picked_quantity' => 0,
            'packed_quantity' => 6,
            'shipped_quantity' => 0,
            'released_quantity' => 0,
        ]);
    $balance = InventoryBalance::factory()
        ->for($product)
        ->for($warehouse)
        ->create(['packed_quantity' => 6]);
    $shipment = Shipment::factory()
        ->for($orderItem->order)
        ->for($warehouse)
        ->create(['status' => ShipmentStatus::PendingHandoff]);
    ShipmentItem::factory()
        ->for($shipment)
        ->for($reservation)
        ->create(['quantity' => 4]);
    $service = app(FulfillmentService::class);

    expect(fn () => $service->unpack(new UnpackReservationInput(
        reservationId: $reservation->id,
        quantity: 3,
        reason: 'Attempt to unpack assigned stock',
        idempotencyKey: 'unpack-assigned-001',
        source: 'test',
    )))->toThrow(InvalidArgumentException::class)
        ->and($reservation->refresh()->packed_quantity)->toBe(6)
        ->and($balance->refresh()->packed_quantity)->toBe(6)
        ->and(InventoryMovement::query()->doesntExist())->toBeTrue()
        ->and(Operation::query()->doesntExist())->toBeTrue();

    $input = new UnpackReservationInput(
        reservationId: $reservation->id,
        quantity: 2,
        reason: 'Repack items with corrected labels',
        idempotencyKey: 'unpack-eligible-001',
        source: 'test',
    );
    $result = $service->unpack($input);
    $replayedResult = $service->unpack($input);
    $movement = InventoryMovement::query()->sole();

    expect($replayedResult)->toEqual($result)
        ->and($result->unpackedQuantity)->toBe(2)
        ->and($result->remainingPackedQuantity)->toBe(4)
        ->and($result->totalPickedQuantity)->toBe(2)
        ->and($reservation->refresh()->packed_quantity)->toBe(4)
        ->and($reservation->picked_quantity)->toBe(2)
        ->and($orderItem->refresh()->packed_quantity)->toBe(4)
        ->and($orderItem->picked_quantity)->toBe(2)
        ->and($balance->refresh()->packed_quantity)->toBe(4)
        ->and($balance->picked_quantity)->toBe(2)
        ->and($movement->source_bucket)->toBe(MovementBucket::Packed)
        ->and($movement->destination_bucket)->toBe(MovementBucket::Picked)
        ->and(ReservationTransition::query()->count())->toBe(1)
        ->and(Operation::query()->count())->toBe(1);
});
