<?php

namespace App\Models;

use App\Enums\ProviderSubmissions\Status;
use Database\Factories\ProviderSubmissionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['shipment_id', 'provider_request_key'])]
class ProviderSubmission extends Model
{
    /** @use HasFactory<ProviderSubmissionFactory> */
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
            'status' => Status::class,
            'last_attempted_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }
}
