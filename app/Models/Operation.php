<?php

namespace App\Models;

use App\Enums\Operations\Status;
use App\Enums\Operations\Type;
use Database\Factories\OperationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'operation_type',
    'idempotency_key',
    'request_hash',
])]
class Operation extends Model
{
    /** @use HasFactory<OperationFactory> */
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
            'operation_type' => Type::class,
            'status' => Status::class,
            'result_payload' => 'array',
            'failure_context' => 'array',
            'completed_at' => 'datetime',
        ];
    }
}
