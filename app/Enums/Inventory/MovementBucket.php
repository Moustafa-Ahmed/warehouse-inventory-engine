<?php

namespace App\Enums\Inventory;

enum MovementBucket: string
{
    case Available = 'available';
    case Reserved = 'reserved';
    case Picked = 'picked';
    case Packed = 'packed';
    case Shipped = 'shipped';
}
