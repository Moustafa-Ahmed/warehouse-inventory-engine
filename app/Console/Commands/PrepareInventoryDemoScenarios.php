<?php

namespace App\Console\Commands;

use App\Services\Demo\DemoScenarioService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use LogicException;

#[Signature('demo:inventory-scenarios
    {--reset : Remove existing DEMO-prefixed data before rebuilding}
    {--force : Skip the reset confirmation}')]
#[Description('Prepare deterministic local inventory and provider demonstration scenarios')]
final class PrepareInventoryDemoScenarios extends Command
{
    public function handle(DemoScenarioService $scenarios): int
    {
        try {
            if ($this->option('reset')) {
                if (
                    ! $this->option('force')
                    && ! $this->confirm(
                        'Remove all DEMO-prefixed records before rebuilding the scenarios?',
                    )
                ) {
                    $this->warn('Demo reset cancelled.');

                    return self::SUCCESS;
                }

                $removedOrders = $scenarios->reset();
                $this->info("Removed {$removedOrders} existing demo order(s).");
            }

            $summary = $scenarios->setup();
        } catch (LogicException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Scenario', 'Record', 'Ready demonstration'],
            [
                ['Partial allocation', 'Order '.$summary['partial_order_id'], '6 allocated, 4 outstanding'],
                ['Timeout after acceptance', 'Shipment '.$summary['timeout_shipment_id'], 'Reconcile, then send confirmation'],
                ['Permanent provider failure', 'Shipment '.$summary['failed_shipment_id'], 'Inspect failed provider submission'],
                ['Duplicate callback', 'Shipment '.$summary['duplicate_shipment_id'], 'Send confirmation, then replay it'],
                ['Pending out-of-order callback', 'Receipt '.$summary['pending_receipt_id'], 'Send handoff, then process pending'],
                ['Shipment confirmation', 'Shipment '.$summary['confirmation_shipment_id'], 'Send confirmation now'],
            ],
        );
        $this->info('Deterministic inventory demonstration scenarios are ready.');

        return self::SUCCESS;
    }
}
