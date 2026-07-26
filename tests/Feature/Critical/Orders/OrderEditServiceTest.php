<?php

use App\DTOs\Orders\EditOrderItemQuantityInput;
use App\Enums\Reservations\Kind;
use App\Enums\Reservations\Status;
use App\Exceptions\PhysicalReversalRequiredException;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\Operation;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Reservation;
use App\Models\ReservationTransition;
use App\Models\Warehouse;
use App\Services\Orders\OrderService;

it('applies a decrease by consuming outstanding demand before releasing reservations', function () {
    [$orderItem, $reservation, $balance] = editableOrderItemContext(
        orderedQuantity: 10,
        reservedQuantity: 6,
    );
    $input = new EditOrderItemQuantityInput(
        orderItemId: $orderItem->id,
        quantityChange: -6,
        reason: 'Customer reduced requested units',
        idempotencyKey: 'edit-order-item-001',
        source: 'test',
    );
    $service = app(OrderService::class);

    $result = $service->editQuantity($input);
    $replayedResult = $service->editQuantity($input);

    expect($replayedResult)->toEqual($result)
        ->and($result->previousOrderedQuantity)->toBe(10)
        ->and($result->orderedQuantity)->toBe(4)
        ->and($result->quantityChange)->toBe(-6)
        ->and($result->releasedReservedQuantity)->toBe(2)
        ->and($result->outstandingQuantity)->toBe(0)
        ->and($orderItem->refresh()->ordered_quantity)->toBe(4)
        ->and($orderItem->reserved_quantity)->toBe(4)
        ->and($orderItem->cancelled_quantity)->toBe(0)
        ->and($reservation->refresh()->reserved_quantity)->toBe(4)
        ->and($reservation->released_quantity)->toBe(2)
        ->and($balance->refresh()->available_quantity)->toBe(6)
        ->and($balance->reserved_quantity)->toBe(4)
        ->and(InventoryMovement::query()->count())->toBe(1)
        ->and(ReservationTransition::query()->count())->toBe(1)
        ->and(Operation::query()->count())->toBe(2);

    $increaseInput = new EditOrderItemQuantityInput(
        orderItemId: $orderItem->id,
        quantityChange: 3,
        reason: 'Customer added requested units',
        idempotencyKey: 'edit-order-item-increase-001',
        source: 'test',
    );
    $increaseResult = $service->editQuantity($increaseInput);
    $replayedIncreaseResult = $service->editQuantity($increaseInput);

    expect($replayedIncreaseResult)->toEqual($increaseResult)
        ->and($increaseResult->previousOrderedQuantity)->toBe(4)
        ->and($increaseResult->orderedQuantity)->toBe(7)
        ->and($increaseResult->releasedReservedQuantity)->toBe(0)
        ->and($increaseResult->outstandingQuantity)->toBe(3)
        ->and($orderItem->refresh()->ordered_quantity)->toBe(7)
        ->and($orderItem->reserved_quantity)->toBe(4)
        ->and(InventoryMovement::query()->count())->toBe(1)
        ->and(ReservationTransition::query()->count())->toBe(1)
        ->and(Operation::query()->count())->toBe(3);
});

it('rejects a reduction that would require picked or packed reversal', function () {
    [$orderItem, $reservation, $balance] = editableOrderItemContext(
        orderedQuantity: 10,
        reservedQuantity: 2,
        pickedQuantity: 3,
        packedQuantity: 1,
    );

    expect(fn () => app(OrderService::class)->editQuantity(
        new EditOrderItemQuantityInput(
            orderItemId: $orderItem->id,
            quantityChange: -7,
            reason: 'Reduction exceeds reversible quantity',
            idempotencyKey: 'edit-order-item-physical-reversal-001',
            source: 'test',
        )
    ))->toThrow(PhysicalReversalRequiredException::class)
        ->and($orderItem->refresh()->ordered_quantity)->toBe(10)
        ->and($orderItem->reserved_quantity)->toBe(2)
        ->and($orderItem->picked_quantity)->toBe(3)
        ->and($orderItem->packed_quantity)->toBe(1)
        ->and($reservation->refresh()->reserved_quantity)->toBe(2)
        ->and($balance->refresh()->reserved_quantity)->toBe(2)
        ->and($balance->picked_quantity)->toBe(3)
        ->and($balance->packed_quantity)->toBe(1)
        ->and(InventoryMovement::query()->doesntExist())->toBeTrue()
        ->and(ReservationTransition::query()->doesntExist())->toBeTrue()
        ->and(Operation::query()->doesntExist())->toBeTrue();
});

/**
 * @return array{OrderItem, Reservation, InventoryBalance}
 */
function editableOrderItemContext(
    int $orderedQuantity,
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
    $balance = InventoryBalance::factory()
        ->for($product)
        ->for($warehouse)
        ->create([
            'available_quantity' => 4,
            'reserved_quantity' => $reservedQuantity,
            'picked_quantity' => $pickedQuantity,
            'packed_quantity' => $packedQuantity,
        ]);

    return [$orderItem, $reservation, $balance];
}
