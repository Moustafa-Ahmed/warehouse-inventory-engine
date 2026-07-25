<?php

namespace App\Enums\Operations;

enum Status: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';
}
