<?php

use App\DTOs\Fulfillment\ReturnPickedInventoryInput;
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
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Fulfillment\FulfillmentService;

it('returns picked inventory to available stock once with an administrator reason', function () {
    $product = Product::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $actor = User::factory()->create();
    $orderItem = OrderItem::factory()
        ->for($product)
        ->create([
            'ordered_quantity' => 5,
            'reserved_quantity' => 2,
            'picked_quantity' => 3,
        ]);
    $reservation = Reservation::factory()
        ->for($orderItem)
        ->for($warehouse)
        ->create([
            'kind' => Kind::Confirmed,
            'status' => Status::Open,
            'requested_quantity' => 5,
            'reserved_quantity' => 2,
            'picked_quantity' => 3,
            'packed_quantity' => 0,
            'shipped_quantity' => 0,
            'released_quantity' => 0,
        ]);
    $balance = InventoryBalance::factory()
        ->for($product)
        ->for($warehouse)
        ->create([
            'reserved_quantity' => 2,
            'picked_quantity' => 3,
        ]);
    $input = new ReturnPickedInventoryInput(
        reservationId: $reservation->id,
        quantity: 2,
        reason: 'Items returned to an available shelf',
        idempotencyKey: 'return-picked-001',
        actorId: $actor->id,
    );
    $service = app(FulfillmentService::class);

    $result = $service->returnPicked($input);
    $replayedResult = $service->returnPicked($input);
    $movement = InventoryMovement::query()->sole();
    $transition = ReservationTransition::query()->sole();

    expect($replayedResult)->toEqual($result)
        ->and($result->returnedQuantity)->toBe(2)
        ->and($result->remainingPickedQuantity)->toBe(1)
        ->and($result->outstandingQuantity)->toBe(2)
        ->and($reservation->refresh()->picked_quantity)->toBe(1)
        ->and($reservation->released_quantity)->toBe(2)
        ->and($orderItem->refresh()->picked_quantity)->toBe(1)
        ->and($balance->refresh()->available_quantity)->toBe(2)
        ->and($balance->picked_quantity)->toBe(1)
        ->and($movement->source_bucket)->toBe(MovementBucket::Picked)
        ->and($movement->destination_bucket)->toBe(MovementBucket::Available)
        ->and($movement->actor_id)->toBe($actor->id)
        ->and($movement->metadata)->toBe(['reason' => 'Items returned to an available shelf'])
        ->and($transition->actor_id)->toBe($actor->id)
        ->and(Operation::query()->count())->toBe(1);
});
