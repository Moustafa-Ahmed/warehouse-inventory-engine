<?php

use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProviderSubmission;
use App\Models\ProviderWebhookReceipt;
use App\Models\Reservation;
use App\Models\Shipment;
use App\Models\User;
use App\Models\Warehouse;

it('summarizes operational attention queues through the report services', function () {
    config()->set('administrator.email', 'dashboard-administrator@example.test');
    $administrator = User::factory()->create([
        'email' => 'dashboard-administrator@example.test',
    ]);
    $warehouse = Warehouse::factory()->create(['code' => 'DASH-WH']);
    $product = Product::factory()->create(['sku' => 'DASH-SKU']);
    $partialOrder = Order::factory()->create(['order_number' => 'DASH-PARTIAL']);
    $partialItem = OrderItem::factory()
        ->for($partialOrder)
        ->for($product)
        ->create();
    Reservation::factory()
        ->for($partialItem)
        ->for($warehouse)
        ->create([
            'requested_quantity' => 5,
            'reserved_quantity' => 2,
        ]);
    $expiringOrder = Order::factory()->create(['order_number' => 'DASH-EXPIRING']);
    $expiringItem = OrderItem::factory()
        ->for($expiringOrder)
        ->for($product)
        ->create();
    Reservation::factory()
        ->temporary()
        ->for($expiringItem)
        ->for($warehouse)
        ->create();
    $shipmentOrder = Order::factory()->create(['order_number' => 'DASH-SHIPMENT']);
    $shipment = Shipment::factory()
        ->for($shipmentOrder)
        ->for($warehouse)
        ->create();
    ProviderSubmission::factory()->unknown()->for($shipment)->create();
    ProviderWebhookReceipt::factory()->create([
        'external_event_id' => 'DASH-PENDING-EVENT',
    ]);
    InventoryMovement::factory()
        ->for($product)
        ->create(['business_reference_type' => 'dashboard_receipt']);

    $this->actingAs($administrator)
        ->get(route('operations.home'))
        ->assertSuccessful()
        ->assertSee('Operational health')
        ->assertSee('Partial allocations')
        ->assertSee('Expiring reservations')
        ->assertSee('Pending handoff')
        ->assertSee('Provider attention')
        ->assertSee('Pending webhooks')
        ->assertSee('Recent movements')
        ->assertSee('DASH-PARTIAL')
        ->assertSee('DASH-EXPIRING')
        ->assertSee('DASH-SHIPMENT')
        ->assertSee('DASH-PENDING-EVENT')
        ->assertSee('DASH-SKU')
        ->assertSee('dashboard receipt');
});
