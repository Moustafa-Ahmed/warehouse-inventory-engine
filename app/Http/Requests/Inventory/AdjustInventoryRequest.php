<?php

namespace App\Http\Requests\Inventory;

use App\DTOs\Inventory\AdjustInventoryInput;
use App\Models\InventoryBalance;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AdjustInventoryRequest extends FormRequest
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
            'adjustment_operation_key' => ['required', 'uuid'],
        ];
    }

    public function toInput(InventoryBalance $inventoryBalance): AdjustInventoryInput
    {
        $validated = $this->validated();

        return new AdjustInventoryInput(
            productId: $inventoryBalance->product_id,
            warehouseId: $inventoryBalance->warehouse_id,
            quantityChange: (int) $validated['quantity_change'],
            reason: $validated['reason'],
            idempotencyKey: $validated['adjustment_operation_key'],
            actorId: $this->user()->id,
        );
    }
}
