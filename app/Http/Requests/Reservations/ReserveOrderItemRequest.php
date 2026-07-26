<?php

namespace App\Http\Requests\Reservations;

use App\DTOs\Reservations\ReserveOrderItemInput;
use App\Enums\Reservations\Kind;
use App\Models\OrderItem;
use DateTimeImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReserveOrderItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('operate') === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'kind' => ['required', Rule::enum(Kind::class)],
            'expires_at' => [
                'nullable',
                'required_if:kind,'.Kind::Temporary->value,
                'prohibited_unless:kind,'.Kind::Temporary->value,
                'date',
                'after:now',
            ],
            'reservation_operation_key' => ['required', 'uuid'],
        ];
    }

    public function toInput(OrderItem $orderItem): ReserveOrderItemInput
    {
        $validated = $this->validated();

        return new ReserveOrderItemInput(
            orderItemId: $orderItem->id,
            warehouseId: (int) $validated['warehouse_id'],
            idempotencyKey: $validated['reservation_operation_key'],
            actorId: $this->user()->id,
            source: 'administrator_ui',
            kind: Kind::from($validated['kind']),
            expiresAt: isset($validated['expires_at'])
                ? new DateTimeImmutable($validated['expires_at'])
                : null,
        );
    }
}
