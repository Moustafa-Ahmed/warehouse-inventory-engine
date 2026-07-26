<?php

use App\DTOs\Fulfillment\PackReservationInput;
use App\DTOs\Fulfillment\PickReservationInput;
use App\DTOs\Inventory\ReceiveStockInput;
use App\DTOs\Orders\CreateOrderInput;
use App\DTOs\Orders\CreateOrderItemInput;
use App\DTOs\Reservations\ReserveOrderItemInput;
use App\DTOs\Shipping\CreateShipmentInput;
use App\DTOs\Shipping\CreateShipmentItemInput;
use App\Enums\ProviderSubmissions\Status as ProviderSubmissionStatus;
use App\Enums\Shipping\EventType;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProviderSubmission;
use App\Models\ProviderWebhookReceipt;
use App\Models\Reservation;
use App\Models\Shipment;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Fulfillment\FulfillmentService;
use App\Services\Inventory\InventoryReportService;
use App\Services\Inventory\InventoryService;
use App\Services\Orders\OrderReportService;
use App\Services\Orders\OrderService;
use App\Services\Reservations\ReservationReportService;
use App\Services\Reservations\ReservationService;
use App\Services\Shipping\ProviderWebhookService;
use App\Services\Shipping\ShipmentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

it('answers operational inventory questions from projections and canonical movements', function () {
    Queue::fake();
    config()->set('administrator.email', 'report-ui-administrator@example.test');

    $administrator = User::factory()->create([
        'email' => 'report-ui-administrator@example.test',
    ]);
    $product = Product::factory()->create();
    $warehouse = Warehouse::factory()->create();

    app(InventoryService::class)->receive(new ReceiveStockInput(
        productId: $product->id,
        warehouseId: $warehouse->id,
        quantity: 8,
        sourceReference: 'report-stock',
        idempotencyKey: (string) Str::uuid(),
        actorId: $administrator->id,
    ));
    $createdOrder = app(OrderService::class)->create(new CreateOrderInput(
        orderNumber: 'REPORT-ORDER-001',
        items: [new CreateOrderItemInput($product->id, 8)],
        idempotencyKey: (string) Str::uuid(),
    ));
    $order = Order::query()->with('items')->findOrFail($createdOrder->orderId);
    $reservationResult = app(ReservationService::class)->reserve(new ReserveOrderItemInput(
        orderItemId: $order->items->sole()->id,
        warehouseId: $warehouse->id,
        idempotencyKey: (string) Str::uuid(),
    ));
    $reservation = Reservation::query()->findOrFail($reservationResult->reservationId);

    app(FulfillmentService::class)->pick(new PickReservationInput(
        reservationId: $reservation->id,
        quantity: 8,
        idempotencyKey: (string) Str::uuid(),
    ));
    app(FulfillmentService::class)->pack(new PackReservationInput(
        reservationId: $reservation->id,
        quantity: 8,
        idempotencyKey: (string) Str::uuid(),
    ));
    $shipmentResult = app(ShipmentService::class)->create(new CreateShipmentInput(
        orderId: $order->id,
        warehouseId: $warehouse->id,
        items: [new CreateShipmentItemInput($reservation->id, 5)],
        idempotencyKey: (string) Str::uuid(),
    ));
    $shipment = Shipment::query()->with('items')->findOrFail($shipmentResult->shipmentId);
    $providerRequestKey = 'report-provider-request';
    $externalShipmentId = 'report-external-shipment';

    ProviderSubmission::query()->create([
        'shipment_id' => $shipment->id,
        'provider_request_key' => $providerRequestKey,
    ])->forceFill([
        'status' => ProviderSubmissionStatus::Accepted,
        'external_shipment_id' => $externalShipmentId,
        'last_attempted_at' => now(),
        'resolved_at' => now(),
    ])->save();

    $occurredAt = now();
    $receipt = ProviderWebhookReceipt::query()->create([
        'provider' => 'mock',
        'external_event_id' => 'report-shipment-confirmed',
        'event_type' => EventType::ShipmentConfirmed,
        'raw_body' => json_encode([
            'external_event_id' => 'report-shipment-confirmed',
            'event_type' => EventType::ShipmentConfirmed->value,
            'external_shipment_id' => $externalShipmentId,
            'provider_request_key' => $providerRequestKey,
            'occurred_at' => $occurredAt->toISOString(),
            'items' => $shipment->items->map(fn ($item): array => [
                'shipment_item_id' => $item->id,
                'quantity' => $item->quantity,
            ])->all(),
        ], JSON_THROW_ON_ERROR),
        'occurred_at' => $occurredAt,
    ]);
    app(ProviderWebhookService::class)->process($receipt->id);

    $inventoryRow = app(InventoryReportService::class)
        ->inventory($product->id, $warehouse->id)
        ->sole();
    $reservationRows = app(ReservationReportService::class)->reservations(
        productId: $product->id,
        warehouseId: $warehouse->id,
    );
    $consumedRow = app(OrderReportService::class)
        ->consumedInventory($product->id, $warehouse->id)
        ->sole();
    $handoffMovements = app(InventoryReportService::class)
        ->movements(referenceType: 'shipment_handoff');

    expect((int) $inventoryRow->available_quantity)->toBe(0)
        ->and((int) $inventoryRow->reserved_quantity)->toBe(0)
        ->and((int) $inventoryRow->picked_quantity)->toBe(0)
        ->and((int) $inventoryRow->packed_quantity)->toBe(3)
        ->and((int) $inventoryRow->on_hand_quantity)->toBe(3)
        ->and((int) $inventoryRow->shipped_quantity)->toBe(5)
        ->and($reservationRows->total())->toBe(1)
        ->and((int) $consumedRow->consumed_quantity)->toBe(5)
        ->and($consumedRow->order_number)->toBe('REPORT-ORDER-001')
        ->and($handoffMovements->total())->toBe(1);

    $movementPlan = collect(DB::select(
        'EXPLAIN SELECT id FROM inventory_movements
         WHERE business_reference_type = ? AND business_reference_id = ?',
        ['shipment_handoff', (string) $shipment->id],
    ))->first();
    $reservationPlan = collect(DB::select(
        'EXPLAIN SELECT id FROM reservations
         WHERE status = ? ORDER BY created_at LIMIT 30',
        ['open'],
    ))->first();

    expect($movementPlan->possible_keys)->toContain('inventory_movements_business_reference_index')
        ->and($reservationPlan->possible_keys)->toContain('reservations_status_created_at_index');

    $filters = [
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
    ];

    $this->actingAs($administrator)
        ->get(route('reports.inventory', $filters))
        ->assertSuccessful()
        ->assertSee($product->sku)
        ->assertSee('Current warehouse buckets');
    $this->get(route('reports.reservations', $filters))
        ->assertSuccessful()
        ->assertSee('REPORT-ORDER-001');
    $this->get(route('reports.consumed-orders', $filters))
        ->assertSuccessful()
        ->assertSee('Only confirmed packed-to-external');
    $this->get(route('reports.movements', [
        ...$filters,
        'reference_type' => 'shipment_handoff',
    ]))->assertSuccessful()
        ->assertSee('shipment_handoff');
});
