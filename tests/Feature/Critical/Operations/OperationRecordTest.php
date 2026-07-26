<?php

use App\Enums\Operations\Status;
use App\Enums\Operations\Type;
use App\Models\Operation;
use Illuminate\Database\QueryException;

it('enforces idempotency key uniqueness and preserves a completed original result', function () {
    $idempotencyKey = 'operation-key-001';
    $resultPayload = ['received_quantity' => 10];

    $operation = Operation::factory()->completed()->create([
        'idempotency_key' => $idempotencyKey,
        'operation_type' => Type::ReceiveStock,
        'result_reference' => 'receipt:42',
        'result_payload' => $resultPayload,
    ]);

    $this->assertModelExists($operation);

    expect(fn () => Operation::factory()->create([
        'idempotency_key' => $idempotencyKey,
    ]))->toThrow(QueryException::class);

    $persistedOperation = Operation::query()
        ->where('idempotency_key', $idempotencyKey)
        ->sole();

    expect($persistedOperation->status)->toBe(Status::Completed)
        ->and($persistedOperation->result_reference)->toBe('receipt:42')
        ->and($persistedOperation->result_payload)->toBe($resultPayload)
        ->and($persistedOperation->completed_at)->not->toBeNull()
        ->and($persistedOperation->failure_context)->toBeNull();
});
