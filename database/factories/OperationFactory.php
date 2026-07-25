<?php

namespace Database\Factories;

use App\Enums\Operations\Status;
use App\Models\Operation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Operation>
 */
class OperationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'operation_type' => fake()->slug(2),
            'idempotency_key' => (string) Str::uuid(),
            'request_hash' => hash('sha256', fake()->uuid()),
            'status' => Status::Pending,
            'result_reference' => null,
            'result_payload' => null,
            'failure_context' => null,
            'completed_at' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Status::Completed,
            'result_reference' => 'result:'.fake()->uuid(),
            'result_payload' => ['message' => 'Completed'],
            'failure_context' => null,
            'completed_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Status::Failed,
            'result_reference' => null,
            'result_payload' => null,
            'failure_context' => ['reason' => 'Operation failed'],
            'completed_at' => now(),
        ]);
    }
}
