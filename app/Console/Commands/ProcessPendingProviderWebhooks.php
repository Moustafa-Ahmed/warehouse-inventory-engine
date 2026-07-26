<?php

namespace App\Console\Commands;

use App\Enums\ProviderWebhookReceipts\Status;
use App\Jobs\ProcessProviderWebhookJob;
use App\Models\ProviderWebhookReceipt;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('provider-webhooks:process-pending {--limit=50 : Maximum webhook receipts to dispatch}')]
#[Description('Dispatch processing for pending provider webhook receipts')]
final class ProcessPendingProviderWebhooks extends Command
{
    public function handle(): int
    {
        $receiptIds = ProviderWebhookReceipt::query()
            ->where('status', Status::Pending->value)
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->limit(max(1, (int) $this->option('limit')))
            ->pluck('id');

        foreach ($receiptIds as $receiptId) {
            ProcessProviderWebhookJob::dispatch((int) $receiptId);
        }

        $this->info("Dispatched {$receiptIds->count()} provider webhook receipt(s).");

        return self::SUCCESS;
    }
}
