<?php

use App\Enums\ProviderWebhookReceipts\Status;
use App\Models\ProviderWebhookReceipt;
use App\Services\Shipping\WebhookSignature;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;

it('secures and deduplicates the provider webhook boundary', function (
    string $case,
    int $expectedStatus,
    int $expectedReceiptCount,
) {
    Queue::fake();
    config()->set('shipping.webhook.providers.mock.secret', 'test-webhook-secret');
    config()->set('shipping.webhook.replay_window_seconds', 300);
    $eventId = 'provider-event-'.$case;
    $timestamp = now()->timestamp;
    $body = providerWebhookBody($eventId);
    $headers = providerWebhookHeaders($eventId, $timestamp, $body);

    if ($case === 'missing_signature') {
        unset($headers['HTTP_X_PROVIDER_SIGNATURE']);
    } elseif ($case === 'expired_timestamp') {
        $timestamp = now()->subMinutes(10)->timestamp;
        $headers = providerWebhookHeaders($eventId, $timestamp, $body);
    } elseif ($case === 'invalid_signature') {
        $headers['HTTP_X_PROVIDER_SIGNATURE'] = str_repeat('0', 64);
    } elseif ($case === 'malformed_json') {
        $body = '{';
        $headers = providerWebhookHeaders($eventId, $timestamp, $body);
    }

    $firstResponse = sendProviderWebhook($body, $headers);
    $originalBody = $body;

    if ($case === 'identical_duplicate') {
        $response = sendProviderWebhook($body, $headers);
    } elseif ($case === 'completed_duplicate') {
        ProviderWebhookReceipt::query()->sole()->forceFill([
            'status' => Status::Processed,
            'processed_at' => now(),
        ])->save();
        $response = sendProviderWebhook($body, $headers);
    } elseif ($case === 'mismatched_body') {
        $changedPayload = json_decode($body, true, flags: JSON_THROW_ON_ERROR);
        $changedPayload['items'][0]['quantity'] = 2;
        $changedBody = json_encode($changedPayload, JSON_THROW_ON_ERROR);
        $response = sendProviderWebhook(
            $changedBody,
            providerWebhookHeaders($eventId, $timestamp, $changedBody),
        );
    } else {
        $response = $firstResponse;
    }

    $response->assertStatus($expectedStatus);

    expect(ProviderWebhookReceipt::query()->count())->toBe($expectedReceiptCount);

    if ($expectedReceiptCount === 1) {
        $receipt = ProviderWebhookReceipt::query()->sole();

        expect($receipt->raw_body)->toBe($originalBody);
    }

    if ($case === 'identical_duplicate') {
        $response->assertJson([
            'duplicate' => true,
            'processing' => 'pending',
        ]);
    } elseif ($case === 'completed_duplicate') {
        $response->assertJson([
            'duplicate' => true,
            'processing' => 'complete',
        ]);
    }
})->with([
    'valid' => ['valid', 202, 1],
    'missing signature' => ['missing_signature', 422, 0],
    'expired timestamp' => ['expired_timestamp', 422, 0],
    'invalid signature' => ['invalid_signature', 422, 0],
    'malformed JSON' => ['malformed_json', 422, 0],
    'identical pending duplicate' => ['identical_duplicate', 202, 1],
    'completed duplicate' => ['completed_duplicate', 200, 1],
    'mismatched-body identity collision' => ['mismatched_body', 409, 1],
]);

function providerWebhookBody(string $eventId): string
{
    return json_encode([
        'external_event_id' => $eventId,
        'event_type' => 'shipment.confirmed',
        'external_shipment_id' => 'mock-shipment-42',
        'provider_request_key' => 'provider-request-42',
        'occurred_at' => now()->toISOString(),
        'items' => [
            ['shipment_item_id' => 101, 'quantity' => 1],
        ],
    ], JSON_THROW_ON_ERROR);
}

/**
 * @return array<string, string>
 */
function providerWebhookHeaders(
    string $eventId,
    int $timestamp,
    string $body,
): array {
    return [
        'HTTP_ACCEPT' => 'application/json',
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_SHIPPING_PROVIDER' => 'mock',
        'HTTP_X_PROVIDER_EVENT_ID' => $eventId,
        'HTTP_X_PROVIDER_TIMESTAMP' => (string) $timestamp,
        'HTTP_X_PROVIDER_SIGNATURE' => app(WebhookSignature::class)->sign(
            $timestamp,
            $body,
            'test-webhook-secret',
        ),
    ];
}

/**
 * @param  array<string, string>  $headers
 */
function sendProviderWebhook(string $body, array $headers): TestResponse
{
    return test()->call(
        'POST',
        route('webhooks.shipping-provider'),
        server: $headers,
        content: $body,
    );
}
