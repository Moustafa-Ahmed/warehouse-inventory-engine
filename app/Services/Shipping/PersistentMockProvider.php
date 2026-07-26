<?php

namespace App\Services\Shipping;

use App\Contracts\ShippingProvider;
use App\DTOs\Shipping\Request;
use App\DTOs\Shipping\RequestItem;
use App\DTOs\Shipping\Result;
use App\Enums\MockProviderShipments\Status;
use App\Enums\MockProviderWebhooks\Status as WebhookStatus;
use App\Enums\Shipping\EventType;
use App\Enums\Shipping\Outcome;
use App\Enums\Shipping\Scenario;
use App\Models\MockProviderScenarioOverride;
use App\Models\MockProviderShipment;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

final class PersistentMockProvider implements ShippingProvider
{
    public function __construct(
        private readonly ?Scenario $forcedScenario = null,
    ) {}

    public function submit(Request $request): Result
    {
        $shipment = MockProviderShipment::query()
            ->where('provider_request_key', $request->providerRequestKey)
            ->first();

        if ($shipment === null) {
            try {
                $shipment = DB::transaction(
                    fn (): MockProviderShipment => $this->createShipment($request),
                    attempts: 3,
                );
            } catch (UniqueConstraintViolationException) {
                $shipment = MockProviderShipment::query()
                    ->where('provider_request_key', $request->providerRequestKey)
                    ->firstOrFail();
            }
        }

        $this->ensureRequestMatches($shipment, $request);

        return $this->resultFor($shipment, responseResult: true);
    }

    public function statusFor(string $providerRequestKey): ?Result
    {
        $shipment = MockProviderShipment::query()
            ->where('provider_request_key', $providerRequestKey)
            ->first();

        return $shipment === null
            ? null
            : $this->resultFor($shipment, responseResult: false);
    }

