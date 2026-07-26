<?php

namespace App\Services\Shipping;

use App\Contracts\ShippingProvider;
use App\Enums\MockProviderShipments\Status as ShipmentStatus;
use App\Enums\MockProviderWebhooks\Status as WebhookStatus;
use App\Enums\Shipping\EventType;
use App\Enums\Shipping\Scenario;
use App\Jobs\DeliverMockProviderWebhookJob;
use App\Models\MockProviderScenarioOverride;
use App\Models\MockProviderShipment;
use App\Models\MockProviderWebhook;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

final class MockProviderControlService
{
    public function __construct(
        private readonly ShippingProvider $provider,
    ) {}

    public function setNextScenario(int $shipmentId, Scenario $scenario): void
    {
        $this->ensureAvailable();
        $shipment = Shipment::query()->findOrFail($shipmentId);

        MockProviderScenarioOverride::query()->updateOrCreate(
            ['shipment_reference' => (string) $shipment->id],
            ['scenario' => $scenario],
        );
    }

    public function sendHandoffConfirmation(int $mockProviderShipmentId): int
    {
        return $this->send(
            $mockProviderShipmentId,
            EventType::ShipmentConfirmed,
            allowOutOfOrder: false,
        );
    }

    public function sendDeliveryConfirmation(int $mockProviderShipmentId): int
    {
        return $this->send(
            $mockProviderShipmentId,
            EventType::DeliveryConfirmed,
            allowOutOfOrder: false,
        );
    }

    public function sendOutOfOrderDelivery(int $mockProviderShipmentId): int
    {
        return $this->send(
            $mockProviderShipmentId,
            EventType::DeliveryConfirmed,
            allowOutOfOrder: true,
        );
    }

    public function replayLastWebhook(int $mockProviderShipmentId): int
    {
        $this->ensureAvailable();
        $webhookId = DB::transaction(function () use ($mockProviderShipmentId): int {
            $shipment = MockProviderShipment::query()
                ->lockForUpdate()
                ->findOrFail($mockProviderShipmentId);
            $webhook = $shipment->webhooks()
                ->latest('occurred_at')
                ->latest('id')
                ->lockForUpdate()
                ->firstOrFail();

            $this->makeDue($webhook);

            return $webhook->id;
        }, attempts: 3);

        DeliverMockProviderWebhookJob::dispatch($webhookId);

        return $webhookId;
    }

    private function send(
        int $mockProviderShipmentId,
        EventType $eventType,
        bool $allowOutOfOrder,
    ): int {
        $this->ensureAvailable();
        $webhookId = DB::transaction(
            fn (): int => $this->createOrRelease(
                $mockProviderShipmentId,
                $eventType,
                $allowOutOfOrder,
            ),
            attempts: 3,
        );

        DeliverMockProviderWebhookJob::dispatch($webhookId);

        return $webhookId;
    }

    private function createOrRelease(
        int $mockProviderShipmentId,
        EventType $eventType,
        bool $allowOutOfOrder,
    ): int {
        $mockShipment = MockProviderShipment::query()
            ->lockForUpdate()
            ->findOrFail($mockProviderShipmentId);

        if ($mockShipment->status === ShipmentStatus::PermanentlyRejected) {
            throw new InvalidArgumentException(
                'A permanently rejected mock-provider shipment cannot emit callbacks.'
            );
        }

        if (
            $eventType === EventType::DeliveryConfirmed
            && ! $allowOutOfOrder
            && $mockShipment->status === ShipmentStatus::Accepted
        ) {
            throw new InvalidArgumentException(
                'Delivery confirmation requires provider handoff confirmation.'
            );
        }

        $webhook = $mockShipment->webhooks()
            ->where('event_type', $eventType->value)
            ->lockForUpdate()
            ->first();

        if ($webhook === null) {
            $webhook = $this->createWebhook($mockShipment, $eventType);
        } else {
            $this->makeDue($webhook);
        }

        return $webhook->id;
    }

    private function createWebhook(
        MockProviderShipment $mockShipment,
        EventType $eventType,
    ): MockProviderWebhook {
        if (! ctype_digit($mockShipment->shipment_reference)) {
            throw new LogicException(
                'The mock-provider shipment does not reference a warehouse shipment.'
            );
        }

        $shipment = Shipment::query()
            ->with('items')
            ->findOrFail((int) $mockShipment->shipment_reference);

        if ($shipment->items->isEmpty()) {
            throw new InvalidArgumentException(
                'A mock-provider callback requires at least one shipment item.'
            );
        }

        $occurredAt = now();
        $externalEventId = 'mock-event-'.hash(
            'sha256',
            $mockShipment->provider_request_key.'|'.$eventType->value,
        );
        $items = $shipment->items
            ->map(fn (ShipmentItem $item): array => [
                'shipment_item_id' => $item->id,
                'quantity' => $item->quantity,
            ])
            ->values()
            ->all();
        $rawBody = json_encode([
            'external_event_id' => $externalEventId,
            'event_type' => $eventType->value,
            'external_shipment_id' => $mockShipment->external_shipment_id,
            'provider_request_key' => $mockShipment->provider_request_key,
            'occurred_at' => $occurredAt->toISOString(),
            'items' => $items,
        ], JSON_THROW_ON_ERROR);

        return $mockShipment->webhooks()->create([
            'external_event_id' => $externalEventId,
            'event_type' => $eventType,
            'raw_body' => $rawBody,
            'occurred_at' => $occurredAt,
            'next_delivery_at' => $occurredAt,
        ]);
    }

    private function makeDue(MockProviderWebhook $webhook): void
    {
        if ($webhook->status === WebhookStatus::Delivering) {
            throw new InvalidArgumentException(
                'A mock-provider webhook already has an active delivery attempt.'
            );
        }

        $webhook->forceFill([
            'status' => WebhookStatus::Pending,
            'next_delivery_at' => now(),
            'acknowledged_at' => null,
            'last_response_status_code' => null,
            'failure_reason' => null,
        ])->save();
    }

    private function ensureAvailable(): void
    {
        if (! App::environment(['local', 'testing'])) {
            throw new LogicException(
                'Mock-provider controls are available only in local and testing environments.'
            );
        }

        if (! $this->provider instanceof PersistentMockProvider) {
            throw new LogicException(
                'Mock-provider controls require the persistent mock provider.'
            );
        }
    }
}
