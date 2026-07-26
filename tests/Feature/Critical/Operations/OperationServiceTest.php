<?php

use App\Enums\Operations\Status;
use App\Enums\Operations\Type;
use App\Exceptions\IdempotencyConflictException;
use App\Models\Operation;
use App\Models\Product;
use App\Services\Operations\OperationService;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Facades\DB;
use Tests\Support\ConcurrentOperationClaim;

it('returns the original result for an identical canonical request', function () {
    $service = app(OperationService::class);
    $callbackExecutions = 0;

    $execute = function (array $request) use ($service, &$callbackExecutions): array {
        return DB::transaction(function () use ($service, $request, &$callbackExecutions): array {
            return $service->execute(
                Type::ReceiveStock,
                'receipt-replay-key',
                $request,
                function () use (&$callbackExecutions): array {
                    $callbackExecutions++;

                    return ['movement_id' => 42, 'received_quantity' => 5];
                },
            );
        });
    };

    $firstResult = $execute([
        'product_id' => 10,
        'warehouse' => ['id' => 20, 'code' => 'CAI'],
        'quantity' => 5,
    ]);
    $replayedResult = $execute([
        'quantity' => 5,
        'warehouse' => ['code' => 'CAI', 'id' => 20],
        'product_id' => 10,
    ]);
    $operation = Operation::query()->sole();

    expect($replayedResult)->toBe($firstResult)
        ->and($callbackExecutions)->toBe(1)
        ->and($operation->operation_type)->toBe(Type::ReceiveStock)
        ->and($operation->status)->toBe(Status::Completed)
        ->and($operation->result_payload)->toBe($firstResult)
        ->and($operation->completed_at)->not->toBeNull();
});

it('rejects reuse of an idempotency key for a different request', function () {
    $service = app(OperationService::class);

    DB::transaction(
        fn (): array => $service->execute(
            Type::ReceiveStock,
            'receipt-conflict-key',
            ['product_id' => 10, 'warehouse_id' => 20, 'quantity' => 5],
            fn (): array => ['movement_id' => 42],
        ),
    );

    $conflictingCallbackRan = false;

    expect(fn () => DB::transaction(
        fn (): array => $service->execute(
            Type::ReceiveStock,
            'receipt-conflict-key',
            ['product_id' => 10, 'warehouse_id' => 20, 'quantity' => 6],
            function () use (&$conflictingCallbackRan): array {
                $conflictingCallbackRan = true;

                return ['movement_id' => 43];
            },
        ),
    ))->toThrow(IdempotencyConflictException::class)
        ->and($conflictingCallbackRan)->toBeFalse()
        ->and(Operation::query()->count())->toBe(1);
});

it('allows only one concurrent claimant to execute the callback', function () {
    $idempotencyKey = 'receipt-concurrent-key';
    $sku = 'CONCURRENT-RECEIPT';
    $request = ['product_id' => 10, 'warehouse_id' => 20, 'quantity' => 5];

    $attempt = ConcurrentOperationClaim::make($idempotencyKey, $request, $sku);

    $results = Concurrency::run([$attempt, $attempt]);

    expect($results[1])->toBe($results[0])
        ->and(Operation::query()->where('idempotency_key', $idempotencyKey)->count())->toBe(1)
        ->and(Product::query()->where('sku', $sku)->count())->toBe(1);
});

it('rolls back the claim and callback writes when execution fails', function () {
    $service = app(OperationService::class);
    $idempotencyKey = 'receipt-rollback-key';
    $sku = 'ROLLED-BACK-RECEIPT';

    expect(fn () => DB::transaction(
        fn (): array => $service->execute(
            Type::ReceiveStock,
            $idempotencyKey,
            ['product_id' => 10, 'warehouse_id' => 20, 'quantity' => 5],
            function () use ($sku): array {
                Product::query()->create([
                    'sku' => $sku,
                    'name' => 'Rolled back receipt marker',
                    'is_active' => true,
                ]);

                throw new RuntimeException('Injected operation failure.');
            },
        ),
    ))->toThrow(RuntimeException::class)
        ->and(Operation::query()->where('idempotency_key', $idempotencyKey)->doesntExist())->toBeTrue()
        ->and(Product::query()->where('sku', $sku)->doesntExist())->toBeTrue();

    $result = DB::transaction(
        fn (): array => $service->execute(
            Type::ReceiveStock,
            $idempotencyKey,
            ['product_id' => 10, 'warehouse_id' => 20, 'quantity' => 5],
            fn (): array => ['retried' => true],
        ),
    );

    expect($result)->toBe(['retried' => true])
        ->and(Operation::query()->where('idempotency_key', $idempotencyKey)->count())->toBe(1);
});
