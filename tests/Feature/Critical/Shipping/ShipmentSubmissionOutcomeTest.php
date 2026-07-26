<?php

use App\Contracts\ShippingProvider;
use App\DTOs\Shipping\Request;
use App\DTOs\Shipping\Result;
use App\Enums\ProviderSubmissions\Status;
use App\Enums\Shipments\Status as ShipmentStatus;
use App\Enums\Shipping\Outcome;
use App\Enums\Shipping\Scenario;
use App\Models\InventoryMovement;
use App\Models\ProviderSubmission;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use App\Services\Shipping\InMemoryProvider;
use App\Services\Shipping\ShipmentSubmissionService;
use Illuminate\Support\Facades\DB;

it('records provider outcomes without calling the provider inside a transaction', function (
    Scenario $scenario,
    Outcome $expectedOutcome,
    Status $expectedStatus,
) {
    $shipment = Shipment::factory()->create();
    ShipmentItem::factory()->for($shipment)->create(['quantity' => 2]);
    $provider = new class($scenario) implements ShippingProvider
    {
        /** @var list<int> */
        public array $transactionLevels = [];

        private InMemoryProvider $provider;

        public function __construct(Scenario $scenario)
        {
            $this->provider = new InMemoryProvider($scenario);
        }

        public function submit(Request $request): Result
        {
            $this->transactionLevels[] = DB::connection()->transactionLevel();

            return $this->provider->submit($request);
        }

        public function statusFor(string $providerRequestKey): ?Result
        {
            return $this->provider->statusFor($providerRequestKey);
        }
    };
    $service = new ShipmentSubmissionService($provider);

    $result = $service->submit($shipment->id);
    $submission = ProviderSubmission::query()->sole();

    expect($result->outcome)->toBe($expectedOutcome)
        ->and($submission->status)->toBe($expectedStatus)
        ->and($submission->last_attempted_at)->not->toBeNull()
        ->and($provider->transactionLevels)->toBe([0])
        ->and($shipment->refresh()->status)->toBe(ShipmentStatus::PendingHandoff)
        ->and(InventoryMovement::query()->doesntExist())->toBeTrue();
})->with([
    'accepted' => [
        Scenario::ImmediateSuccess,
        Outcome::Accepted,
        Status::Accepted,
    ],
    'unknown after timeout' => [
        Scenario::TimeoutThenSuccess,
        Outcome::Unknown,
        Status::Unknown,
    ],
    'permanently failed' => [
        Scenario::PermanentFailure,
        Outcome::PermanentlyFailed,
        Status::PermanentlyFailed,
    ],
]);
