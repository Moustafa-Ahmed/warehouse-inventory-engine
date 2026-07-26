<?php

use App\Enums\ProviderSubmissions\Status as SubmissionStatus;
use App\Enums\ProviderWebhookReceipts\Status as ReceiptStatus;
use App\Enums\Shipping\Scenario;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\MockProviderShipment;
use App\Models\Operation;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProviderSubmission;
use App\Models\ProviderWebhookReceipt;
use App\Models\Shipment;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

it('prepares deterministic scenarios through the application services', function () {
    $this->artisan('demo:inventory-scenarios')
        ->expectsOutputToContain('Deterministic inventory demonstration scenarios are ready.')
        ->assertSuccessful();

    $partialOrder = Order::query()
        ->where('order_number', 'DEMO-PARTIAL-001')
        ->with('items.reservations')
        ->sole();
    $partialItem = $partialOrder->items->sole();
    $partialReservation = $partialItem->reservations->sole();

    expect($partialItem->ordered_quantity)->toBe(10)
        ->and($partialItem->reserved_quantity)->toBe(6)
        ->and($partialReservation->requested_quantity)->toBe(10)
        ->and($partialReservation->reserved_quantity)->toBe(6)
        ->and(
            ProviderSubmission::query()
                ->where('status', SubmissionStatus::Unknown->value)
                ->count(),
        )->toBe(1)
        ->and(
            ProviderSubmission::query()
                ->where('status', SubmissionStatus::PermanentlyFailed->value)
                ->count(),
        )->toBe(1)
        ->and(
            MockProviderShipment::query()
                ->where('scenario', Scenario::SuccessWithDuplicateDelivery->value)
                ->count(),
        )->toBe(1)
        ->and(
            ProviderWebhookReceipt::query()
                ->where('status', ReceiptStatus::Pending->value)
                ->count(),
        )->toBe(1)
        ->and(
            Shipment::query()
                ->whereHas('providerSubmissions')
                ->count(),
        )->toBe(5);

    $demoProductIds = Product::query()
        ->where('sku', 'like', 'DEMO-%')
        ->pluck('id');

    foreach (InventoryBalance::query()->whereIn('product_id', $demoProductIds)->get() as $balance) {
        foreach (['available', 'reserved', 'picked', 'packed'] as $bucket) {
            $incoming = (int) InventoryMovement::query()
                ->where('product_id', $balance->product_id)
                ->where('destination_warehouse_id', $balance->warehouse_id)
                ->where('destination_bucket', $bucket)
                ->sum('quantity');
            $outgoing = (int) InventoryMovement::query()
                ->where('product_id', $balance->product_id)
                ->where('source_warehouse_id', $balance->warehouse_id)
                ->where('source_bucket', $bucket)
                ->sum('quantity');

            expect($balance->getAttribute($bucket.'_quantity'))
                ->toBe($incoming - $outgoing);
        }
    }

    $counts = [
        Order::query()->where('order_number', 'like', 'DEMO-%')->count(),
        Operation::query()->where('idempotency_key', 'like', 'demo:%')->count(),
        InventoryMovement::query()->whereIn('product_id', $demoProductIds)->count(),
    ];

    $this->artisan('demo:inventory-scenarios')->assertSuccessful();

    expect([
        Order::query()->where('order_number', 'like', 'DEMO-%')->count(),
        Operation::query()->where('idempotency_key', 'like', 'demo:%')->count(),
        InventoryMovement::query()->whereIn('product_id', $demoProductIds)->count(),
    ])->toBe($counts);
});

it('resets only demo records before rebuilding them', function () {
    $unrelatedOrder = Order::factory()->create();

    $this->artisan('demo:inventory-scenarios')->assertSuccessful();
    $this->artisan('demo:inventory-scenarios', [
        '--reset' => true,
        '--force' => true,
    ])->assertSuccessful();

    expect($unrelatedOrder->fresh())->not->toBeNull()
        ->and(Order::query()->where('order_number', 'like', 'DEMO-%')->count())
        ->toBe(6);
});

it('registers both demo commands and rejects them outside local or testing', function () {
    expect(Artisan::all())->toHaveKeys([
        'demo:concurrent-reservation',
        'demo:inventory-scenarios',
    ]);

    app()->detectEnvironment(fn (): string => 'production');

    try {
        $this->artisan('demo:inventory-scenarios')
            ->expectsOutput(
                'Demonstration scenarios are available only in local and testing environments.',
            )
            ->assertFailed();
        $this->artisan('demo:concurrent-reservation')
            ->expectsOutput(
                'Demonstration scenarios are available only in local and testing environments.',
            )
            ->assertFailed();
    } finally {
        app()->detectEnvironment(fn (): string => 'testing');
    }

    expect(DB::table('orders')->doesntExist())->toBeTrue();
});
