<?php

namespace App\Services\Shipping;

use App\Contracts\ShippingProvider;
use App\DTOs\Shipping\PreparedSubmission;
use App\DTOs\Shipping\Request;
use App\DTOs\Shipping\RequestItem;
use App\DTOs\Shipping\Result;
use App\Enums\ProviderSubmissions\Status as ProviderSubmissionStatus;
use App\Enums\Shipments\Status as ShipmentStatus;
use App\Enums\Shipping\EventType;
use App\Enums\Shipping\Outcome;
use App\Models\ProviderSubmission;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

final class ShipmentSubmissionService
{
    public function __construct(
        private readonly ShippingProvider $provider,
    ) {}

    public function prepare(int $shipmentId): PreparedSubmission
    {
        return DB::transaction(
            fn (): PreparedSubmission => $this->prepareLockedShipment($shipmentId),
            attempts: 3,
        );
    }

    public function submit(int $shipmentId): Result
    {
        $prepared = $this->prepare($shipmentId);

        try {
            $result = $this->provider->submit($prepared->providerRequest);
        } catch (Throwable $exception) {
            $this->recordProviderException(
                $prepared->providerSubmissionId,
                $exception,
            );

            throw $exception;
        }

        $this->recordResult($prepared->providerSubmissionId, $result);

        return $result;
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

    public function reconcile(int $providerSubmissionId): ?Result
    {
        $submission = ProviderSubmission::query()->findOrFail($providerSubmissionId);

        if ($submission->status !== ProviderSubmissionStatus::Unknown) {
            return null;
        }

        $result = $this->provider->statusFor($submission->provider_request_key);

        if ($result === null) {
            return null;
        }

        $this->recordResult($submission->id, $result);

        if (in_array($result->latestConfirmedEvent, [
            EventType::ShipmentConfirmed,
            EventType::DeliveryConfirmed,
        ], true)) {
            $this->provider->requestHandoffConfirmationRedelivery(
                $submission->provider_request_key,
            );
        }

        return $result;
    }

    private function recordResult(int $submissionId, Result $result): void
    {
        DB::transaction(function () use ($submissionId, $result): void {
            $submission = ProviderSubmission::query()
                ->lockForUpdate()
                ->findOrFail($submissionId);

            if (! hash_equals(
                $submission->provider_request_key,
                $result->providerRequestKey,
            )) {
                throw new InvalidArgumentException(
                    'The provider result does not match the prepared request.'
                );
            }

            $submission->forceFill([
                'status' => match ($result->outcome) {
                    Outcome::Accepted => ProviderSubmissionStatus::Accepted,
                    Outcome::Unknown => ProviderSubmissionStatus::Unknown,
                    Outcome::PermanentlyFailed => ProviderSubmissionStatus::PermanentlyFailed,
                },
                'external_shipment_id' => $result->externalShipmentId,
                'failure_reason' => match ($result->outcome) {
                    Outcome::Accepted => null,
                    Outcome::Unknown => 'The provider response timed out; the outcome is unknown.',
                    Outcome::PermanentlyFailed => 'The provider permanently rejected the shipment.',
                },
                'last_attempted_at' => now(),
                'resolved_at' => $result->outcome === Outcome::Unknown ? null : now(),
            ])->save();
        }, attempts: 3);
    }

    private function recordProviderException(
        int $submissionId,
        Throwable $exception,
    ): void {
        DB::transaction(function () use ($submissionId, $exception): void {
            ProviderSubmission::query()
                ->lockForUpdate()
                ->findOrFail($submissionId)
                ->forceFill([
                    'status' => ProviderSubmissionStatus::Unknown,
                    'failure_reason' => 'Provider call failed with '.$exception::class.'.',
                    'last_attempted_at' => now(),
                    'resolved_at' => null,
                ])->save();
        }, attempts: 3);
    }
}