    public function requestHandoffConfirmationRedelivery(string $providerRequestKey): void
    {
        DB::transaction(function () use ($providerRequestKey): void {
            $shipment = MockProviderShipment::query()
                ->where('provider_request_key', $providerRequestKey)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($shipment->status, [
                Status::HandoffConfirmed,
                Status::Delivered,
            ], true)) {
                return;
            }

            $webhook = $shipment->webhooks()
                ->where('event_type', EventType::ShipmentConfirmed->value)
                ->lockForUpdate()
                ->first();

            if ($webhook === null || $webhook->status === WebhookStatus::Acknowledged) {
                return;
            }

            $webhook->forceFill([
                'status' => WebhookStatus::Pending,
                'next_delivery_at' => now(),
                'acknowledged_at' => null,
                'last_response_status_code' => null,
                'failure_reason' => null,
            ])->save();
        }, attempts: 3);
    }

    private function createShipment(Request $request): MockProviderShipment
    {
        $override = MockProviderScenarioOverride::query()
            ->where('shipment_reference', $request->shipmentReference)
            ->lockForUpdate()
            ->first();
        $scenario = $this->forcedScenario
            ?? $override?->scenario
            ?? $this->selectWeightedScenario();
        $scenarioWasForced = $this->forcedScenario !== null || $override !== null;
        $permanentlyRejected = $scenario === Scenario::PermanentFailure;
        $shipment = new MockProviderShipment([
            'provider_request_key' => $request->providerRequestKey,
            'external_shipment_id' => $permanentlyRejected
                ? null
                : 'mock-'.hash('sha256', $request->providerRequestKey),
            'shipment_reference' => $request->shipmentReference,
            'scenario' => $scenario,
            'scenario_was_forced' => $scenarioWasForced,
        ]);
        $shipment->forceFill([
            'status' => $permanentlyRejected
                ? Status::PermanentlyRejected
                : Status::Accepted,
            'failure_reason' => $permanentlyRejected
                ? 'Provider permanently rejected the shipment.'
                : null,
            'accepted_at' => $permanentlyRejected ? null : now(),
            'rejected_at' => $permanentlyRejected ? now() : null,
            'handoff_confirmed_at' => null,
            'delivered_at' => null,
        ])->save();

        $this->persistWebhookIntents($shipment, $request);
        $override?->delete();

        return $shipment;
    }

    private function ensureRequestMatches(
        MockProviderShipment $shipment,
        Request $request,
    ): void {
        if (! hash_equals($shipment->shipment_reference, $request->shipmentReference)) {
            throw new InvalidArgumentException(
                'The provider request key is already assigned to another shipment.'
            );
        }
    }

    private function resultFor(
        MockProviderShipment $shipment,
        bool $responseResult,
    ): Result {
        $outcome = $shipment->status === Status::PermanentlyRejected
            ? Outcome::PermanentlyFailed
            : Outcome::Accepted;

        if ($responseResult && $shipment->scenario === Scenario::TimeoutThenSuccess) {
            $outcome = Outcome::Unknown;
        }

        return new Result(
            providerRequestKey: $shipment->provider_request_key,
            externalShipmentId: $responseResult
                && $shipment->scenario === Scenario::TimeoutThenSuccess
                    ? null
                    : $shipment->external_shipment_id,
            outcome: $outcome,
            callbackIntent: $shipment->scenario->callbackIntent(),
            latestConfirmedEvent: match ($shipment->status) {
                Status::HandoffConfirmed => EventType::ShipmentConfirmed,
                Status::Delivered => EventType::DeliveryConfirmed,
                default => null,
            },
        );
    }

    private function persistWebhookIntents(
        MockProviderShipment $shipment,
        Request $request,
    ): void {
        $delaySeconds = max(1, (int) config('shipping.mock_provider.callback_delay_seconds'));
        $now = now();

        switch ($shipment->scenario) {
            case Scenario::ImmediateSuccess:
                $this->createWebhook(
                    $shipment,
                    $request,
                    EventType::ShipmentConfirmed,
                    $now,
                );
                break;
            case Scenario::DelayedSuccess:
            case Scenario::TimeoutThenSuccess:
                $this->createWebhook(
                    $shipment,
                    $request,
                    EventType::ShipmentConfirmed,
                    $now->copy()->addSeconds($delaySeconds),
                );
                break;
            case Scenario::SuccessWithDuplicateDelivery:
                $this->createConfirmationAndDeliveryWebhooks(
                    $shipment,
                    $request,
                    $now,
                    $now->copy()->addSeconds($delaySeconds),
                );
                break;
            case Scenario::OutOfOrderDelivery:
                $this->createConfirmationAndDeliveryWebhooks(
                    $shipment,
                    $request,
                    $now->copy()->addSeconds($delaySeconds),
                    $now,
                );
                break;
            case Scenario::PermanentFailure:
                break;
        }
    }

    private function createConfirmationAndDeliveryWebhooks(
        MockProviderShipment $shipment,
        Request $request,
        Carbon $confirmationAt,
        Carbon $deliveryAt,
    ): void {
        $this->createWebhook(
            $shipment,
            $request,
            EventType::ShipmentConfirmed,
            $confirmationAt,
        );
        $this->createWebhook(
            $shipment,
            $request,
            EventType::DeliveryConfirmed,
            $deliveryAt,
        );
    }

    private function createWebhook(
        MockProviderShipment $shipment,
        Request $request,
        EventType $eventType,
        Carbon $occurredAt,
    ): void {
        $externalEventId = 'mock-event-'.hash(
            'sha256',
            $request->providerRequestKey.'|'.$eventType->value,
        );
        $items = array_map(
            fn (RequestItem $item): array => [
                'shipment_item_id' => $item->shipmentItemId,
                'quantity' => $item->quantity,
            ],
            $request->items,
        );
        $rawBody = json_encode([
            'external_event_id' => $externalEventId,
            'event_type' => $eventType->value,
            'external_shipment_id' => $shipment->external_shipment_id,
            'provider_request_key' => $shipment->provider_request_key,
            'occurred_at' => $occurredAt->toISOString(),
            'items' => $items,
        ], JSON_THROW_ON_ERROR);

        $shipment->webhooks()->create([
            'external_event_id' => $externalEventId,
            'event_type' => $eventType,
            'raw_body' => $rawBody,
            'occurred_at' => $occurredAt,
            'next_delivery_at' => $occurredAt,
        ]);
    }

    private function selectWeightedScenario(): Scenario
    {
        /** @var array<string, int|string> $configuredWeights */
        $configuredWeights = config('shipping.mock_provider.scenario_weights', []);
        $weights = [];

        foreach (Scenario::cases() as $scenario) {
            $weight = (int) ($configuredWeights[$scenario->value] ?? 0);

            if ($weight < 0) {
                throw new LogicException('Mock-provider scenario weights cannot be negative.');
            }

            $weights[$scenario->value] = $weight;
        }

        $totalWeight = array_sum($weights);

        if ($totalWeight < 1) {
            throw new LogicException('At least one mock-provider scenario weight must be positive.');
        }

        $selectedWeight = random_int(1, $totalWeight);

        foreach ($weights as $scenario => $weight) {
            $selectedWeight -= $weight;

            if ($selectedWeight <= 0) {
                return Scenario::from($scenario);
            }
        }

        throw new LogicException('A mock-provider scenario could not be selected.');
    }
}
