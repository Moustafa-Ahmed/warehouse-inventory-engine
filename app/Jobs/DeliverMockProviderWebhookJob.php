<?php

namespace App\Jobs;

use App\Services\Shipping\MockProviderWebhookDeliveryService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class DeliverMockProviderWebhookJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 30;

    public bool $failOnTimeout = true;

    public function __construct(
        public readonly int $webhookId,
    ) {}

    public function handle(MockProviderWebhookDeliveryService $delivery): void
    {
        $delivery->deliver($this->webhookId);
    }
}
