<?php

use App\DTOs\Shipping\Request;
use App\Enums\Shipping\CallbackIntent;
use App\Enums\Shipping\Outcome;
use App\Enums\Shipping\Scenario;
use App\Services\Shipping\InMemoryProvider;

it('maps deterministic scenarios to response and provider outcomes', function (
    Scenario $scenario,
    Outcome $responseOutcome,
    Outcome $providerOutcome,
    ?CallbackIntent $callbackIntent,
    bool $responseIncludesExternalShipment,
    bool $providerCreatedExternalShipment,
) {
    $provider = new InMemoryProvider($scenario);

    $request = new Request(
        providerRequestKey: 'provider-request-'.$scenario->value,
        shipmentReference: 'shipment-001',
    );

    $submission = $provider->submit($request);
    $status = $provider->statusFor($request->providerRequestKey);

    expect($submission->outcome)->toBe($responseOutcome)
        ->and($submission->callbackIntent)->toBe($callbackIntent)
        ->and($submission->externalShipmentId !== null)->toBe($responseIncludesExternalShipment)
        ->and($status)->not->toBeNull()
        ->and($status->outcome)->toBe($providerOutcome)
        ->and($status->callbackIntent)->toBe($callbackIntent)
        ->and($status->externalShipmentId !== null)->toBe($providerCreatedExternalShipment);

    if ($responseIncludesExternalShipment) {
        expect($status->externalShipmentId)->toBe($submission->externalShipmentId);
    }
})->with([
    'immediate success' => [
        Scenario::ImmediateSuccess,
        Outcome::Accepted,
        Outcome::Accepted,
        CallbackIntent::Immediate,
        true,
        true,
    ],
    'delayed success' => [
        Scenario::DelayedSuccess,
        Outcome::Accepted,
        Outcome::Accepted,
        CallbackIntent::Delayed,
        true,
        true,
    ],
    'permanent failure' => [
        Scenario::PermanentFailure,
        Outcome::PermanentlyFailed,
        Outcome::PermanentlyFailed,
        null,
        false,
        false,
    ],
    'timeout after provider acceptance' => [
        Scenario::TimeoutThenSuccess,
        Outcome::Unknown,
        Outcome::Accepted,
        CallbackIntent::Delayed,
        false,
        true,
    ],
    'duplicate callback intent' => [
        Scenario::SuccessWithDuplicateDelivery,
        Outcome::Accepted,
        Outcome::Accepted,
        CallbackIntent::Duplicate,
        true,
        true,
    ],
    'out-of-order callback intent' => [
        Scenario::OutOfOrderDelivery,
        Outcome::Accepted,
        Outcome::Accepted,
        CallbackIntent::OutOfOrder,
        true,
        true,
    ],
]);

it('reuses the first result for a repeated stable request key', function () {
    $provider = new InMemoryProvider(Scenario::DelayedSuccess);

    $request = new Request(
        providerRequestKey: 'provider-request-reused',
        shipmentReference: 'shipment-001',
    );

    $firstSubmission = $provider->submit($request);
    $secondSubmission = $provider->submit($request);

    expect($secondSubmission)->toBe($firstSubmission)
        ->and($firstSubmission->externalShipmentId)
        ->toBe('mock-'.hash('sha256', $request->providerRequestKey))
        ->and($provider->statusFor($request->providerRequestKey)?->externalShipmentId)
        ->toBe($firstSubmission->externalShipmentId);
});
