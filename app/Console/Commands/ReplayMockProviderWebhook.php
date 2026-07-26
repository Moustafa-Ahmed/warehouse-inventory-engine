<?php

namespace App\Console\Commands;

use App\Services\Shipping\MockProviderControlService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use InvalidArgumentException;
use LogicException;

#[Signature('mock-provider:replay-webhook
    {mockProviderShipmentId : Mock-provider shipment whose latest webhook should be replayed}')]
#[Description('Replay the latest mock-provider callback with its original identity and body')]
final class ReplayMockProviderWebhook extends Command
{
    public function handle(MockProviderControlService $controls): int
    {
        try {
            $webhookId = $controls->replayLastWebhook(
                (int) $this->argument('mockProviderShipmentId'),
            );
        } catch (InvalidArgumentException|LogicException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Dispatched mock-provider webhook replay [{$webhookId}].");

        return self::SUCCESS;
    }
}
