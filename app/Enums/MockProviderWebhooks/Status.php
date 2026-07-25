<?php

namespace App\Enums\MockProviderWebhooks;

enum Status: string
{
    case Pending = 'pending';
    case Delivering = 'delivering';
    case RetryScheduled = 'retry_scheduled';
    case Acknowledged = 'acknowledged';
    case PermanentlyFailed = 'permanently_failed';
}
