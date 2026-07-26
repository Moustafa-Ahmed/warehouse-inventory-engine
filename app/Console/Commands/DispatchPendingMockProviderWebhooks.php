<?php

namespace App\Console\Commands;

use App\Enums\MockProviderWebhooks\Status;
use App\Jobs\DeliverMockProviderWebhookJob;
use App\Models\MockProviderWebhook;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

#[Signature('mock-provider:dispatch-pending {--limit= : Maximum callbacks to dispatch}')]
#[Description('Dispatch due and recoverable mock-provider callbacks')]
final class DispatchPendingMockProviderWebhooks extends Command
{
    public function handle(): int
    {
        if (config('queue.default') === 'sync') {
            $this->error('Mock-provider callbacks require a non-synchronous queue connection.');

            return self::FAILURE;
        }

        $limit = max(
            1,
            (int) ($this->option('limit')
                ?: config('shipping.mock_provider.dispatch_batch_size')),
        );
        $leaseCutoff = now()->subSeconds(
            max(1, (int) config('shipping.mock_provider.delivery_lease_seconds')),
        );
        $webhookIds = MockProviderWebhook::query()
            ->where(function (Builder $query) use ($leaseCutoff): void {
                $query
                    ->where(function (Builder $due): void {
                        $due->whereIn('status', [
                            Status::Pending->value,
                            Status::RetryScheduled->value,
                        ])->where('next_delivery_at', '<=', now());
                    })
                    ->orWhere(function (Builder $expired) use ($leaseCutoff): void {
                        $expired
                            ->where('status', Status::Delivering->value)
                            ->where('last_attempted_at', '<=', $leaseCutoff);
                    });
            })
            ->orderBy('next_delivery_at')
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id');

        foreach ($webhookIds as $webhookId) {
            DeliverMockProviderWebhookJob::dispatch((int) $webhookId);
        }

        $this->info("Dispatched {$webhookIds->count()} mock-provider callback(s).");

        return self::SUCCESS;
    }
}
