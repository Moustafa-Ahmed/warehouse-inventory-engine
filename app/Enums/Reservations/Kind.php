<?php

namespace App\Enums\Reservations;

enum Kind: string
{
    case Temporary = 'temporary';
    case Confirmed = 'confirmed';
}
