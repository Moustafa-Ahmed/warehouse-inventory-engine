<?php

namespace Database\Factories;

use App\Enums\ProviderWebhookReceipts\Status;
use App\Enums\Shipping\EventType;
use App\Models\ProviderWebhookReceipt;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProviderWebhookReceipt>
 */
class ProviderWebhookReceiptFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'provider' => 'mock',
            'external_event_id' => fake()->unique()->uuid(),
            'event_type' => EventType::ShipmentConfirmed,
            'raw_body' => function (array $attributes): string {
                $providerRequestKey = 'provider-request-'.hash('sha256', $attributes['external_event_id']);

                return json_encode([
                    'external_event_id' => $attributes['external_event_id'],
                    'event_type' => $attributes['event_type'] instanceof EventType
                        ? $attributes['event_type']->value
                        : $attributes['event_type'],
                    'external_shipment_id' => 'mock-'.hash('sha256', $providerRequestKey),
                    'provider_request_key' => $providerRequestKey,
                    'occurred_at' => (string) $attributes['occurred_at'],
                    'items' => [],
                ], JSON_THROW_ON_ERROR);
            },
            'occurred_at' => now(),
            'status' => Status::Pending,
            'failure_reason' => null,
            'processed_at' => null,
        ];
    }

    public function deliveryConfirmation(): static
    {
        return $this->state(fn () => ['event_type' => EventType::DeliveryConfirmed]);
    }

    public function processed(): static
    {
        return $this->state(fn () => [
            'status' => Status::Processed,
            'failure_reason' => null,
            'processed_at' => now(),
        ]);
    }

    public function ignoredAsStale(): static
    {
        return $this->state(fn () => [
            'status' => Status::IgnoredAsStale,
            'failure_reason' => null,
            'processed_at' => now(),
        ]);
    }

    public function retryableFailure(): static
    {
        return $this->state(fn () => [
            'status' => Status::RetryableFailure,
            'failure_reason' => 'Provider webhook processing failed transiently.',
            'processed_at' => null,
        ]);
    }

    public function permanentlyFailed(): static
    {
        return $this->state(fn () => [
            'status' => Status::PermanentlyFailed,
            'failure_reason' => 'Provider webhook processing failed permanently.',
            'processed_at' => null,
        ]);
    }
}
