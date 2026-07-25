<?php

namespace App\Enums\Shipping;

enum CallbackIntent: string
{
    case Immediate = 'immediate';
    case Delayed = 'delayed';
    case Duplicate = 'duplicate';
    case OutOfOrder = 'out_of_order';
}
