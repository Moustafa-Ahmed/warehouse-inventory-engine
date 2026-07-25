<?php

namespace Database\Factories;

use App\Enums\Shipments\Status;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shipment>
 */
class ShipmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'warehouse_id' => Warehouse::factory(),
            'status' => Status::PendingHandoff,
            'shipped_at' => null,
        ];
    }

    public function shipped(): static
    {
        return $this->state(fn () => [
            'status' => Status::Shipped,
            'shipped_at' => now(),
        ]);
    }
}
