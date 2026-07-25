<?php

namespace App\Enums\ProviderSubmissions;

enum Status: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Unknown = 'unknown';
    case PermanentlyFailed = 'permanently_failed';
}
