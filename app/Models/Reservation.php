<?php

namespace App\Models;

use App\Enums\Reservations\Kind;
use App\Enums\Reservations\Status;
use Database\Factories\ReservationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['order_item_id', 'warehouse_id', 'requested_quantity'])]
class Reservation extends Model
{
    /** @use HasFactory<ReservationFactory> */
    use HasFactory;

    protected $attributes = ['status' => Status::Open->value, 'reserved_quantity' => 0, 'picked_quantity' => 0, 'packed_quantity' => 0, 'shipped_quantity' => 0, 'released_quantity' => 0];

    protected function casts(): array
    {
        return ['kind' => Kind::class, 'status' => Status::class, 'requested_quantity' => 'integer', 'reserved_quantity' => 'integer', 'picked_quantity' => 'integer', 'packed_quantity' => 'integer', 'shipped_quantity' => 'integer', 'released_quantity' => 'integer', 'expires_at' => 'datetime'];
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function transitions(): HasMany
    {
        return $this->hasMany(ReservationTransition::class);
    }

    public function shipmentItems(): HasMany
    {
        return $this->hasMany(ShipmentItem::class);
    }
}
