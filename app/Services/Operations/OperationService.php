<?php

namespace App\Services\Operations;

use App\Enums\Operations\Status;
use App\Enums\Operations\Type;
use App\Exceptions\IdempotencyConflictException;
use App\Models\Operation;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use LogicException;

final class OperationService
{
    /**
     * @param  array<string, mixed>  $validatedRequest
     * @param  Closure(Operation): array<string, mixed>  $callback
     * @return array<string, mixed>
     */
    public function execute(
        Type $type,
        string $idempotencyKey,
        array $validatedRequest,
        Closure $callback,
    ): array {
        if (DB::connection()->transactionLevel() === 0) {
            throw new LogicException('OperationService must execute inside the caller transaction.');
        }

        $requestHash = $this->requestHash($type, $validatedRequest);
        [$operation, $wasClaimed] = $this->claim($type, $idempotencyKey, $requestHash);

        if (! $wasClaimed) {
            $this->ensureRequestMatches($operation, $type, $requestHash);

            if ($operation->status === Status::Completed) {
                return $operation->result_payload ?? [];
            }

            throw new LogicException("Operation [{$idempotencyKey}] is not available for execution.");
        }

        $result = $callback($operation);

        $operation->forceFill([
            'status' => Status::Completed,
            'result_payload' => $result,
            'failure_context' => null,
            'completed_at' => now(),
        ])->save();

        return $result;
    }

    /**
     * @return array{Operation, bool}
     */
    private function claim(Type $type, string $idempotencyKey, string $requestHash): array
    {
        try {
            return [
                Operation::query()->create([
                    'operation_type' => $type,
                    'idempotency_key' => $idempotencyKey,
                    'request_hash' => $requestHash,
                ]),
                true,
            ];
        } catch (UniqueConstraintViolationException) {
            return [
                Operation::query()
                    ->where('idempotency_key', $idempotencyKey)
                    ->lockForUpdate()
                    ->firstOrFail(),
                false,
            ];
        }
    }

    private function ensureRequestMatches(Operation $operation, Type $type, string $requestHash): void
    {
        if (
            $operation->operation_type !== $type
            || ! hash_equals($operation->request_hash, $requestHash)
        ) {
            throw new IdempotencyConflictException($operation->idempotency_key);
        }
    }

    /**
     * @param  array<string, mixed>  $validatedRequest
     */
    private function requestHash(Type $type, array $validatedRequest): string
    {
        return hash('sha256', json_encode([
            'operation_type' => $type->value,
            'request' => $this->canonicalize($validatedRequest),
        ], JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION));
    }

    private function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(
                fn (mixed $item): mixed => $this->canonicalize($item),
                $value,
            );
        }

        ksort($value, SORT_STRING);

        return array_map(
            fn (mixed $item): mixed => $this->canonicalize($item),
            $value,
        );
    }
}
