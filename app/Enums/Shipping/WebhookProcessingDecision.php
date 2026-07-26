<?php

namespace App\Enums\Shipping;

enum WebhookProcessingDecision
{
    case Ready;
    case WaitingForPrerequisite;
    case Stale;
}
