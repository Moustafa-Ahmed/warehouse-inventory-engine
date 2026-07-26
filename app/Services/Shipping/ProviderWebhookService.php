<?php

namespace App\Services\Shipping;

use App\Enums\ProviderWebhookReceipts\Status as ReceiptStatus;
use App\Enums\Shipments\Status as ShipmentStatus;
use App\Enums\Shipping\EventType;
use App\Enums\Shipping\WebhookProcessingDecision;
use App\Models\ProviderSubmission;
use App\Models\ProviderWebhookReceipt;
use App\Models\ShipmentItem;

final class ProviderWebhookService
{
    public function __construct(
        private readonly ShipmentService $shipments,
    ) {}

    public function process(int $providerWebhookReceiptId): void
    {
        $receipt = ProviderWebhookReceipt::query()->findOrFail($providerWebhookReceiptId);

        if (in_array($receipt->status, [
            ReceiptStatus::Processed,
            ReceiptStatus::IgnoredAsStale,
            ReceiptStatus::PermanentlyFailed,
        ], true)) {
            return;
        }

        $decision = $this->classify($receipt);

        if ($decision === WebhookProcessingDecision::WaitingForPrerequisite) {
            return;
        }

        if ($decision === WebhookProcessingDecision::Stale) {
            $receipt->forceFill([
                'status' => ReceiptStatus::IgnoredAsStale,
                'failure_reason' => null,
                'processed_at' => now(),
            ])->save();

            return;
        }

        if ($receipt->event_type === EventType::ShipmentConfirmed) {
            $this->shipments->confirmHandoff($receipt->id);
        } elseif ($receipt->event_type === EventType::DeliveryConfirmed) {
            $this->shipments->confirmDelivery($receipt->id);
        }
    }

    private function classify(
        ProviderWebhookReceipt $receipt,
    ): WebhookProcessingDecision {
        /** @var array<string, mixed> $payload */
        $payload = json_decode($receipt->raw_body, true, flags: JSON_THROW_ON_ERROR);
        $submission = ProviderSubmission::query()
            ->with('shipment.items')
            ->where('provider_request_key', $payload['provider_request_key'])
            ->firstOrFail();
        $shipment = $submission->shipment;

        if ($receipt->event_type === EventType::ShipmentConfirmed) {
            return $shipment->status === ShipmentStatus::PendingHandoff
                ? WebhookProcessingDecision::Ready
                : WebhookProcessingDecision::Stale;
        }

        if ($shipment->status === ShipmentStatus::PendingHandoff) {
            return WebhookProcessingDecision::WaitingForPrerequisite;
        }

        $deliveryIsComplete = $shipment->items->every(
            fn (ShipmentItem $item): bool => $item->delivered_quantity >= $item->quantity,
        );

        return $deliveryIsComplete
            ? WebhookProcessingDecision::Stale
            : WebhookProcessingDecision::Ready;
    }
}
