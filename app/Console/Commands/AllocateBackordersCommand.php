<?php

namespace App\Console\Commands;

use App\Services\Reservations\ReservationService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('inventory:allocate-backorders
            {--warehouse= : Limit recovery to one warehouse ID}
            {--run-key= : Stable key for this recovery sweep}
            {--batch=50 : Maximum reservation requests per warehouse}')]
#[Description('Retry outstanding reservation requests at their selected warehouses')]
final class AllocateBackordersCommand extends Command
{
    public function handle(ReservationService $reservations): int
    {
        $warehouseOption = $this->option('warehouse');
        $warehouseId = is_string($warehouseOption) && $warehouseOption !== ''
            ? (int) $warehouseOption
            : null;
        $batchSize = (int) $this->option('batch');

        if (($warehouseId !== null && $warehouseId < 1) || $batchSize < 1 || $batchSize > 500) {
            $this->components->error('Warehouse and batch options must contain valid positive values.');

            return self::FAILURE;
        }

        $runKeyOption = $this->option('run-key');
        $runKey = is_string($runKeyOption) && trim($runKeyOption) !== ''
            ? $runKeyOption
            : 'scheduled-backorder-sweep-'.now()->format('YmdHi');
        $allocatedQuantity = $reservations->allocateBackorders(
            runKey: $runKey,
            warehouseId: $warehouseId,
            batchSize: $batchSize,
        );

        $this->components->info("Allocated {$allocatedQuantity} backordered units.");

        return self::SUCCESS;
    }
}
