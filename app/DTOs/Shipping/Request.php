<?php

namespace App\DTOs\Shipping;

final readonly class Request
{
    public function __construct(
        public string $providerRequestKey,
        public string $shipmentReference,
    ) {}
}
