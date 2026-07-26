<?php

namespace App\Http\Requests\Inventory;

use App\DTOs\Inventory\ReceiveStockInput;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ReceiveStockRequest extends FormRequest
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
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'source_reference' => ['required', 'string', 'max:255'],
            'operation_key' => ['required', 'uuid'],
        ];
    }

    public function toInput(): ReceiveStockInput
    {
        $validated = $this->validated();

        return new ReceiveStockInput(
            productId: (int) $validated['product_id'],
            warehouseId: (int) $validated['warehouse_id'],
            quantity: (int) $validated['quantity'],
            sourceReference: $validated['source_reference'],
            idempotencyKey: $validated['operation_key'],
            actorId: $this->user()->id,
        );
    }
}
