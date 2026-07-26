<?php

use App\Enums\ProviderWebhookReceipts\Status as ReceiptStatus;
use App\Enums\Reservations\Status as ReservationStatus;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\Operation;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProviderSubmission;
use App\Models\ProviderWebhookReceipt;
use App\Models\Reservation;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use App\Models\Warehouse;
use App\Services\Shipping\ProviderWebhookService;

it('applies partial delivery progress idempotently without changing warehouse inventory', function () {
    $product = Product::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $order = Order::factory()->create();
    $orderItem = OrderItem::factory()->for($order)->for($product)->create([
        'ordered_quantity' => 3,
        'shipped_quantity' => 3,
        'delivered_quantity' => 0,
    ]);
    $reservation = Reservation::factory()
        ->for($orderItem)
        ->for($warehouse)
        ->closed(3)
        ->create();
    $balance = InventoryBalance::factory()
        ->for($product)
        ->for($warehouse)
        ->create(['available_quantity' => 5]);
    $shipment = Shipment::factory()->for($order)->for($warehouse)->shipped()->create();
    $shipmentItem = ShipmentItem::factory()
        ->for($shipment)
        ->for($reservation)
        ->create(['quantity' => 3]);
    $providerRequestKey = 'delivery-request-1';
    $externalShipmentId = 'mock-delivery-1';
    ProviderSubmission::factory()
        ->for($shipment)
        ->accepted($externalShipmentId)
        ->create(['provider_request_key' => $providerRequestKey]);
    $firstReceipt = deliveryReceipt(
        'delivery-event-1',
        $externalShipmentId,
        $providerRequestKey,
        $shipmentItem->id,
        2,
    );
    $webhooks = app(ProviderWebhookService::class);

    $webhooks->process($firstReceipt->id);
    $webhooks->process($firstReceipt->id);

    expect($shipmentItem->refresh()->delivered_quantity)->toBe(2)
        ->and($orderItem->refresh()->delivered_quantity)->toBe(2)
        ->and($firstReceipt->refresh()->status)->toBe(ReceiptStatus::Processed)
        ->and($balance->refresh()->available_quantity)->toBe(5)
        ->and(InventoryMovement::query()->doesntExist())->toBeTrue()
        ->and(Operation::query()->count())->toBe(1);

    $secondReceipt = deliveryReceipt(
        'delivery-event-2',
        $externalShipmentId,
        $providerRequestKey,
        $shipmentItem->id,
        1,
    );
    $webhooks->process($secondReceipt->id);

    expect($shipmentItem->refresh()->delivered_quantity)->toBe(3)
        ->and($orderItem->refresh()->delivered_quantity)->toBe(3)
        ->and($reservation->refresh()->status)->toBe(ReservationStatus::Closed)
        ->and($balance->refresh()->available_quantity)->toBe(5)
        ->and(InventoryMovement::query()->doesntExist())->toBeTrue()
        ->and(Operation::query()->count())->toBe(2);
});

function deliveryReceipt(
    string $externalEventId,
    string $externalShipmentId,
    string $providerRequestKey,
    int $shipmentItemId,
    int $quantity,
): ProviderWebhookReceipt {
    return ProviderWebhookReceipt::factory()->deliveryConfirmation()->create([
        'provider' => 'mock',
        'external_event_id' => $externalEventId,
        'raw_body' => json_encode([
            'external_event_id' => $externalEventId,
            'event_type' => 'delivery.confirmed',
            'external_shipment_id' => $externalShipmentId,
            'provider_request_key' => $providerRequestKey,
            'occurred_at' => now()->toISOString(),
            'items' => [
                ['shipment_item_id' => $shipmentItemId, 'quantity' => $quantity],
            ],
        ], JSON_THROW_ON_ERROR),
    ]);
}
