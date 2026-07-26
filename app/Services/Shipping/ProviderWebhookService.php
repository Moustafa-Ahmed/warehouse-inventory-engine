<?php

namespace App\Services\Shipping;

use App\Enums\Shipping\EventType;
use App\Models\ProviderWebhookReceipt;

final class ProviderWebhookService
{
    public function __construct(
        private readonly ShipmentService $shipments,
    ) {}

    public function process(int $providerWebhookReceiptId): void
    {
        $receipt = ProviderWebhookReceipt::query()->findOrFail($providerWebhookReceiptId);

        if ($receipt->event_type === EventType::ShipmentConfirmed) {
            $this->shipments->confirmHandoff($receipt->id);
        } elseif ($receipt->event_type === EventType::DeliveryConfirmed) {
            $this->shipments->confirmDelivery($receipt->id);
        }
    }
}
