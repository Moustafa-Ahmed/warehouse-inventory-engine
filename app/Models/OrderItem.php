<?php

namespace App\Models;

use Database\Factories\OrderItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'order_id',
    'product_id',
    'ordered_quantity',
    'cancelled_quantity',
    'reserved_quantity',
    'picked_quantity',
    'packed_quantity',
    'shipped_quantity',
    'delivered_quantity',
])]
class OrderItem extends Model
{
    /** @use HasFactory<OrderItemFactory> */
    use HasFactory;

    /** @var array<string, mixed> */
    protected $attributes = [
        'cancelled_quantity' => 0,
        'reserved_quantity' => 0,
        'picked_quantity' => 0,
        'packed_quantity' => 0,
        'shipped_quantity' => 0,
        'delivered_quantity' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ordered_quantity' => 'integer',
            'cancelled_quantity' => 'integer',
            'reserved_quantity' => 'integer',
            'picked_quantity' => 'integer',
            'packed_quantity' => 'integer',
            'shipped_quantity' => 'integer',
            'delivered_quantity' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
