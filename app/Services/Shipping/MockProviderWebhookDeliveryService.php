<?php

namespace App\Services\Shipping;

use App\Enums\MockProviderShipments\Status as ShipmentStatus;
use App\Enums\MockProviderWebhooks\Status;
use App\Enums\Shipping\EventType;
use App\Models\MockProviderShipment;
use App\Models\MockProviderWebhook;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

final class MockProviderWebhookDeliveryService
{
    public function __construct(
        private readonly WebhookSignature $signatures,
    ) {}

    public function deliver(int $webhookId): void
    {
        $attempt = DB::transaction(
            fn (): ?array => $this->claim($webhookId),
            attempts: 3,
        );

        if ($attempt === null) {
            return;
        }

        $url = (string) config('shipping.mock_provider.webhook_url');
        $secret = (string) config('shipping.webhook.providers.mock.secret');

        if ($url === '' || $secret === '') {
            $this->finishPermanently(
                $webhookId,
                $attempt['attempt_count'],
                null,
                'Mock-provider callback URL or signing secret is not configured.',
            );

            return;
        }

        $timestamp = now()->timestamp;

        try {
            $response = Http::connectTimeout(
                (int) config('shipping.mock_provider.connect_timeout_seconds'),
            )
                ->timeout((int) config('shipping.mock_provider.request_timeout_seconds'))
                ->acceptJson()
                ->withHeaders([
                    'X-Shipping-Provider' => 'mock',
                    'X-Provider-Event-Id' => $attempt['external_event_id'],
                    'X-Provider-Timestamp' => (string) $timestamp,
                    'X-Provider-Signature' => $this->signatures->sign(
                        $timestamp,
                        $attempt['raw_body'],
                        $secret,
                    ),
                ])
                ->withBody($attempt['raw_body'], 'application/json')
                ->post($url);
        } catch (ConnectionException) {
            $this->finishRetryable(
                $webhookId,
                $attempt['attempt_count'],
                null,
                'Provider webhook transport failed.',
            );

            return;
        }

        if ($response->successful()) {
            $this->finishAcknowledged(
                $webhookId,
                $attempt['attempt_count'],
                $response,
            );

            return;
        }

        if ($response->status() === 429 || $response->serverError()) {
            $this->finishRetryable(
                $webhookId,
                $attempt['attempt_count'],
                $response->status(),
                'Provider webhook endpoint returned a retryable response.',
            );

            return;
        }

        $this->finishPermanently(
            $webhookId,
            $attempt['attempt_count'],
            $response->status(),
            'Provider webhook endpoint rejected the callback.',
        );
    }

    /**
     * @return array{attempt_count: int, external_event_id: string, raw_body: string}|null
     */
    private function claim(int $webhookId): ?array
    {
        $webhook = MockProviderWebhook::query()
            ->lockForUpdate()
            ->findOrFail($webhookId);
        $leaseCutoff = now()->subSeconds(
            max(1, (int) config('shipping.mock_provider.delivery_lease_seconds')),
        );
        $isDue = in_array($webhook->status, [
            Status::Pending,
            Status::RetryScheduled,
        ], true) && $webhook->next_delivery_at->isPast();
        $claimExpired = $webhook->status === Status::Delivering
            && $webhook->last_attempted_at?->lte($leaseCutoff);

        if (! $isDue && ! $claimExpired) {
            return null;
        }

        $webhook->forceFill([
            'status' => Status::Delivering,
            'attempt_count' => $webhook->attempt_count + 1,
            'last_attempted_at' => now(),
            'acknowledged_at' => null,
            'last_response_status_code' => null,
            'failure_reason' => null,
        ])->save();
        $this->recordProviderEvent($webhook);

        return [
            'attempt_count' => $webhook->attempt_count,
            'external_event_id' => $webhook->external_event_id,
            'raw_body' => $webhook->raw_body,
        ];
    }

    private function recordProviderEvent(MockProviderWebhook $webhook): void
    {
        $shipment = MockProviderShipment::query()
            ->lockForUpdate()
            ->findOrFail($webhook->mock_provider_shipment_id);

        if ($shipment->status === ShipmentStatus::PermanentlyRejected) {
            return;
        }

        if ($webhook->event_type === EventType::ShipmentConfirmed) {
            $shipment->forceFill([
                'status' => $shipment->status === ShipmentStatus::Delivered
                    ? ShipmentStatus::Delivered
                    : ShipmentStatus::HandoffConfirmed,
                'handoff_confirmed_at' => $shipment->handoff_confirmed_at
                    ?? $webhook->occurred_at,
            ])->save();

            return;
        }

        $shipment->forceFill([
            'status' => ShipmentStatus::Delivered,
            'delivered_at' => $shipment->delivered_at ?? $webhook->occurred_at,
        ])->save();
    }

    private function finishAcknowledged(
        int $webhookId,
        int $attemptCount,
        Response $response,
    ): void {
        $this->finish($webhookId, $attemptCount, [
            'status' => Status::Acknowledged,
            'acknowledged_at' => now(),
            'last_response_status_code' => $response->status(),
            'failure_reason' => null,
        ]);
    }

    private function finishRetryable(
        int $webhookId,
        int $attemptCount,
        ?int $statusCode,
        string $reason,
    ): void {
        $maximumAttempts = max(
            1,
            (int) config('shipping.mock_provider.maximum_delivery_attempts'),
        );

        if ($attemptCount >= $maximumAttempts) {
            $this->finishPermanently(
                $webhookId,
                $attemptCount,
                $statusCode,
                'Provider webhook delivery exhausted its retry limit.',
            );

            return;
        }

        $baseDelay = max(
            1,
            (int) config('shipping.mock_provider.retry_base_seconds'),
        );
        $delay = $baseDelay * (2 ** max(0, $attemptCount - 1));

        $this->finish($webhookId, $attemptCount, [
            'status' => Status::RetryScheduled,
            'next_delivery_at' => now()->addSeconds($delay),
            'acknowledged_at' => null,
            'last_response_status_code' => $statusCode,
            'failure_reason' => $reason,
        ]);
    }

    private function finishPermanently(
        int $webhookId,
        int $attemptCount,
        ?int $statusCode,
        string $reason,
    ): void {
        $this->finish($webhookId, $attemptCount, [
            'status' => Status::PermanentlyFailed,
            'acknowledged_at' => null,
            'last_response_status_code' => $statusCode,
            'failure_reason' => $reason,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function finish(
        int $webhookId,
        int $attemptCount,
        array $attributes,
    ): void {
        DB::transaction(function () use ($webhookId, $attemptCount, $attributes): void {
            $webhook = MockProviderWebhook::query()
                ->lockForUpdate()
                ->findOrFail($webhookId);

            if (
                $webhook->status !== Status::Delivering
                || $webhook->attempt_count !== $attemptCount
            ) {
                return;
            }

            $webhook->forceFill($attributes)->save();
        }, attempts: 3);
    }
}
