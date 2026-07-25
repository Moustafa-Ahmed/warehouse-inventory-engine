<?php

namespace App\Models;

use App\Enums\Reservations\Kind;
use App\Enums\Reservations\Status;
use Database\Factories\ReservationTransitionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['reservation_id', 'operation_id', 'actor_id', 'source', 'reason', 'before_kind', 'after_kind', 'before_status', 'after_status', 'before_reserved_quantity', 'after_reserved_quantity', 'before_picked_quantity', 'after_picked_quantity', 'before_packed_quantity', 'after_packed_quantity', 'before_shipped_quantity', 'after_shipped_quantity', 'before_released_quantity', 'after_released_quantity'])]
class ReservationTransition extends Model
{
    /** @use HasFactory<ReservationTransitionFactory> */
    use HasFactory;

    public $timestamps = false;

    protected function casts(): array
    {
        return ['before_kind' => Kind::class, 'after_kind' => Kind::class, 'before_status' => Status::class, 'after_status' => Status::class, 'before_reserved_quantity' => 'integer', 'after_reserved_quantity' => 'integer', 'before_picked_quantity' => 'integer', 'after_picked_quantity' => 'integer', 'before_packed_quantity' => 'integer', 'after_packed_quantity' => 'integer', 'before_shipped_quantity' => 'integer', 'after_shipped_quantity' => 'integer', 'before_released_quantity' => 'integer', 'after_released_quantity' => 'integer', 'created_at' => 'datetime'];
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function operation(): BelongsTo
    {
        return $this->belongsTo(Operation::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
