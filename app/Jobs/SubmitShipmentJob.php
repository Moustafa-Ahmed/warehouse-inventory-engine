<?php

namespace App\Jobs;

use App\Services\Shipping\ShipmentSubmissionService;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

final class SubmitShipmentJob implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 20;

    public bool $failOnTimeout = true;

    public function __construct(
        public readonly int $shipmentId,
    ) {}

    public function handle(ShipmentSubmissionService $submissions): void
    {
        $submissions->submit($this->shipmentId);
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [5, 15, 30];
    }

    public function uniqueId(): string
    {
        return (string) $this->shipmentId;
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Shipment submission job failed.', [
            'shipment_id' => $this->shipmentId,
            'exception' => $exception !== null ? $exception::class : null,
        ]);
    }
}
