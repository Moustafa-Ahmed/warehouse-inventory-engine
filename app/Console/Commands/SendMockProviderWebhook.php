<?php

namespace App\Console\Commands;

use App\Enums\Shipping\EventType;
use App\Services\Shipping\MockProviderControlService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use InvalidArgumentException;
use LogicException;

#[Signature('mock-provider:send-webhook
    {mockProviderShipmentId : Mock-provider shipment ID}
    {eventType : shipment.confirmed or delivery.confirmed}
    {--out-of-order : Allow delivery confirmation before provider handoff}')]
#[Description('Send a deterministic mock-provider callback')]
final class SendMockProviderWebhook extends Command
{
    public function handle(MockProviderControlService $controls): int
    {
        $eventType = EventType::tryFrom((string) $this->argument('eventType'));

        if ($eventType === null) {
            $this->error('Event type must be shipment.confirmed or delivery.confirmed.');

            return self::INVALID;
        }

        try {
            $webhookId = match ($eventType) {
                EventType::ShipmentConfirmed => $controls->sendHandoffConfirmation(
                    (int) $this->argument('mockProviderShipmentId'),
                ),
                EventType::DeliveryConfirmed => $this->option('out-of-order')
                    ? $controls->sendOutOfOrderDelivery(
                        (int) $this->argument('mockProviderShipmentId'),
                    )
                    : $controls->sendDeliveryConfirmation(
                        (int) $this->argument('mockProviderShipmentId'),
                    ),
            };
        } catch (InvalidArgumentException|LogicException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Dispatched mock-provider webhook [{$webhookId}].");

        return self::SUCCESS;
    }
}
