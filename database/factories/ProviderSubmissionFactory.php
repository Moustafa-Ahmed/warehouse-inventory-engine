<?php

namespace Database\Factories;

use App\Enums\ProviderSubmissions\Status;
use App\Models\ProviderSubmission;
use App\Models\Shipment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProviderSubmission>
 */
class ProviderSubmissionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'shipment_id' => Shipment::factory(),
            'provider_request_key' => fake()->unique()->uuid(),
            'status' => Status::Pending,
            'external_shipment_id' => null,
            'failure_reason' => null,
            'last_attempted_at' => null,
            'resolved_at' => null,
        ];
    }

    public function accepted(?string $externalShipmentId = null): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Status::Accepted,
            'external_shipment_id' => $externalShipmentId ?? 'mock-'.hash('sha256', $attributes['provider_request_key']),
            'failure_reason' => null,
            'last_attempted_at' => now(),
            'resolved_at' => now(),
        ]);
    }

    public function unknown(): static
    {
        return $this->state(fn () => [
            'status' => Status::Unknown,
            'external_shipment_id' => null,
            'failure_reason' => 'Provider response timed out.',
            'last_attempted_at' => now(),
            'resolved_at' => null,
        ]);
    }

    public function permanentlyFailed(string $failureReason = 'Provider permanently rejected the submission.'): static
    {
        return $this->state(fn () => [
            'status' => Status::PermanentlyFailed,
            'external_shipment_id' => null,
            'failure_reason' => $failureReason,
            'last_attempted_at' => now(),
            'resolved_at' => now(),
        ]);
    }
}
