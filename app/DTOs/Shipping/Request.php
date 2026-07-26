<?php

namespace App\DTOs\Shipping;

final readonly class Request
{
    /**
     * @param  list<RequestItem>  $items
     */
    public function __construct(
        public string $providerRequestKey,
        public string $shipmentReference,
        public array $items = [],
    ) {}
}
