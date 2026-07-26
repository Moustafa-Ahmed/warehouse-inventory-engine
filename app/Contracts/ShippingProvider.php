<?php

namespace App\Contracts;

use App\DTOs\Shipping\Request;
use App\DTOs\Shipping\Result;

interface ShippingProvider
{
    public function submit(Request $request): Result;

    public function statusFor(string $providerRequestKey): ?Result;

    public function requestHandoffConfirmationRedelivery(string $providerRequestKey): void;
}
