<?php

use App\Enums\ProviderSubmissions\Status;
use App\Models\ProviderSubmission;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use App\Services\Shipping\ShipmentSubmissionService;

it('durably reuses one active provider submission and stable request key', function () {
    $shipment = Shipment::factory()->create();
    ShipmentItem::factory()->for($shipment)->create(['quantity' => 3]);
    $service = app(ShipmentSubmissionService::class);

    $first = $service->prepare($shipment->id);
    $replayed = $service->prepare($shipment->id);
    $submission = ProviderSubmission::query()->sole();

    expect($replayed)->toEqual($first)
        ->and($submission->id)->toBe($first->providerSubmissionId)
        ->and($submission->status)->toBe(Status::Pending)
        ->and($submission->provider_request_key)
        ->toBe($first->providerRequest->providerRequestKey)
        ->and($first->providerRequest->shipmentReference)->toBe((string) $shipment->id)
        ->and(ProviderSubmission::query()->count())->toBe(1);
});
