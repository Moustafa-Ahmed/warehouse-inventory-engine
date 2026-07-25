<?php

namespace App\Enums\Shipments;

enum Status: string
{
    case PendingHandoff = 'pending_handoff';
    case Shipped = 'shipped';
}
