<?php

namespace App\Models;

use App\Enums\MockProviderShipments\Status;
use App\Enums\Shipping\Scenario;
use Database\Factories\MockProviderShipmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['provider_request_key', 'external_shipment_id', 'shipment_reference', 'scenario', 'scenario_was_forced'])]
class MockProviderShipment extends Model
{
    /** @use HasFactory<MockProviderShipmentFactory> */
    use HasFactory;

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => Status::Accepted->value,
        'scenario_was_forced' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scenario' => Scenario::class,
            'scenario_was_forced' => 'boolean',
            'status' => Status::class,
            'accepted_at' => 'datetime',
            'rejected_at' => 'datetime',
            'handoff_confirmed_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    public function webhooks(): HasMany
    {
        return $this->hasMany(MockProviderWebhook::class);
    }
}
