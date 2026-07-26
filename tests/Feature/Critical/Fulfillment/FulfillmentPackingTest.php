<?php

use App\DTOs\Fulfillment\PackReservationInput;
use App\Enums\Inventory\MovementBucket;
use App\Enums\Reservations\Kind;
use App\Enums\Reservations\Status;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\Operation;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Reservation;
use App\Models\ReservationTransition;
use App\Models\Warehouse;
use App\Services\Fulfillment\FulfillmentService;

it('packs only the reservation quantity that is currently picked', function () {
    $product = Product::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $orderItem = OrderItem::factory()
        ->for($product)
        ->create([
            'ordered_quantity' => 5,
            'picked_quantity' => 5,
        ]);
    $reservation = Reservation::factory()
        ->for($orderItem)
        ->for($warehouse)
        ->create([
            'kind' => Kind::Confirmed,
            'status' => Status::Open,
            'requested_quantity' => 5,
            'reserved_quantity' => 0,
            'picked_quantity' => 5,
            'packed_quantity' => 0,
            'shipped_quantity' => 0,
            'released_quantity' => 0,
        ]);
    $balance = InventoryBalance::factory()
        ->for($product)
        ->for($warehouse)
        ->create(['picked_quantity' => 5]);
    $input = new PackReservationInput(
        reservationId: $reservation->id,
        quantity: 3,
        idempotencyKey: 'pack-reservation-001',
        source: 'test',
    );
    $service = app(FulfillmentService::class);

    $result = $service->pack($input);
    $replayedResult = $service->pack($input);
    $movement = InventoryMovement::query()->sole();

    expect($replayedResult)->toEqual($result)
        ->and($result->packedQuantity)->toBe(3)
        ->and($result->remainingPickedQuantity)->toBe(2)
        ->and($result->totalPackedQuantity)->toBe(3)
        ->and($reservation->refresh()->picked_quantity)->toBe(2)
        ->and($reservation->packed_quantity)->toBe(3)
        ->and($orderItem->refresh()->picked_quantity)->toBe(2)
        ->and($orderItem->packed_quantity)->toBe(3)
        ->and($balance->refresh()->picked_quantity)->toBe(2)
        ->and($balance->packed_quantity)->toBe(3)
        ->and($movement->source_bucket)->toBe(MovementBucket::Picked)
        ->and($movement->destination_bucket)->toBe(MovementBucket::Packed)
        ->and(ReservationTransition::query()->count())->toBe(1)
        ->and(Operation::query()->count())->toBe(1);

    expect(fn () => $service->pack(new PackReservationInput(
        reservationId: $reservation->id,
        quantity: 3,
        idempotencyKey: 'pack-reservation-exceeds-picked',
        source: 'test',
    )))->toThrow(InvalidArgumentException::class)
        ->and($reservation->refresh()->picked_quantity)->toBe(2)
        ->and($reservation->packed_quantity)->toBe(3)
        ->and($balance->refresh()->picked_quantity)->toBe(2)
        ->and($balance->packed_quantity)->toBe(3)
        ->and(InventoryMovement::query()->count())->toBe(1)
        ->and(ReservationTransition::query()->count())->toBe(1)
        ->and(Operation::query()->count())->toBe(1);
});
