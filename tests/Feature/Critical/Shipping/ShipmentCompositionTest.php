<?php

use App\DTOs\Shipping\CreateShipmentInput;
use App\DTOs\Shipping\CreateShipmentItemInput;
use App\Enums\Reservations\Kind;
use App\Enums\Reservations\Status as ReservationStatus;
use App\Enums\Shipments\Status;
use App\Models\InventoryMovement;
use App\Models\Operation;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Reservation;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use App\Models\Warehouse;
use App\Services\Shipping\ShipmentService;

it('composes warehouse-scoped partial shipments without deducting inventory', function () {
    $order = Order::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $otherWarehouse = Warehouse::factory()->create();
    $firstReservation = packedReservation($order, $warehouse, 6);
    $secondReservation = packedReservation($order, $warehouse, 3);
    $otherWarehouseReservation = packedReservation($order, $otherWarehouse, 2);
    $input = new CreateShipmentInput(
        orderId: $order->id,
        warehouseId: $warehouse->id,
        items: [
            new CreateShipmentItemInput($firstReservation->id, 4),
            new CreateShipmentItemInput($secondReservation->id, 3),
        ],
        idempotencyKey: 'create-shipment-001',
    );
    $service = app(ShipmentService::class);

    $result = $service->create($input);
    $replayedResult = $service->create($input);
    $shipment = Shipment::query()->findOrFail($result->shipmentId);

    expect($replayedResult)->toEqual($result)
        ->and($shipment->status)->toBe(Status::PendingHandoff)
        ->and($shipment->shipped_at)->toBeNull()
        ->and($shipment->order_id)->toBe($order->id)
        ->and($shipment->warehouse_id)->toBe($warehouse->id)
        ->and($shipment->items)->toHaveCount(2)
        ->and($shipment->items->pluck('delivered_quantity')->all())->toBe([0, 0])
        ->and($firstReservation->refresh()->packed_quantity)->toBe(6)
        ->and($secondReservation->refresh()->packed_quantity)->toBe(3)
        ->and(InventoryMovement::query()->doesntExist())->toBeTrue();

    $secondShipment = $service->create(new CreateShipmentInput(
        orderId: $order->id,
        warehouseId: $warehouse->id,
        items: [new CreateShipmentItemInput($firstReservation->id, 2)],
        idempotencyKey: 'create-shipment-002',
    ));

    expect($secondShipment->items[0]['quantity'])->toBe(2)
        ->and(fn () => $service->create(new CreateShipmentInput(
            orderId: $order->id,
            warehouseId: $warehouse->id,
            items: [new CreateShipmentItemInput($firstReservation->id, 1)],
            idempotencyKey: 'create-shipment-exceeds-packed',
        )))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $service->create(new CreateShipmentInput(
            orderId: $order->id,
            warehouseId: $warehouse->id,
            items: [new CreateShipmentItemInput($otherWarehouseReservation->id, 1)],
            idempotencyKey: 'create-shipment-cross-warehouse',
        )))->toThrow(InvalidArgumentException::class)
        ->and(Shipment::query()->count())->toBe(2)
        ->and(ShipmentItem::query()->count())->toBe(3)
        ->and(Operation::query()->count())->toBe(2)
        ->and(InventoryMovement::query()->doesntExist())->toBeTrue();
});

function packedReservation(
    Order $order,
    Warehouse $warehouse,
    int $quantity,
): Reservation {
    $product = Product::factory()->create();
    $orderItem = OrderItem::factory()
        ->for($order)
        ->for($product)
        ->create([
            'ordered_quantity' => $quantity,
            'packed_quantity' => $quantity,
        ]);

    return Reservation::factory()
        ->for($orderItem)
        ->for($warehouse)
        ->create([
            'kind' => Kind::Confirmed,
            'status' => ReservationStatus::Open,
            'requested_quantity' => $quantity,
            'reserved_quantity' => 0,
            'picked_quantity' => 0,
            'packed_quantity' => $quantity,
            'shipped_quantity' => 0,
            'released_quantity' => 0,
        ]);
}
