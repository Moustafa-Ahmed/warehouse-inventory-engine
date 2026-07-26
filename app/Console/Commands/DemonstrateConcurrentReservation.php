<?php

namespace App\Console\Commands;

use App\Services\Demo\DemoScenarioService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use LogicException;

#[Signature('demo:concurrent-reservation')]
#[Description('Demonstrate two users concurrently reserving the final available unit')]
final class DemonstrateConcurrentReservation extends Command
{
    public function handle(DemoScenarioService $scenarios): int
    {
        try {
            $result = $scenarios->runConcurrentReservation();
        } catch (LogicException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Attempt allocations', 'Available after', 'Reserved after', 'Orders'],
            [[
                implode(', ', $result['allocated_quantities']),
                $result['available_quantity'],
                $result['reserved_quantity'],
                implode(', ', $result['order_ids']),
            ]],
        );
        $this->info('Exactly one user reserved the final unit.');

        return self::SUCCESS;
    }
}
