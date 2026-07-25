<?php

namespace App\Enums\Shipping;

enum EventType: string
{
    case ShipmentConfirmed = 'shipment.confirmed';
    case DeliveryConfirmed = 'delivery.confirmed';
}
