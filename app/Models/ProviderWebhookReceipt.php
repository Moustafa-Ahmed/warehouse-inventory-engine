<?php

namespace App\Models;

use App\Enums\ProviderWebhookReceipts\Status;
use App\Enums\Shipping\EventType;
use Database\Factories\ProviderWebhookReceiptFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['provider', 'external_event_id', 'event_type', 'raw_body', 'occurred_at'])]
class ProviderWebhookReceipt extends Model
{
    /** @use HasFactory<ProviderWebhookReceiptFactory> */
    use HasFactory;

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => Status::Pending->value,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event_type' => EventType::class,
            'status' => Status::class,
            'occurred_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }
}
