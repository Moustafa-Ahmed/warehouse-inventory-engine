<?php

use App\Enums\MockProviderShipments\Status as MockProviderShipmentStatus;
use App\Enums\MockProviderWebhooks\Status as MockProviderWebhookStatus;
use App\Enums\ProviderWebhookReceipts\Status as ProviderWebhookReceiptStatus;
use App\Enums\Shipping\EventType;
use App\Enums\Shipping\Scenario;
use App\Models\MockProviderShipment;
use App\Models\MockProviderWebhook;
use App\Models\ProviderWebhookReceipt;
use Illuminate\Database\UniqueConstraintViolationException;

it('creates valid mock-provider reliability records for each factory state', function () {
    $forcedShipment = MockProviderShipment::factory()->forced(Scenario::DelayedSuccess)->create();
    $rejectedShipment = MockProviderShipment::factory()->permanentlyRejected()->create();
    $confirmedShipment = MockProviderShipment::factory()->handoffConfirmed()->create();
    $deliveredShipment = MockProviderShipment::factory()->delivered()->create();

    expect($forcedShipment->scenario)->toBe(Scenario::DelayedSuccess)
        ->and($forcedShipment->scenario_was_forced)->toBeTrue()
        ->and($rejectedShipment->status)->toBe(MockProviderShipmentStatus::PermanentlyRejected)
        ->and($rejectedShipment->external_shipment_id)->toBeNull()
        ->and($rejectedShipment->rejected_at)->not->toBeNull()
        ->and($confirmedShipment->status)->toBe(MockProviderShipmentStatus::HandoffConfirmed)
        ->and($confirmedShipment->handoff_confirmed_at)->not->toBeNull()
        ->and($deliveredShipment->status)->toBe(MockProviderShipmentStatus::Delivered)
        ->and($deliveredShipment->delivered_at)->not->toBeNull();

    $deliveryWebhook = MockProviderWebhook::factory()
        ->for($forcedShipment)
        ->deliveryConfirmation()
        ->create();
    $deliveringWebhook = MockProviderWebhook::factory()->delivering()->create();
    $retryWebhook = MockProviderWebhook::factory()->retryScheduled(2)->create();
    $acknowledgedWebhook = MockProviderWebhook::factory()->acknowledged()->create();
    $failedWebhook = MockProviderWebhook::factory()->permanentlyFailed()->create();
    $deliveryPayload = json_decode($deliveryWebhook->raw_body, true, flags: JSON_THROW_ON_ERROR);

    expect($deliveryWebhook->event_type)->toBe(EventType::DeliveryConfirmed)
        ->and($deliveryPayload['external_shipment_id'])->toBe($forcedShipment->external_shipment_id)
        ->and($deliveryPayload['provider_request_key'])->toBe($forcedShipment->provider_request_key)
        ->and($deliveringWebhook->status)->toBe(MockProviderWebhookStatus::Delivering)
        ->and($retryWebhook->status)->toBe(MockProviderWebhookStatus::RetryScheduled)
        ->and($retryWebhook->attempt_count)->toBe(2)
        ->and($acknowledgedWebhook->status)->toBe(MockProviderWebhookStatus::Acknowledged)
        ->and($acknowledgedWebhook->acknowledged_at)->not->toBeNull()
        ->and($failedWebhook->status)->toBe(MockProviderWebhookStatus::PermanentlyFailed);

    $deliveryReceipt = ProviderWebhookReceipt::factory()->deliveryConfirmation()->create();
    $processedReceipt = ProviderWebhookReceipt::factory()->processed()->create();
    $staleReceipt = ProviderWebhookReceipt::factory()->ignoredAsStale()->create();
    $retryableReceipt = ProviderWebhookReceipt::factory()->retryableFailure()->create();
    $failedReceipt = ProviderWebhookReceipt::factory()->permanentlyFailed()->create();
    $deliveryReceiptPayload = json_decode($deliveryReceipt->raw_body, true, flags: JSON_THROW_ON_ERROR);

    expect($deliveryReceipt->event_type)->toBe(EventType::DeliveryConfirmed)
        ->and($deliveryReceiptPayload['external_event_id'])->toBe($deliveryReceipt->external_event_id)
        ->and($deliveryReceiptPayload['event_type'])->toBe(EventType::DeliveryConfirmed->value)
        ->and($deliveryReceiptPayload['external_shipment_id'])
        ->toBe('mock-'.hash('sha256', $deliveryReceiptPayload['provider_request_key']))
        ->and($processedReceipt->status)->toBe(ProviderWebhookReceiptStatus::Processed)
        ->and($processedReceipt->processed_at)->not->toBeNull()
        ->and($staleReceipt->status)->toBe(ProviderWebhookReceiptStatus::IgnoredAsStale)
        ->and($staleReceipt->processed_at)->not->toBeNull()
        ->and($retryableReceipt->status)->toBe(ProviderWebhookReceiptStatus::RetryableFailure)
        ->and($retryableReceipt->processed_at)->toBeNull()
        ->and($failedReceipt->status)->toBe(ProviderWebhookReceiptStatus::PermanentlyFailed)
        ->and($failedReceipt->processed_at)->toBeNull();
});

it('rejects a second mock-provider webhook row with the same external event identity', function () {
    $webhook = MockProviderWebhook::factory()->create();

    expect(fn () => MockProviderWebhook::factory()
        ->for($webhook->mockProviderShipment)
        ->create(['external_event_id' => $webhook->external_event_id]))
        ->toThrow(UniqueConstraintViolationException::class);
});
