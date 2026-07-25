<?php

namespace App\DTOs\Shipping;

use App\Enums\Shipping\CallbackIntent;
use App\Enums\Shipping\Outcome;

final readonly class Result
{
    public function __construct(
        public string $providerRequestKey,
        public ?string $externalShipmentId,
        public Outcome $outcome,
        public ?CallbackIntent $callbackIntent,
    ) {}
}
