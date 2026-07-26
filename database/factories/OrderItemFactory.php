<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
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
            'product_id' => Product::factory(),
            'ordered_quantity' => fake()->numberBetween(1, 100),
            'cancelled_quantity' => 0,
            'reserved_quantity' => 0,
            'picked_quantity' => 0,
            'packed_quantity' => 0,
            'shipped_quantity' => 0,
            'delivered_quantity' => 0,
        ];
    }

    public function outstanding(int $quantity = 10): static
    {
        return $this->state(fn () => [
            'ordered_quantity' => $quantity,
            'cancelled_quantity' => 0,
            'reserved_quantity' => 0,
            'picked_quantity' => 0,
            'packed_quantity' => 0,
            'shipped_quantity' => 0,
            'delivered_quantity' => 0,
        ]);
    }

    public function partiallyReserved(
        int $orderedQuantity = 10,
        int $reservedQuantity = 6,
    ): static {
        if ($reservedQuantity < 1 || $reservedQuantity >= $orderedQuantity) {
            throw new \InvalidArgumentException('A partial reservation must be between zero and the ordered quantity.');
        }

        return $this->state(fn () => [
            'ordered_quantity' => $orderedQuantity,
            'cancelled_quantity' => 0,
            'reserved_quantity' => $reservedQuantity,
            'picked_quantity' => 0,
            'packed_quantity' => 0,
            'shipped_quantity' => 0,
            'delivered_quantity' => 0,
        ]);
    }

    public function fullyShipped(int $quantity = 10): static
    {
        return $this->state(fn () => [
            'ordered_quantity' => $quantity,
            'cancelled_quantity' => 0,
            'reserved_quantity' => 0,
            'picked_quantity' => 0,
            'packed_quantity' => 0,
            'shipped_quantity' => $quantity,
            'delivered_quantity' => 0,
        ]);
    }
}
