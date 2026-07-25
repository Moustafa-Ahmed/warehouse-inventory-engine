<?php

namespace App\Enums\Reservations;

enum Status: string
{
    case Open = 'open';
    case Released = 'released';
    case Expired = 'expired';
    case Closed = 'closed';
}
