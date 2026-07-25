<?php

namespace App\Enums\ProviderWebhookReceipts;

enum Status: string
{
    case Pending = 'pending';
    case Processed = 'processed';
    case IgnoredAsStale = 'ignored_as_stale';
    case RetryableFailure = 'retryable_failure';
    case PermanentlyFailed = 'permanently_failed';
}
