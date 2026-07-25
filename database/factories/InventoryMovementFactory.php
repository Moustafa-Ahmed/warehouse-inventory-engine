<?php

namespace Database\Factories;

use App\Enums\Inventory\MovementBucket;
use App\Models\InventoryMovement;
use App\Models\Operation;
use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryMovement>
 */
class InventoryMovementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'operation_id' => Operation::factory(),
            'product_id' => Product::factory(),
            'source_warehouse_id' => null,
            'source_bucket' => null,
            'destination_warehouse_id' => Warehouse::factory(),
            'destination_bucket' => MovementBucket::Available,
            'quantity' => fake()->numberBetween(1, 100),
            'business_reference_type' => 'test_reference',
            'business_reference_id' => fake()->uuid(),
            'actor_id' => null,
            'metadata' => null,
            'created_at' => now(),
        ];
    }
}
