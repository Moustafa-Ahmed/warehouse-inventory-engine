<?php

use App\Enums\MockProviderShipments\Status as MockShipmentStatus;
use App\Enums\MockProviderWebhooks\Status as WebhookStatus;
use App\Enums\ProviderSubmissions\Status as SubmissionStatus;
use App\Enums\Shipping\EventType;
use App\Enums\Shipping\Scenario;
use App\Jobs\ReconcileProviderSubmissionJob;
use App\Models\InventoryMovement;
use App\Models\MockProviderShipment;
use App\Models\MockProviderWebhook;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use App\Services\Shipping\PersistentMockProvider;
use App\Services\Shipping\ShipmentSubmissionService;
use Illuminate\Support\Facades\Queue;

it('reconciles timeout acceptance and redelivers the existing confirmation callback', function () {
    $shipment = Shipment::factory()->create();
    ShipmentItem::factory()->for($shipment)->create(['quantity' => 2]);
    $provider = new PersistentMockProvider(Scenario::TimeoutThenSuccess);
    $service = new ShipmentSubmissionService($provider);
    $submissionResult = $service->submit($shipment->id);
    $submission = $shipment->providerSubmissions()->sole();
    $mockShipment = MockProviderShipment::query()->sole();
    $webhook = MockProviderWebhook::query()->sole();
    $externalShipmentId = $mockShipment->external_shipment_id;
    $rawBody = $webhook->raw_body;

    expect($submissionResult->outcome->value)->toBe('unknown')
        ->and($submission->status)->toBe(SubmissionStatus::Unknown);

    Queue::fake();
    $this->artisan('provider-submissions:reconcile-unknown', ['--limit' => 10])
        ->expectsOutput('Dispatched 1 reconciliation job(s).')
        ->assertSuccessful();
    Queue::assertPushed(
        ReconcileProviderSubmissionJob::class,
        fn (ReconcileProviderSubmissionJob $job): bool => $job->providerSubmissionId === $submission->id,
    );

    $mockShipment->forceFill([
        'status' => MockShipmentStatus::HandoffConfirmed,
        'handoff_confirmed_at' => now(),
    ])->save();
    $webhook->forceFill([
        'status' => WebhookStatus::PermanentlyFailed,
        'attempt_count' => 2,
        'next_delivery_at' => now()->addHour(),
        'last_response_status_code' => 401,
        'failure_reason' => 'Previous delivery failed.',
    ])->save();

    $result = $service->reconcile($submission->id);

    expect($result?->latestConfirmedEvent)->toBe(EventType::ShipmentConfirmed)
        ->and($submission->refresh()->status)->toBe(SubmissionStatus::Accepted)
        ->and($submission->external_shipment_id)->toBe($externalShipmentId)
        ->and($webhook->refresh()->status)->toBe(WebhookStatus::Pending)
        ->and($webhook->raw_body)->toBe($rawBody)
        ->and($webhook->attempt_count)->toBe(2)
        ->and($webhook->next_delivery_at->lte(now()))->toBeTrue()
        ->and(MockProviderShipment::query()->count())->toBe(1)
        ->and(MockProviderWebhook::query()->count())->toBe(1)
        ->and($shipment->refresh()->status->value)->toBe('pending_handoff')
        ->and(InventoryMovement::query()->doesntExist())->toBeTrue();

    $prepared = $service->prepare($shipment->id);
    $provider->submit($prepared->providerRequest);

    expect(MockProviderShipment::query()->count())->toBe(1)
        ->and(MockProviderShipment::query()->sole()->external_shipment_id)
        ->toBe($externalShipmentId);
});
