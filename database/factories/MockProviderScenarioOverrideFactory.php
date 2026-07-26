<?php

namespace Database\Factories;

use App\Enums\Shipping\Scenario;
use App\Models\MockProviderScenarioOverride;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MockProviderScenarioOverride>
 */
class MockProviderScenarioOverrideFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'shipment_reference' => (string) fake()->unique()->numberBetween(1, 1_000_000),
            'scenario' => Scenario::ImmediateSuccess,
        ];
    }
}
