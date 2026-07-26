<?php

use App\Contracts\ShippingProvider;
use App\Models\MockProviderWebhook;
use App\Models\ProviderWebhookReceipt;
use App\Models\ShipmentItem;
use App\Services\Shipping\PersistentMockProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('boots against the isolated MySQL test database', function () {
    expect(config('database.default'))->toBe('mysql')
        ->and(DB::connection()->getDriverName())->toBe('mysql')
        ->and(Schema::hasTable('users'))->toBeTrue()
        ->and(Schema::hasTable('products'))->toBeTrue()
        ->and(Schema::hasTable('warehouses'))->toBeTrue()
        ->and(Schema::hasTable('operations'))->toBeTrue()
        ->and(Schema::hasTable('inventory_balances'))->toBeTrue()
        ->and(Schema::hasTable('inventory_movements'))->toBeTrue()
        ->and(Schema::hasTable('orders'))->toBeTrue()
        ->and(Schema::hasTable('order_items'))->toBeTrue()
        ->and(Schema::hasTable('reservations'))->toBeTrue()
        ->and(Schema::hasTable('reservation_transitions'))->toBeTrue()
        ->and(Schema::hasTable('shipments'))->toBeTrue()
        ->and(Schema::hasTable('shipment_items'))->toBeTrue()
        ->and(Schema::hasTable('provider_submissions'))->toBeTrue()
        ->and(Schema::hasTable('mock_provider_shipments'))->toBeTrue()
        ->and(Schema::hasTable('mock_provider_webhooks'))->toBeTrue()
        ->and(Schema::hasTable('provider_webhook_receipts'))->toBeTrue()
        ->and($this->app->make(ShippingProvider::class))->toBeInstanceOf(PersistentMockProvider::class);

    $mockProviderWebhook = MockProviderWebhook::factory()->create();
    $providerWebhookReceipt = ProviderWebhookReceipt::factory()->create();
    $shipmentItem = ShipmentItem::factory()->create();

    expect(json_decode($mockProviderWebhook->raw_body, true, flags: JSON_THROW_ON_ERROR)['external_event_id'])
        ->toBe($mockProviderWebhook->external_event_id)
        ->and(json_decode($providerWebhookReceipt->raw_body, true, flags: JSON_THROW_ON_ERROR)['external_event_id'])
        ->toBe($providerWebhookReceipt->external_event_id)
        ->and($shipmentItem->reservation->warehouse_id)->toBe($shipmentItem->shipment->warehouse_id)
        ->and($shipmentItem->reservation->orderItem->order_id)->toBe($shipmentItem->shipment->order_id)
        ->and($shipmentItem->reservation->packed_quantity)->toBe($shipmentItem->quantity);

    $this->get('/')->assertRedirect(route('login'));
    $this->get(route('login'))->assertSuccessful();
});
