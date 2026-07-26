<?php

use App\Jobs\AllocateBackorderJob;
use App\Jobs\DeliverMockProviderWebhookJob;
use App\Jobs\ProcessProviderWebhookJob;
use App\Jobs\ReconcileProviderSubmissionJob;
use App\Jobs\SubmitShipmentJob;
use App\Models\InventoryBalance;
use App\Models\Operation;
use App\Models\ProviderSubmission;
use App\Models\ProviderWebhookReceipt;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Support\Facades\DB;

it('keeps queue visibility timeouts retries and backoff compatible', function () {
    $retryAfter = (int) config('queue.connections.database.retry_after');
    $jobs = [
        new AllocateBackorderJob(1, 'configuration-review'),
        new DeliverMockProviderWebhookJob(1),
        new ProcessProviderWebhookJob(1),
        new ReconcileProviderSubmissionJob(1),
        new SubmitShipmentJob(1),
    ];

    foreach ($jobs as $job) {
        expect($job->timeout)->toBeGreaterThan(0)
            ->and($retryAfter)->toBeGreaterThan($job->timeout)
            ->and($job->tries)->toBeGreaterThanOrEqual(1);

        if (method_exists($job, 'backoff')) {
            $backoff = $job->backoff();

            expect($backoff)->not->toBeEmpty()
                ->and($backoff)->each->toBeInt()
                ->and(max($backoff))->toBeLessThan($retryAfter);
        }
    }

    expect(new SubmitShipmentJob(1))
        ->toBeInstanceOf(ShouldBeUniqueUntilProcessing::class)
        ->and(new ReconcileProviderSubmissionJob(1))
        ->toBeInstanceOf(ShouldBeUniqueUntilProcessing::class)
        ->and(new ProcessProviderWebhookJob(1))
        ->toBeInstanceOf(ShouldBeUniqueUntilProcessing::class)
        ->and((new DeliverMockProviderWebhookJob(1))->tries)->toBe(1)
        ->and(config('shipping.mock_provider.maximum_delivery_attempts'))
        ->toBeGreaterThan(1)
        ->and(config('shipping.mock_provider.request_timeout_seconds'))
        ->toBeLessThan((new DeliverMockProviderWebhookJob(1))->timeout);
});

it('uses unique indexes for critical idempotency and identity lookups', function () {
    $operation = Operation::factory()->create([
        'idempotency_key' => 'lookup-key',
    ]);
    $balance = InventoryBalance::factory()->create();
    $submission = ProviderSubmission::factory()->create([
        'provider_request_key' => 'provider-key',
    ]);
    $receipt = ProviderWebhookReceipt::factory()->create([
        'provider' => 'mock',
        'external_event_id' => 'event-key',
    ]);
    $plans = [
        'operations_idempotency_key_unique' => DB::selectOne(
            'EXPLAIN SELECT id FROM operations WHERE idempotency_key = ?',
            [$operation->idempotency_key],
        ),
        'inventory_balances_product_id_warehouse_id_unique' => DB::selectOne(
            'EXPLAIN SELECT id FROM inventory_balances WHERE product_id = ? AND warehouse_id = ?',
            [$balance->product_id, $balance->warehouse_id],
        ),
        'provider_submissions_provider_request_key_unique' => DB::selectOne(
            'EXPLAIN SELECT id FROM provider_submissions WHERE provider_request_key = ?',
            [$submission->provider_request_key],
        ),
        'provider_webhook_receipts_provider_external_event_id_unique' => DB::selectOne(
            'EXPLAIN SELECT id FROM provider_webhook_receipts WHERE provider = ? AND external_event_id = ?',
            [$receipt->provider, $receipt->external_event_id],
        ),
    ];

    foreach ($plans as $expectedIndex => $plan) {
        expect($plan->key)->toBe($expectedIndex);
    }
});
