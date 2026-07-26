<?php

use App\DTOs\Inventory\ReceiveStockInput;
use App\DTOs\Orders\CreateOrderInput;
use App\DTOs\Orders\CreateOrderItemInput;
use App\DTOs\Reservations\ReserveOrderItemInput;
use App\Enums\Shipping\EventType;
use App\Models\MockProviderShipment;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProviderWebhookReceipt;
use App\Models\Reservation;
use App\Models\Shipment;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Inventory\InventoryService;
use App\Services\Orders\OrderService;
use App\Services\Reservations\ReservationService;
use App\Services\Shipping\ShipmentSubmissionService;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

it('operates fulfillment and exposes the provider lifecycle without rendering sensitive payloads', function () {
    Queue::fake();
    config()->set('administrator.email', 'shipping-ui-administrator@example.test');
    config()->set('shipping.webhook.providers.mock.secret', 'super-secret-webhook-key');

    $administrator = User::factory()->create([
        'email' => 'shipping-ui-administrator@example.test',
    ]);
    $product = Product::factory()->create();
    $warehouse = Warehouse::factory()->create();

    app(InventoryService::class)->receive(new ReceiveStockInput(
        productId: $product->id,
        warehouseId: $warehouse->id,
        quantity: 4,
        sourceReference: 'shipping-ui-stock',
        idempotencyKey: (string) Str::uuid(),
        actorId: $administrator->id,
    ));
    $createdOrder = app(OrderService::class)->create(new CreateOrderInput(
        orderNumber: 'SHIP-WEB-001',
        items: [new CreateOrderItemInput($product->id, 4)],
        idempotencyKey: (string) Str::uuid(),
    ));
    $order = Order::query()->with('items')->findOrFail($createdOrder->orderId);
    $reservationResult = app(ReservationService::class)->reserve(new ReserveOrderItemInput(
        orderItemId: $order->items->sole()->id,
        warehouseId: $warehouse->id,
        idempotencyKey: (string) Str::uuid(),
        actorId: $administrator->id,
        source: 'shipping_ui_setup',
    ));
    $reservation = Reservation::query()->findOrFail($reservationResult->reservationId);

    $this->actingAs($administrator)
        ->post(route('reservations.pick', $reservation), [
            'quantity' => 3,
            'pick_operation_key' => (string) Str::uuid(),
        ])->assertRedirect(route('reservations.show', $reservation))
        ->assertSessionHas('status', 'Inventory picked.');

    $this->post(route('reservations.return-picked', $reservation), [
        'quantity' => 1,
        'reason' => 'Return one inspected unit.',
        'return_operation_key' => (string) Str::uuid(),
    ])->assertRedirect(route('reservations.show', $reservation))
        ->assertSessionHas('status', 'Picked inventory returned to available.');

    $this->post(route('reservations.pack', $reservation), [
        'quantity' => 2,
        'pack_operation_key' => (string) Str::uuid(),
    ])->assertRedirect(route('reservations.show', $reservation))
        ->assertSessionHas('status', 'Picked inventory packed.');

    $this->post(route('reservations.unpack', $reservation), [
        'quantity' => 1,
        'reason' => 'Reopen the parcel for inspection.',
        'unpack_operation_key' => (string) Str::uuid(),
    ])->assertRedirect(route('reservations.show', $reservation))
        ->assertSessionHas('status', 'Packed inventory returned to picked.');

    $this->post(route('reservations.pack', $reservation), [
        'quantity' => 1,
        'pack_operation_key' => (string) Str::uuid(),
    ])->assertSessionHas('status', 'Picked inventory packed.');

    $group = $order->id.':'.$warehouse->id;

    $this->get(route('shipments.create', ['group' => $group]))
        ->assertSuccessful()
        ->assertSee($order->order_number)
        ->assertSee($product->sku)
        ->assertSee('Unassigned packed');

    $this->post(route('shipments.store'), [
        'order_id' => $order->id,
        'warehouse_id' => $warehouse->id,
        'items' => [$reservation->id => 2],
        'shipment_operation_key' => (string) Str::uuid(),
    ])->assertRedirect()
        ->assertSessionHas('status', 'Shipment composed from packed inventory.');

    $shipment = Shipment::query()->sole();

    $this->post(route('shipments.mock-provider-scenario.store', $shipment), [
        'scenario' => 'immediate_success',
    ])->assertRedirect(route('shipments.show', $shipment))
        ->assertSessionHas('status', 'The next provider outcome was selected.');

    $this->post(route('shipments.submit', $shipment))
        ->assertRedirect(route('shipments.show', $shipment))
        ->assertSessionHas('status', 'Shipment submission queued with its stable provider request identity.');

    app(ShipmentSubmissionService::class)->submit($shipment->id);

    $mockProviderShipment = MockProviderShipment::query()->with('webhooks')->sole();
    $webhook = $mockProviderShipment->webhooks->firstOrFail();

    ProviderWebhookReceipt::query()->create([
        'provider' => 'mock',
        'external_event_id' => $webhook->external_event_id,
        'event_type' => EventType::ShipmentConfirmed,
        'raw_body' => '{"sensitive-payload-marker":"must-not-render"}',
        'occurred_at' => now(),
    ]);
    $receipt = ProviderWebhookReceipt::query()->sole();

    $this->get(route('shipments.show', $shipment))
        ->assertSuccessful()
        ->assertSee('Provider submissions')
        ->assertSee('Mock-provider shipment')
        ->assertSee('Outbound webhook')
        ->assertSee('Received provider webhooks')
        ->assertDontSee('sensitive-payload-marker')
        ->assertDontSee('super-secret-webhook-key');

    $this->get(route('provider-webhook-receipts.show', $receipt))
        ->assertSuccessful()
        ->assertSee('Raw callback bodies and authentication material are intentionally not rendered.')
        ->assertDontSee('sensitive-payload-marker')
        ->assertDontSee('super-secret-webhook-key');

    $this->post(route('shipments.mock-provider.handoff', [$shipment, $mockProviderShipment]))
        ->assertRedirect(route('shipments.show', $shipment))
        ->assertSessionHas('status', 'Shipment-confirmation webhook queued for signed HTTP delivery.');

    $this->post(route('mock-provider.webhooks.replay', [$mockProviderShipment, $webhook]))
        ->assertRedirect(route('shipments.show', $shipment))
        ->assertSessionHas(
            'status',
            'The persisted webhook was queued again with the same event ID and raw body.',
        );
});
