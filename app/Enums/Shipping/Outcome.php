<?php

namespace App\Enums\Shipping;

enum Outcome: string
{
    case Accepted = 'accepted';
    case PermanentlyFailed = 'permanently_failed';
    case Uncertain = 'uncertain';
}
