<?php

use App\Enums\MockProviderShipments\Status as MockShipmentStatus;
use App\Enums\MockProviderWebhooks\Status as MockWebhookStatus;
use App\Enums\Shipping\EventType;
use App\Enums\Shipping\Outcome;
use App\Enums\Shipping\Scenario;
use App\Models\MockProviderScenarioOverride;
use App\Models\MockProviderShipment;
use App\Models\MockProviderWebhook;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use App\Services\Shipping\InMemoryProvider;
use App\Services\Shipping\MockProviderControlService;
use App\Services\Shipping\ShipmentSubmissionService;
use Illuminate\Support\Facades\Http;

it('consumes a durable one-shot scenario override during provider submission', function () {
    $shipment = Shipment::factory()->create();
    ShipmentItem::factory()->for($shipment)->create();
    $controls = app(MockProviderControlService::class);

    $controls->setNextScenario($shipment->id, Scenario::PermanentFailure);

    expect(MockProviderScenarioOverride::query()->sole()->scenario)
        ->toBe(Scenario::PermanentFailure);

    $result = app(ShipmentSubmissionService::class)->submit($shipment->id);
    $mockShipment = MockProviderShipment::query()->sole();

    expect($result->outcome)->toBe(Outcome::PermanentlyFailed)
        ->and($mockShipment->scenario)->toBe(Scenario::PermanentFailure)
        ->and($mockShipment->scenario_was_forced)->toBeTrue()
        ->and($mockShipment->status)->toBe(MockShipmentStatus::PermanentlyRejected)
        ->and(MockProviderScenarioOverride::query()->doesntExist())->toBeTrue();
});

it('sends, replays, and deliberately reorders callbacks through mock-provider state', function () {
    config()->set(
        'shipping.mock_provider.webhook_url',
        'https://warehouse.test/webhooks/shipping-provider',
    );
    config()->set('shipping.webhook.providers.mock.secret', 'test-secret');
    Http::fake([
        '*' => Http::response(['receipt_id' => 1], 202),
    ]);
    $shipment = Shipment::factory()->create();
    ShipmentItem::factory()->for($shipment)->create(['quantity' => 2]);
    $mockShipment = MockProviderShipment::factory()->create([
        'shipment_reference' => (string) $shipment->id,
    ]);

    $this->artisan('mock-provider:send-webhook', [
        'mockProviderShipmentId' => $mockShipment->id,
        'eventType' => EventType::DeliveryConfirmed->value,
    ])->assertFailed();

    expect(MockProviderWebhook::query()->doesntExist())->toBeTrue();

    $this->artisan('mock-provider:send-webhook', [
        'mockProviderShipmentId' => $mockShipment->id,
        'eventType' => EventType::ShipmentConfirmed->value,
    ])->assertSuccessful();

    $handoffWebhook = MockProviderWebhook::query()->sole();

    expect($mockShipment->refresh()->status)->toBe(MockShipmentStatus::HandoffConfirmed)
        ->and($handoffWebhook->status)->toBe(MockWebhookStatus::Acknowledged)
        ->and($handoffWebhook->attempt_count)->toBe(1);

    $this->artisan('mock-provider:send-webhook', [
        'mockProviderShipmentId' => $mockShipment->id,
        'eventType' => EventType::DeliveryConfirmed->value,
    ])->assertSuccessful();

    $deliveryWebhook = MockProviderWebhook::query()
        ->where('event_type', EventType::DeliveryConfirmed->value)
        ->sole();
    $originalEventId = $deliveryWebhook->external_event_id;
    $originalRawBody = $deliveryWebhook->raw_body;

    expect($mockShipment->refresh()->status)->toBe(MockShipmentStatus::Delivered)
        ->and($deliveryWebhook->status)->toBe(MockWebhookStatus::Acknowledged)
        ->and($deliveryWebhook->attempt_count)->toBe(1)
        ->and(MockProviderWebhook::query()->count())->toBe(2);

    $this->artisan('mock-provider:replay-webhook', [
        'mockProviderShipmentId' => $mockShipment->id,
    ])->assertSuccessful();

    expect($deliveryWebhook->refresh()->external_event_id)->toBe($originalEventId)
        ->and($deliveryWebhook->raw_body)->toBe($originalRawBody)
        ->and($deliveryWebhook->attempt_count)->toBe(2)
        ->and(MockProviderWebhook::query()->count())->toBe(2);

    $outOfOrderShipment = Shipment::factory()->create();
    ShipmentItem::factory()->for($outOfOrderShipment)->create();
    $outOfOrderMockShipment = MockProviderShipment::factory()->create([
        'shipment_reference' => (string) $outOfOrderShipment->id,
    ]);

    $this->artisan('mock-provider:send-webhook', [
        'mockProviderShipmentId' => $outOfOrderMockShipment->id,
        'eventType' => EventType::DeliveryConfirmed->value,
        '--out-of-order' => true,
    ])->assertSuccessful();

    expect($outOfOrderMockShipment->refresh()->status)->toBe(MockShipmentStatus::Delivered)
        ->and($outOfOrderMockShipment->handoff_confirmed_at)->toBeNull()
        ->and($outOfOrderMockShipment->webhooks()->sole()->event_type)
        ->toBe(EventType::DeliveryConfirmed);

    Http::assertSentCount(4);
});

it('rejects controls outside allowed environments and with another provider adapter', function () {
    $controls = app(MockProviderControlService::class);
    app()->detectEnvironment(fn (): string => 'production');

    try {
        expect(fn () => $controls->setNextScenario(1, Scenario::ImmediateSuccess))
            ->toThrow(
                LogicException::class,
                'Mock-provider controls are available only in local and testing environments.',
            );
    } finally {
        app()->detectEnvironment(fn (): string => 'testing');
    }

    $controlsWithAnotherAdapter = new MockProviderControlService(
        new InMemoryProvider,
    );

    expect(fn () => $controlsWithAnotherAdapter->setNextScenario(
        1,
        Scenario::ImmediateSuccess,
    ))->toThrow(
        LogicException::class,
        'Mock-provider controls require the persistent mock provider.',
    );
});
