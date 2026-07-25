<?php

namespace Database\Factories;

use App\Enums\Reservations\Kind;
use App\Enums\Reservations\Status;
use App\Models\Operation;
use App\Models\Reservation;
use App\Models\ReservationTransition;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReservationTransition>
 */
class ReservationTransitionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reservation_id' => Reservation::factory(), 'operation_id' => Operation::factory(), 'actor_id' => null, 'source' => 'system', 'reason' => null, 'before_kind' => Kind::Confirmed, 'after_kind' => Kind::Confirmed, 'before_status' => Status::Open, 'after_status' => Status::Open, 'before_reserved_quantity' => 0, 'after_reserved_quantity' => 1, 'before_picked_quantity' => 0, 'after_picked_quantity' => 0, 'before_packed_quantity' => 0, 'after_packed_quantity' => 0, 'before_shipped_quantity' => 0, 'after_shipped_quantity' => 0, 'before_released_quantity' => 0, 'after_released_quantity' => 0, 'created_at' => now(),
        ];
    }
}
