<?php

namespace Database\Factories;

use App\Enums\MockProviderShipments\Status;
use App\Enums\Shipping\Scenario;
use App\Models\MockProviderShipment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MockProviderShipment>
 */
class MockProviderShipmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'provider_request_key' => fake()->unique()->uuid(),
            'external_shipment_id' => fn (array $attributes) => 'mock-'.hash('sha256', $attributes['provider_request_key']),
            'shipment_reference' => 'shipment-'.fake()->uuid(),
            'scenario' => Scenario::ImmediateSuccess,
            'scenario_was_forced' => false,
            'status' => Status::Accepted,
            'failure_reason' => null,
            'accepted_at' => now(),
            'rejected_at' => null,
            'handoff_confirmed_at' => null,
            'delivered_at' => null,
        ];
    }

    public function forced(Scenario $scenario): static
    {
        return $this->state(fn () => [
            'scenario' => $scenario,
            'scenario_was_forced' => true,
        ]);
    }

    public function permanentlyRejected(string $failureReason = 'Provider permanently rejected the submission.'): static
    {
        return $this->state(fn () => [
            'external_shipment_id' => null,
            'scenario' => Scenario::PermanentFailure,
            'status' => Status::PermanentlyRejected,
            'failure_reason' => $failureReason,
            'accepted_at' => null,
            'rejected_at' => now(),
            'handoff_confirmed_at' => null,
            'delivered_at' => null,
        ]);
    }

    public function handoffConfirmed(): static
    {
        return $this->state(fn () => [
            'status' => Status::HandoffConfirmed,
            'failure_reason' => null,
            'accepted_at' => now(),
            'rejected_at' => null,
            'handoff_confirmed_at' => now(),
            'delivered_at' => null,
        ]);
    }

    public function delivered(): static
    {
        return $this->state(fn () => [
            'status' => Status::Delivered,
            'failure_reason' => null,
            'accepted_at' => now(),
            'rejected_at' => null,
            'handoff_confirmed_at' => now(),
            'delivered_at' => now(),
        ]);
    }
}
