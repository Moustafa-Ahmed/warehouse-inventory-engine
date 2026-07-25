<?php

namespace App\Models;

use App\Enums\MockProviderWebhooks\Status;
use App\Enums\Shipping\EventType;
use Database\Factories\MockProviderWebhookFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['mock_provider_shipment_id', 'external_event_id', 'event_type', 'raw_body', 'occurred_at', 'next_delivery_at'])]
class MockProviderWebhook extends Model
{
    /** @use HasFactory<MockProviderWebhookFactory> */
    use HasFactory;

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => Status::Pending->value,
        'attempt_count' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event_type' => EventType::class,
            'status' => Status::class,
            'attempt_count' => 'integer',
            'occurred_at' => 'datetime',
            'next_delivery_at' => 'datetime',
            'last_attempted_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'last_response_status_code' => 'integer',
        ];
    }

    public function mockProviderShipment(): BelongsTo
    {
        return $this->belongsTo(MockProviderShipment::class);
    }
}
