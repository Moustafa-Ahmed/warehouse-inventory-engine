<?php

namespace Database\Factories;

use App\Models\OrderItem;
use App\Models\Reservation;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShipmentItem>
 */
class ShipmentItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'shipment_id' => Shipment::factory(),
            'quantity' => 1,
            'delivered_quantity' => 0,
            'reservation_id' => function (array $attributes): int {
                $shipment = Shipment::query()->findOrFail($attributes['shipment_id']);

                return Reservation::factory()
                    ->for(
                        OrderItem::factory()->for($shipment->order),
                        'orderItem',
                    )
                    ->for($shipment->warehouse)
                    ->packed($attributes['quantity'])
                    ->create()
                    ->getKey();
            },
        ];
    }
}
