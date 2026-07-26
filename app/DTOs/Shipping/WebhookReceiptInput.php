<?php

namespace App\DTOs\Shipping;

use App\Enums\Shipping\EventType;
use Carbon\CarbonImmutable;

final readonly class WebhookReceiptInput
{
    public function __construct(
        public string $provider,
        public string $externalEventId,
        public EventType $eventType,
        public string $rawBody,
        public CarbonImmutable $occurredAt,
    ) {}
}
