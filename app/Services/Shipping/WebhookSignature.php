<?php

namespace App\Services\Shipping;

final class WebhookSignature
{
    public function sign(int $timestamp, string $rawBody, string $secret): string
    {
        return hash_hmac('sha256', $timestamp.'.'.$rawBody, $secret);
    }

    public function isValid(
        int $timestamp,
        string $rawBody,
        string $secret,
        string $signature,
    ): bool {
        return hash_equals(
            $this->sign($timestamp, $rawBody, $secret),
            $signature,
        );
    }
}
