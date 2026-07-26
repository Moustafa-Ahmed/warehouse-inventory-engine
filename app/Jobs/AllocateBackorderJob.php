<?php

namespace App\Jobs;

use App\Services\Reservations\ReservationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class AllocateBackorderJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(
        public readonly int $warehouseId,
        public readonly string $runKey,
        public readonly int $batchSize = 50,
    ) {}

    public function handle(ReservationService $reservations): void
    {
        $reservations->allocateBackorders(
            runKey: $this->runKey,
            warehouseId: $this->warehouseId,
            batchSize: $this->batchSize,
        );
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [5, 15];
    }
}
