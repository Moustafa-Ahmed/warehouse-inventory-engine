<?php

namespace Database\Factories;

use App\Enums\MockProviderWebhooks\Status;
use App\Enums\Shipping\EventType;
use App\Models\MockProviderShipment;
use App\Models\MockProviderWebhook;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MockProviderWebhook>
 */
class MockProviderWebhookFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'mock_provider_shipment_id' => MockProviderShipment::factory(),
            'external_event_id' => fake()->unique()->uuid(),
            'event_type' => EventType::ShipmentConfirmed,
            'raw_body' => function (array $attributes): string {
                $shipment = MockProviderShipment::query()->findOrFail($attributes['mock_provider_shipment_id']);
                $eventType = $attributes['event_type'] instanceof EventType
                    ? $attributes['event_type']->value
                    : $attributes['event_type'];

                return json_encode([
                    'external_event_id' => $attributes['external_event_id'],
                    'event_type' => $eventType,
                    'external_shipment_id' => $shipment->external_shipment_id,
                    'provider_request_key' => $shipment->provider_request_key,
                    'occurred_at' => (string) $attributes['occurred_at'],
                    'items' => [],
                ], JSON_THROW_ON_ERROR);
            },
            'status' => Status::Pending,
            'attempt_count' => 0,
            'occurred_at' => now(),
            'next_delivery_at' => now(),
            'last_attempted_at' => null,
            'acknowledged_at' => null,
            'last_response_status_code' => null,
            'failure_reason' => null,
        ];
    }

    public function deliveryConfirmation(): static
    {
        return $this->state(fn () => ['event_type' => EventType::DeliveryConfirmed]);
    }

    public function delivering(int $attemptCount = 1): static
    {
        return $this->state(fn () => [
            'status' => Status::Delivering,
            'attempt_count' => $attemptCount,
            'last_attempted_at' => now(),
            'acknowledged_at' => null,
            'last_response_status_code' => null,
            'failure_reason' => null,
        ]);
    }

    public function retryScheduled(int $attemptCount = 1): static
    {
        return $this->state(fn () => [
            'status' => Status::RetryScheduled,
            'attempt_count' => $attemptCount,
            'next_delivery_at' => now()->addMinute(),
            'last_attempted_at' => now(),
            'acknowledged_at' => null,
            'last_response_status_code' => null,
            'failure_reason' => 'Provider webhook delivery failed transiently.',
        ]);
    }

    public function acknowledged(int $attemptCount = 1): static
    {
        return $this->state(fn () => [
            'status' => Status::Acknowledged,
            'attempt_count' => $attemptCount,
            'last_attempted_at' => now(),
            'acknowledged_at' => now(),
            'last_response_status_code' => 200,
            'failure_reason' => null,
        ]);
    }

    public function permanentlyFailed(int $attemptCount = 1): static
    {
        return $this->state(fn () => [
            'status' => Status::PermanentlyFailed,
            'attempt_count' => $attemptCount,
            'last_attempted_at' => now(),
            'acknowledged_at' => null,
            'last_response_status_code' => 401,
            'failure_reason' => 'Provider webhook delivery failed permanently.',
        ]);
    }
}
