<?php

namespace App\Jobs;

use App\Services\Shipping\ProviderWebhookService;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class ProcessProviderWebhookJob implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(
        public readonly int $providerWebhookReceiptId,
    ) {}

    public function handle(ProviderWebhookService $webhooks): void
    {
        $webhooks->process($this->providerWebhookReceiptId);
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [5, 15, 30];
    }

    public function uniqueId(): string
    {
        return (string) $this->providerWebhookReceiptId;
    }
}
