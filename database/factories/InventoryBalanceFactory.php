<?php

namespace Database\Factories;

use App\Models\InventoryBalance;
use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryBalance>
 */
class InventoryBalanceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'warehouse_id' => Warehouse::factory(),
            'available_quantity' => 0,
            'reserved_quantity' => 0,
            'picked_quantity' => 0,
            'packed_quantity' => 0,
        ];
    }
}
