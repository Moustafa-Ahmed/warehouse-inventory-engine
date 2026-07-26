<?php

use App\Enums\MockProviderWebhooks\Status;
use App\Jobs\DeliverMockProviderWebhookJob;
use App\Models\MockProviderWebhook;
use App\Services\Shipping\MockProviderWebhookDeliveryService;
use App\Services\Shipping\WebhookSignature;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

it('delivers signed callbacks and records recoverable transport outcomes', function () {
    config()->set([
        'shipping.mock_provider.webhook_url' => 'https://warehouse.test/webhooks/shipping-provider',
        'shipping.webhook.providers.mock.secret' => 'delivery-test-secret',
        'shipping.mock_provider.maximum_delivery_attempts' => 3,
        'shipping.mock_provider.retry_base_seconds' => 10,
        'shipping.mock_provider.delivery_lease_seconds' => 60,
        'queue.default' => 'database',
    ]);
    $delivery = app(MockProviderWebhookDeliveryService::class);
    $acknowledged = MockProviderWebhook::factory()->create([
        'next_delivery_at' => now()->subSecond(),
    ]);
    $originalBody = $acknowledged->raw_body;
    Http::fakeSequence('https://warehouse.test/*')
        ->push([], 202)
        ->push([], 503)
        ->push([], 401);

    $delivery->deliver($acknowledged->id);

    $acknowledged->refresh();
    expect($acknowledged->status)->toBe(Status::Acknowledged)
        ->and($acknowledged->attempt_count)->toBe(1)
        ->and($acknowledged->last_response_status_code)->toBe(202)
        ->and($acknowledged->raw_body)->toBe($originalBody);
    Http::assertSent(function (Request $request) use ($acknowledged, $originalBody): bool {
        $timestamp = (int) $request->header('X-Provider-Timestamp')[0];

        return $request->url() === 'https://warehouse.test/webhooks/shipping-provider'
            && $request->body() === $originalBody
            && $request->header('X-Provider-Event-Id')[0] === $acknowledged->external_event_id
            && app(WebhookSignature::class)->isValid(
                $timestamp,
                $originalBody,
                'delivery-test-secret',
                $request->header('X-Provider-Signature')[0],
            );
    });

    $retryable = MockProviderWebhook::factory()->create([
        'next_delivery_at' => now()->subSecond(),
    ]);

    $delivery->deliver($retryable->id);

    $retryable->refresh();
    expect($retryable->status)->toBe(Status::RetryScheduled)
        ->and($retryable->attempt_count)->toBe(1)
        ->and($retryable->last_response_status_code)->toBe(503)
        ->and($retryable->next_delivery_at->isFuture())->toBeTrue();

    $permanent = MockProviderWebhook::factory()->create([
        'next_delivery_at' => now()->subSecond(),
    ]);

    $delivery->deliver($permanent->id);

    expect($permanent->refresh()->status)->toBe(Status::PermanentlyFailed)
        ->and($permanent->attempt_count)->toBe(1)
        ->and($permanent->last_response_status_code)->toBe(401);

    $expiredClaim = MockProviderWebhook::factory()->delivering()->create([
        'last_attempted_at' => now()->subMinutes(2),
    ]);
    Queue::fake();

    $this->artisan('mock-provider:dispatch-pending', ['--limit' => 10])
        ->expectsOutput('Dispatched 1 mock-provider callback(s).')
        ->assertSuccessful();

    Queue::assertPushed(
        DeliverMockProviderWebhookJob::class,
        fn (DeliverMockProviderWebhookJob $job): bool => $job->webhookId === $expiredClaim->id,
    );
});
