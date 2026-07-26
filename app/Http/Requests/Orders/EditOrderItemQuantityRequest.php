<?php

namespace App\Http\Requests\Orders;

use App\DTOs\Orders\EditOrderItemQuantityInput;
use App\Models\OrderItem;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class EditOrderItemQuantityRequest extends FormRequest
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
            'quantity_change' => ['required', 'integer', 'not_in:0'],
            'reason' => ['required', 'string', 'max:500'],
            'edit_operation_key' => ['required', 'uuid'],
        ];
    }

    public function toInput(OrderItem $orderItem): EditOrderItemQuantityInput
    {
        $validated = $this->validated();

        return new EditOrderItemQuantityInput(
            orderItemId: $orderItem->id,
            quantityChange: (int) $validated['quantity_change'],
            reason: $validated['reason'],
            idempotencyKey: $validated['edit_operation_key'],
            actorId: $this->user()->id,
            source: 'administrator_ui',
        );
    }
}
