<?php

namespace App\DTOs\Shipping;

final readonly class WebhookReceiptResult
{
    public function __construct(
        public int $receiptId,
        public bool $wasCreated,
        public bool $requiresProcessing,
    ) {}
}
