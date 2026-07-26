<?php

use App\Enums\Inventory\MovementBucket;
use App\Enums\ProviderWebhookReceipts\Status as ReceiptStatus;
use App\Enums\Reservations\Kind;
use App\Enums\Reservations\Status as ReservationStatus;
use App\Enums\Shipments\Status as ShipmentStatus;
use App\Jobs\ProcessProviderWebhookJob;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\Operation;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProviderSubmission;
use App\Models\ProviderWebhookReceipt;
use App\Models\Reservation;
use App\Models\ReservationTransition;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use App\Models\Warehouse;
use App\Services\Shipping\ProviderWebhookService;
use Illuminate\Support\Facades\Event;

it('confirms carrier handoff atomically and exactly once through a persisted webhook', function () {
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
    $providerRequestKey = 'handoff-request-1';
    $externalShipmentId = 'mock-handoff-1';
    ProviderSubmission::factory()
        ->for($shipment)
        ->accepted($externalShipmentId)
        ->create(['provider_request_key' => $providerRequestKey]);
    $externalEventId = 'handoff-event-1';
    $rawBody = json_encode([
        'external_event_id' => $externalEventId,
        'event_type' => 'shipment.confirmed',
        'external_shipment_id' => $externalShipmentId,
        'provider_request_key' => $providerRequestKey,
        'occurred_at' => now()->toISOString(),
        'items' => [
            ['shipment_item_id' => $shipmentItem->id, 'quantity' => 2],
        ],
    ], JSON_THROW_ON_ERROR);
    $receipt = ProviderWebhookReceipt::factory()->create([
        'provider' => 'mock',
        'external_event_id' => $externalEventId,
        'raw_body' => $rawBody,
    ]);
    $job = new ProcessProviderWebhookJob($receipt->id);
    $webhooks = app(ProviderWebhookService::class);
    $transitionEvent = 'eloquent.creating: '.ReservationTransition::class;
    Event::listen(
        $transitionEvent,
        fn (): never => throw new RuntimeException('Injected confirmation history failure.'),
    );

    try {
        expect(fn () => $job->handle($webhooks))
            ->toThrow(RuntimeException::class, 'Injected confirmation history failure.');
    } finally {
        Event::forget($transitionEvent);
    }

    expect($balance->refresh()->packed_quantity)->toBe(2)
        ->and($reservation->refresh()->packed_quantity)->toBe(2)
        ->and($reservation->shipped_quantity)->toBe(0)
        ->and($orderItem->refresh()->packed_quantity)->toBe(2)
        ->and($orderItem->shipped_quantity)->toBe(0)
        ->and($shipment->refresh()->status)->toBe(ShipmentStatus::PendingHandoff)
        ->and($receipt->refresh()->status)->toBe(ReceiptStatus::Pending)
        ->and(Operation::query()->doesntExist())->toBeTrue()
        ->and(InventoryMovement::query()->doesntExist())->toBeTrue()
        ->and(ReservationTransition::query()->doesntExist())->toBeTrue();

    $job->handle($webhooks);
    $job->handle($webhooks);
    $movement = InventoryMovement::query()->sole();

    expect($balance->refresh()->packed_quantity)->toBe(0)
        ->and($reservation->refresh()->packed_quantity)->toBe(0)
        ->and($reservation->shipped_quantity)->toBe(2)
        ->and($reservation->status)->toBe(ReservationStatus::Closed)
        ->and($orderItem->refresh()->packed_quantity)->toBe(0)
        ->and($orderItem->shipped_quantity)->toBe(2)
        ->and($shipment->refresh()->status)->toBe(ShipmentStatus::Shipped)
        ->and($shipment->shipped_at)->not->toBeNull()
        ->and($receipt->refresh()->status)->toBe(ReceiptStatus::Processed)
        ->and($receipt->processed_at)->not->toBeNull()
        ->and($movement->source_warehouse_id)->toBe($warehouse->id)
        ->and($movement->source_bucket)->toBe(MovementBucket::Packed)
        ->and($movement->destination_warehouse_id)->toBeNull()
        ->and($movement->destination_bucket)->toBe(MovementBucket::Shipped)
        ->and($movement->quantity)->toBe(2)
        ->and(Operation::query()->count())->toBe(1)
        ->and(InventoryMovement::query()->count())->toBe(1)
        ->and(ReservationTransition::query()->count())->toBe(1);
})->repeat(3);
