<?php

namespace App\Http\Requests;

use App\DTOs\Shipping\WebhookReceiptInput;
use App\Enums\Shipping\EventType;
use App\Services\Shipping\WebhookSignature;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ShippingProviderWebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            '_provider' => ['required', 'string', Rule::in(array_keys(
                config('shipping.webhook.providers', [])
            ))],
            '_external_event_id' => ['required', 'string', 'max:255'],
            '_timestamp' => ['required', 'integer'],
            '_signature' => ['required', 'string', 'regex:/\A[a-f0-9]{64}\z/i'],
            'external_event_id' => ['required', 'string', 'max:255'],
            'event_type' => ['required', Rule::enum(EventType::class)],
            'external_shipment_id' => ['required', 'string', 'max:255'],
            'provider_request_key' => ['required', 'string', 'max:255'],
            'occurred_at' => ['required', 'date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.shipment_item_id' => ['required', 'integer', 'min:1', 'distinct'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            '_provider' => $this->header('X-Shipping-Provider'),
            '_external_event_id' => $this->header('X-Provider-Event-Id'),
            '_timestamp' => $this->header('X-Provider-Timestamp'),
            '_signature' => $this->header('X-Provider-Signature'),
        ]);
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $validated = $validator->validated();

                if (! hash_equals(
                    $validated['_external_event_id'],
                    $validated['external_event_id'],
                )) {
                    $validator->errors()->add(
                        'external_event_id',
                        'The body event ID must match the provider event header.',
                    );

                    return;
                }

                $timestamp = (int) $validated['_timestamp'];
                $replayWindow = max(
                    1,
                    (int) config('shipping.webhook.replay_window_seconds'),
                );

                if (abs(now()->timestamp - $timestamp) > $replayWindow) {
                    $validator->errors()->add(
                        '_timestamp',
                        'The provider timestamp is outside the accepted replay window.',
                    );

                    return;
                }

                $secret = (string) config(
                    'shipping.webhook.providers.'.$validated['_provider'].'.secret',
                );

                if (
                    $secret === ''
                    || ! app(WebhookSignature::class)->isValid(
                        timestamp: $timestamp,
                        rawBody: $this->getContent(),
                        secret: $secret,
                        signature: $validated['_signature'],
                    )
                ) {
                    $validator->errors()->add(
                        '_signature',
                        'The provider signature is invalid.',
                    );
                }
            },
        ];
    }

    public function toInput(): WebhookReceiptInput
    {
        $validated = $this->validated();

        return new WebhookReceiptInput(
            provider: $validated['_provider'],
            externalEventId: $validated['external_event_id'],
            eventType: EventType::from($validated['event_type']),
            rawBody: $this->getContent(),
            occurredAt: CarbonImmutable::parse($validated['occurred_at']),
        );
    }
}
