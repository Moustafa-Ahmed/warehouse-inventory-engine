<?php

namespace Database\Factories;

use App\Enums\Reservations\Kind;
use App\Enums\Reservations\Status;
use App\Models\OrderItem;
use App\Models\Reservation;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reservation>
 */
class ReservationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_item_id' => OrderItem::factory(), 'warehouse_id' => Warehouse::factory(), 'kind' => Kind::Confirmed, 'status' => Status::Open, 'requested_quantity' => 1, 'reserved_quantity' => 1, 'picked_quantity' => 0, 'packed_quantity' => 0, 'shipped_quantity' => 0, 'released_quantity' => 0, 'expires_at' => null,
        ];
    }

    public function temporary(): static
    {
        return $this->state(fn () => ['kind' => Kind::Temporary, 'expires_at' => now()->addHour()]);
    }

    public function packed(int $quantity = 1): static
    {
        return $this->state(fn () => [
            'requested_quantity' => $quantity,
            'reserved_quantity' => 0,
            'picked_quantity' => 0,
            'packed_quantity' => $quantity,
            'shipped_quantity' => 0,
            'released_quantity' => 0,
        ]);
    }

    public function released(int $quantity = 1): static
    {
        return $this->state(fn () => [
            'status' => Status::Released,
            'requested_quantity' => $quantity,
            'reserved_quantity' => 0,
            'picked_quantity' => 0,
            'packed_quantity' => 0,
            'shipped_quantity' => 0,
            'released_quantity' => $quantity,
        ]);
    }

    public function expired(int $quantity = 1): static
    {
        return $this->state(fn () => [
            'kind' => Kind::Temporary,
            'status' => Status::Expired,
            'requested_quantity' => $quantity,
            'reserved_quantity' => 0,
            'picked_quantity' => 0,
            'packed_quantity' => 0,
            'shipped_quantity' => 0,
            'released_quantity' => $quantity,
            'expires_at' => now()->subMinute(),
        ]);
    }

    public function closed(int $quantity = 1): static
    {
        return $this->state(fn () => [
            'status' => Status::Closed,
            'requested_quantity' => $quantity,
            'reserved_quantity' => 0,
            'picked_quantity' => 0,
            'packed_quantity' => 0,
            'shipped_quantity' => $quantity,
            'released_quantity' => 0,
        ]);
    }
}
