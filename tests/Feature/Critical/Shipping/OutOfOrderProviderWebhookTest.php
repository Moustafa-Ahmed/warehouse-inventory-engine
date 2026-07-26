<?php

use App\Enums\ProviderWebhookReceipts\Status as ReceiptStatus;
use App\Enums\Reservations\Kind;
use App\Enums\Reservations\Status as ReservationStatus;
use App\Enums\Shipments\Status as ShipmentStatus;
use App\Enums\Shipping\EventType;
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

it('defers an out-of-order delivery then processes it once after handoff', function () {
    $product = Product::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $order = Order::factory()->create();
    $orderItem = OrderItem::factory()->for($order)->for($product)->create([
        'ordered_quantity' => 2,
        'packed_quantity' => 2,
    ]);
    $reservation = Reservation::factory()
        ->for($orderItem)
        ->for($warehouse)
        ->packed(2)
        ->create([
            'kind' => Kind::Confirmed,
            'status' => ReservationStatus::Open,
        ]);
    $balance = InventoryBalance::factory()
        ->for($product)
        ->for($warehouse)
        ->create(['packed_quantity' => 2]);
    $shipment = Shipment::factory()->for($order)->for($warehouse)->create();
    $shipmentItem = ShipmentItem::factory()
        ->for($shipment)
        ->for($reservation)
        ->create(['quantity' => 2]);
    $providerRequestKey = 'out-of-order-request-1';
    $externalShipmentId = 'mock-out-of-order-1';
    ProviderSubmission::factory()
        ->for($shipment)
        ->accepted($externalShipmentId)
        ->create(['provider_request_key' => $providerRequestKey]);
    $createReceipt = function (
        string $externalEventId,
        EventType $eventType,
    ) use (
        $externalShipmentId,
        $providerRequestKey,
        $shipmentItem,
    ): ProviderWebhookReceipt {
        return ProviderWebhookReceipt::factory()->create([
            'provider' => 'mock',
            'external_event_id' => $externalEventId,
            'event_type' => $eventType,
            'raw_body' => json_encode([
                'external_event_id' => $externalEventId,
                'event_type' => $eventType->value,
                'external_shipment_id' => $externalShipmentId,
                'provider_request_key' => $providerRequestKey,
                'occurred_at' => now()->toISOString(),
                'items' => [
                    ['shipment_item_id' => $shipmentItem->id, 'quantity' => 2],
                ],
            ], JSON_THROW_ON_ERROR),
        ]);
    };
    $deliveryReceipt = $createReceipt(
        'out-of-order-delivery-event-1',
        EventType::DeliveryConfirmed,
    );
    $webhooks = app(ProviderWebhookService::class);

    $webhooks->process($deliveryReceipt->id);

    expect($deliveryReceipt->refresh()->status)->toBe(ReceiptStatus::Pending)
        ->and($shipment->refresh()->status)->toBe(ShipmentStatus::PendingHandoff)
        ->and($shipmentItem->refresh()->delivered_quantity)->toBe(0)
        ->and($balance->refresh()->packed_quantity)->toBe(2)
        ->and(InventoryMovement::query()->doesntExist())->toBeTrue()
        ->and(Operation::query()->doesntExist())->toBeTrue();

    $handoffReceipt = $createReceipt(
        'out-of-order-handoff-event-1',
        EventType::ShipmentConfirmed,
    );
    $webhooks->process($handoffReceipt->id);

    expect($shipment->refresh()->status)->toBe(ShipmentStatus::Shipped)
        ->and($deliveryReceipt->refresh()->status)->toBe(ReceiptStatus::Pending)
        ->and($balance->refresh()->packed_quantity)->toBe(0)
        ->and(InventoryMovement::query()->count())->toBe(1);

    $this->artisan('provider-webhooks:process-pending', ['--limit' => 10])
        ->expectsOutput('Dispatched 1 provider webhook receipt(s).')
        ->assertSuccessful();

    expect($deliveryReceipt->refresh()->status)->toBe(ReceiptStatus::Processed)
        ->and($shipmentItem->refresh()->delivered_quantity)->toBe(2)
        ->and($orderItem->refresh()->delivered_quantity)->toBe(2)
        ->and($balance->refresh()->packed_quantity)->toBe(0)
        ->and(InventoryMovement::query()->count())->toBe(1)
        ->and(Operation::query()->count())->toBe(2);

    $staleReceipt = $createReceipt(
        'stale-handoff-event-1',
        EventType::ShipmentConfirmed,
    );
    $webhooks->process($staleReceipt->id);

    expect($staleReceipt->refresh()->status)->toBe(ReceiptStatus::IgnoredAsStale)
        ->and($staleReceipt->processed_at)->not->toBeNull()
        ->and(InventoryMovement::query()->count())->toBe(1)
        ->and(Operation::query()->count())->toBe(2);

    $this->artisan('provider-webhooks:process-pending', ['--limit' => 10])
        ->expectsOutput('Dispatched 0 provider webhook receipt(s).')
        ->assertSuccessful();
});
