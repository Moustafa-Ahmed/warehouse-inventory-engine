<?php

use App\DTOs\Shipping\Request;
use App\DTOs\Shipping\RequestItem;
use App\Enums\MockProviderShipments\Status;
use App\Enums\Shipping\CallbackIntent;
use App\Enums\Shipping\EventType;
use App\Enums\Shipping\Outcome;
use App\Enums\Shipping\Scenario;
use App\Models\MockProviderShipment;
use App\Models\MockProviderWebhook;
use App\Services\Shipping\PersistentMockProvider;

it('persists stable provider identities, outcomes, and webhook intents', function (
    Scenario $scenario,
    Status $providerStatus,
    Outcome $submissionOutcome,
    Outcome $statusOutcome,
    ?CallbackIntent $callbackIntent,
    array $expectedEventTypes,
) {
    $provider = new PersistentMockProvider($scenario);
    $request = new Request(
        providerRequestKey: 'provider-request-'.$scenario->value,
        shipmentReference: 'shipment-42',
        items: [
            new RequestItem(shipmentItemId: 12, quantity: 3),
            new RequestItem(shipmentItemId: 13, quantity: 2),
        ],
    );

    $first = $provider->submit($request);
    $replayed = $provider->submit($request);
    $status = $provider->statusFor($request->providerRequestKey);
    $mockShipment = MockProviderShipment::query()->sole();
    $webhooks = MockProviderWebhook::query()
        ->orderBy('next_delivery_at')
        ->orderBy('id')
        ->get();

    expect($replayed)->toEqual($first)
        ->and($first->outcome)->toBe($submissionOutcome)
        ->and($first->callbackIntent)->toBe($callbackIntent)
        ->and($status?->outcome)->toBe($statusOutcome)
        ->and($status?->callbackIntent)->toBe($callbackIntent)
        ->and($mockShipment->status)->toBe($providerStatus)
        ->and($mockShipment->scenario)->toBe($scenario)
        ->and($mockShipment->scenario_was_forced)->toBeTrue()
        ->and(MockProviderShipment::query()->count())->toBe(1)
        ->and($webhooks)->toHaveCount(count($expectedEventTypes))
        ->and($webhooks->pluck('event_type')->all())->toBe($expectedEventTypes);

    if ($scenario === Scenario::PermanentFailure) {
        expect($first->externalShipmentId)->toBeNull()
            ->and($status?->externalShipmentId)->toBeNull();
    } elseif ($scenario === Scenario::TimeoutThenSuccess) {
        expect($first->externalShipmentId)->toBeNull()
            ->and($status?->externalShipmentId)
            ->toBe('mock-'.hash('sha256', $request->providerRequestKey));
    } else {
        expect($first->externalShipmentId)
            ->toBe('mock-'.hash('sha256', $request->providerRequestKey))
            ->and($status?->externalShipmentId)->toBe($first->externalShipmentId);
    }

    foreach ($webhooks as $webhook) {
        $payload = json_decode($webhook->raw_body, true, flags: JSON_THROW_ON_ERROR);

        expect($payload['external_event_id'])->toBe($webhook->external_event_id)
            ->and($payload['event_type'])->toBe($webhook->event_type->value)
            ->and($payload['external_shipment_id'])->toBe($mockShipment->external_shipment_id)
            ->and($payload['provider_request_key'])->toBe($request->providerRequestKey)
            ->and($payload['items'])->toBe([
                ['shipment_item_id' => 12, 'quantity' => 3],
                ['shipment_item_id' => 13, 'quantity' => 2],
            ]);
    }
})->with([
    'immediate success' => [
        Scenario::ImmediateSuccess,
        Status::Accepted,
        Outcome::Accepted,
        Outcome::Accepted,
        CallbackIntent::Immediate,
        [EventType::ShipmentConfirmed],
    ],
    'delayed success' => [
        Scenario::DelayedSuccess,
        Status::Accepted,
        Outcome::Accepted,
        Outcome::Accepted,
        CallbackIntent::Delayed,
        [EventType::ShipmentConfirmed],
    ],
    'permanent failure' => [
        Scenario::PermanentFailure,
        Status::PermanentlyRejected,
        Outcome::PermanentlyFailed,
        Outcome::PermanentlyFailed,
        null,
        [],
    ],
    'timeout after acceptance' => [
        Scenario::TimeoutThenSuccess,
        Status::Accepted,
        Outcome::Unknown,
        Outcome::Accepted,
        CallbackIntent::Delayed,
        [EventType::ShipmentConfirmed],
    ],
    'duplicate delivery' => [
        Scenario::SuccessWithDuplicateDelivery,
        Status::Accepted,
        Outcome::Accepted,
        Outcome::Accepted,
        CallbackIntent::Duplicate,
        [EventType::ShipmentConfirmed, EventType::DeliveryConfirmed],
    ],
    'out-of-order delivery' => [
        Scenario::OutOfOrderDelivery,
        Status::Accepted,
        Outcome::Accepted,
        Outcome::Accepted,
        CallbackIntent::OutOfOrder,
        [EventType::DeliveryConfirmed, EventType::ShipmentConfirmed],
    ],
]);

it('uses configurable weighted selection when no scenario is forced', function () {
    config()->set('shipping.mock_provider.scenario_weights', [
        Scenario::ImmediateSuccess->value => 0,
        Scenario::DelayedSuccess->value => 1,
        Scenario::PermanentFailure->value => 0,
        Scenario::TimeoutThenSuccess->value => 0,
        Scenario::SuccessWithDuplicateDelivery->value => 0,
        Scenario::OutOfOrderDelivery->value => 0,
    ]);
    $provider = new PersistentMockProvider;

    $provider->submit(new Request(
        providerRequestKey: 'weighted-provider-request',
        shipmentReference: 'shipment-99',
    ));

    $shipment = MockProviderShipment::query()->sole();

    expect($shipment->scenario)->toBe(Scenario::DelayedSuccess)
        ->and($shipment->scenario_was_forced)->toBeFalse();
});
