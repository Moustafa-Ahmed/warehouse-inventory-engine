<?php

namespace App\Jobs;

use App\Services\Shipping\ShipmentSubmissionService;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class ReconcileProviderSubmissionJob implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 20;

    public function __construct(
        public readonly int $providerSubmissionId,
    ) {}

    public function handle(ShipmentSubmissionService $submissions): void
    {
        $submissions->reconcile($this->providerSubmissionId);
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function uniqueId(): string
    {
        return (string) $this->providerSubmissionId;
    }
}
