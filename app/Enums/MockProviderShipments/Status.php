<?php

namespace App\Enums\MockProviderShipments;

enum Status: string
{
    case Accepted = 'accepted';
    case PermanentlyRejected = 'permanently_rejected';
    case HandoffConfirmed = 'handoff_confirmed';
    case Delivered = 'delivered';
}
