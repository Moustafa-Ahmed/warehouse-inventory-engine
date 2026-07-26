<?php

namespace App\Services\Shipping;

use App\DTOs\Shipping\PreparedSubmission;
use App\DTOs\Shipping\Request;
use App\DTOs\Shipping\RequestItem;
use App\Enums\ProviderSubmissions\Status as ProviderSubmissionStatus;
use App\Enums\Shipments\Status as ShipmentStatus;
use App\Models\ProviderSubmission;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class ShipmentSubmissionService
{
    public function prepare(int $shipmentId): PreparedSubmission
    {
        return DB::transaction(
            fn (): PreparedSubmission => $this->prepareLockedShipment($shipmentId),
            attempts: 3,
        );
    }

    private function prepareLockedShipment(int $shipmentId): PreparedSubmission
    {
        $shipment = Shipment::query()
            ->lockForUpdate()
            ->findOrFail($shipmentId);

        if ($shipment->status !== ShipmentStatus::PendingHandoff) {
            throw new InvalidArgumentException('Only shipments pending handoff can be submitted.');
        }

        $shipmentItems = $shipment->items()
            ->orderBy('id')
            ->get();

        if ($shipmentItems->isEmpty()) {
            throw new InvalidArgumentException('A shipment must contain packed items before submission.');
        }

        $submission = ProviderSubmission::query()
            ->where('shipment_id', $shipment->id)
            ->whereIn('status', [
                ProviderSubmissionStatus::Pending->value,
                ProviderSubmissionStatus::Accepted->value,
                ProviderSubmissionStatus::Unknown->value,
            ])
            ->latest('id')
            ->first();

        if ($submission === null) {
            $submission = $shipment->providerSubmissions()->create([
                'provider_request_key' => 'shipment-'.$shipment->id.'-'.Str::uuid(),
            ]);
        }

        return new PreparedSubmission(
            providerSubmissionId: $submission->id,
            providerRequest: new Request(
                providerRequestKey: $submission->provider_request_key,
                shipmentReference: (string) $shipment->id,
                items: $shipmentItems
                    ->map(fn (ShipmentItem $item): RequestItem => new RequestItem(
                        shipmentItemId: $item->id,
                        quantity: $item->quantity,
                    ))
                    ->all(),
            ),
        );
    }
}
