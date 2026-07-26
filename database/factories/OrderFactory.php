<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_number' => mb_strtoupper(fake()->unique()->bothify('ORD-########')),
        ];
    }

    public function withOutstandingItem(int $quantity = 10): static
    {
        return $this->has(
            OrderItem::factory()->outstanding($quantity),
            'items',
        );
    }

    public function withPartiallyReservedItem(
        int $orderedQuantity = 10,
        int $reservedQuantity = 6,
    ): static {
        return $this->has(
            OrderItem::factory()->partiallyReserved($orderedQuantity, $reservedQuantity),
            'items',
        );
    }

    public function withFullyShippedItem(int $quantity = 10): static
    {
        return $this->has(
            OrderItem::factory()->fullyShipped($quantity),
            'items',
        );
    }
}
