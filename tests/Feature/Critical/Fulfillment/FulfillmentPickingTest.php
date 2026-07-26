<?php

use App\DTOs\Fulfillment\PickReservationInput;
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

it('picks confirmed reserved inventory once', function () {
    [$orderItem, $reservation, $balance] = pickableReservationContext();
    $input = new PickReservationInput(
        reservationId: $reservation->id,
        quantity: 2,
        idempotencyKey: 'pick-reservation-001',
        source: 'test',
    );
    $service = app(FulfillmentService::class);

    $result = $service->pick($input);
    $replayedResult = $service->pick($input);
    $movement = InventoryMovement::query()->sole();

    expect($replayedResult)->toEqual($result)
        ->and($result->pickedQuantity)->toBe(2)
        ->and($result->remainingReservedQuantity)->toBe(3)
        ->and($result->totalPickedQuantity)->toBe(2)
        ->and($result->outstandingQuantity)->toBe(0)
        ->and($reservation->refresh()->reserved_quantity)->toBe(3)
        ->and($reservation->picked_quantity)->toBe(2)
        ->and($orderItem->refresh()->reserved_quantity)->toBe(3)
        ->and($orderItem->picked_quantity)->toBe(2)
        ->and($balance->refresh()->reserved_quantity)->toBe(3)
        ->and($balance->picked_quantity)->toBe(2)
        ->and($movement->source_bucket)->toBe(MovementBucket::Reserved)
        ->and($movement->destination_bucket)->toBe(MovementBucket::Picked)
        ->and(ReservationTransition::query()->count())->toBe(1)
        ->and(Operation::query()->count())->toBe(1);
});

it('rejects reservations that are not both confirmed and open', function (
    Kind $kind,
    Status $status,
) {
    [$orderItem, $reservation, $balance] = pickableReservationContext($kind, $status);

    expect(fn () => app(FulfillmentService::class)->pick(new PickReservationInput(
        reservationId: $reservation->id,
        quantity: 1,
        idempotencyKey: "pick-invalid-{$kind->value}-{$status->value}",
        source: 'test',
    )))->toThrow(InvalidArgumentException::class)
        ->and($reservation->refresh()->reserved_quantity)->toBe(5)
        ->and($orderItem->refresh()->reserved_quantity)->toBe(5)
        ->and($balance->refresh()->reserved_quantity)->toBe(5)
        ->and(InventoryMovement::query()->doesntExist())->toBeTrue()
        ->and(ReservationTransition::query()->doesntExist())->toBeTrue()
        ->and(Operation::query()->doesntExist())->toBeTrue();
})->with([
    'temporary' => [Kind::Temporary, Status::Open],
    'released' => [Kind::Confirmed, Status::Released],
    'expired' => [Kind::Confirmed, Status::Expired],
    'closed' => [Kind::Confirmed, Status::Closed],
]);

/**
 * @return array{OrderItem, Reservation, InventoryBalance}
 */
function pickableReservationContext(
    Kind $kind = Kind::Confirmed,
    Status $status = Status::Open,
): array {
    $product = Product::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $orderItem = OrderItem::factory()
        ->for($product)
        ->create([
            'ordered_quantity' => 5,
            'reserved_quantity' => 5,
        ]);
    $reservation = Reservation::factory()
        ->for($orderItem)
        ->for($warehouse)
        ->create([
            'kind' => $kind,
            'status' => $status,
            'requested_quantity' => 5,
            'reserved_quantity' => 5,
            'picked_quantity' => 0,
            'packed_quantity' => 0,
            'shipped_quantity' => 0,
            'released_quantity' => 0,
            'expires_at' => $kind === Kind::Temporary ? now()->addHour() : null,
        ]);
    $balance = InventoryBalance::factory()
        ->for($product)
        ->for($warehouse)
        ->create(['reserved_quantity' => 5]);

    return [$orderItem, $reservation, $balance];
}
