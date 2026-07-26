<?php

use App\Enums\ProviderSubmissions\Status;
use App\Enums\Shipping\Scenario;
use App\Jobs\SubmitShipmentJob;
use App\Models\ProviderSubmission;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use App\Services\Shipping\InMemoryProvider;
use App\Services\Shipping\ShipmentSubmissionService;
use Illuminate\Support\Facades\Queue;

it('processes eligible shipments through a repeat-safe thin job and command', function () {
    $acceptedShipment = Shipment::factory()->create();
    ShipmentItem::factory()->for($acceptedShipment)->create(['quantity' => 2]);
    $failedShipment = Shipment::factory()->create();
    ShipmentItem::factory()->for($failedShipment)->create(['quantity' => 1]);
    Shipment::factory()->shipped()->create();
    Queue::fake();

    $this->artisan('shipments:process-pending', ['--limit' => 10])
        ->expectsOutput('Dispatched 2 shipment(s).')
        ->assertSuccessful();

    Queue::assertPushed(SubmitShipmentJob::class, 2);

    $acceptedProvider = new InMemoryProvider(Scenario::ImmediateSuccess);
    $acceptedJob = new SubmitShipmentJob($acceptedShipment->id);
    $acceptedService = new ShipmentSubmissionService($acceptedProvider);
    $acceptedJob->handle($acceptedService);
    $acceptedJob->handle($acceptedService);

    (new SubmitShipmentJob($failedShipment->id))->handle(
        new ShipmentSubmissionService(
            new InMemoryProvider(Scenario::PermanentFailure),
        ),
    );

    expect(
        $acceptedShipment->providerSubmissions()->sole()->status
    )->toBe(Status::Accepted)
        ->and($failedShipment->providerSubmissions()->sole()->status)
        ->toBe(Status::PermanentlyFailed)
        ->and(ProviderSubmission::query()->count())->toBe(2)
        ->and(config('queue.connections.database.retry_after'))
        ->toBeGreaterThan($acceptedJob->timeout);

    Queue::fake();

    $this->artisan('shipments:process-pending', ['--limit' => 10])
        ->expectsOutput('Dispatched 0 shipment(s).')
        ->assertSuccessful();

    Queue::assertNothingPushed();
});
