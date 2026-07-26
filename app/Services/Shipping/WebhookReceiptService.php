<?php

namespace App\Services\Shipping;

use App\DTOs\Shipping\WebhookReceiptInput;
use App\DTOs\Shipping\WebhookReceiptResult;
use App\Enums\ProviderWebhookReceipts\Status;
use App\Exceptions\WebhookIdentityConflictException;
use App\Models\ProviderWebhookReceipt;
use Illuminate\Database\UniqueConstraintViolationException;

final class WebhookReceiptService
{
    public function receive(WebhookReceiptInput $input): WebhookReceiptResult
    {
        try {
            $receipt = ProviderWebhookReceipt::query()->create([
                'provider' => $input->provider,
                'external_event_id' => $input->externalEventId,
                'event_type' => $input->eventType,
                'raw_body' => $input->rawBody,
                'occurred_at' => $input->occurredAt,
            ]);

            return new WebhookReceiptResult(
                receiptId: $receipt->id,
                wasCreated: true,
                requiresProcessing: true,
            );
        } catch (UniqueConstraintViolationException) {
            $receipt = ProviderWebhookReceipt::query()
                ->where('provider', $input->provider)
                ->where('external_event_id', $input->externalEventId)
                ->firstOrFail();
        }

        if (! hash_equals($receipt->raw_body, $input->rawBody)) {
            throw new WebhookIdentityConflictException;
        }

        return new WebhookReceiptResult(
            receiptId: $receipt->id,
            wasCreated: false,
            requiresProcessing: ! in_array($receipt->status, [
                Status::Processed,
                Status::IgnoredAsStale,
            ], true),
        );
    }
}
