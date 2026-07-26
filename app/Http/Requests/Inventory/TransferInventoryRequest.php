<?php

namespace App\Http\Requests\Inventory;

use App\DTOs\Inventory\TransferStockInput;
use App\Models\InventoryBalance;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransferInventoryRequest extends FormRequest
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
        /** @var InventoryBalance $inventoryBalance */
        $inventoryBalance = $this->route('inventoryBalance');

        return [
            'destination_warehouse_id' => [
                'required',
                'integer',
                'exists:warehouses,id',
                Rule::notIn([$inventoryBalance->warehouse_id]),
            ],
            'quantity' => ['required', 'integer', 'min:1'],
            'transfer_operation_key' => ['required', 'uuid'],
        ];
    }

    public function toInput(InventoryBalance $inventoryBalance): TransferStockInput
    {
        $validated = $this->validated();

        return new TransferStockInput(
            productId: $inventoryBalance->product_id,
            sourceWarehouseId: $inventoryBalance->warehouse_id,
            destinationWarehouseId: (int) $validated['destination_warehouse_id'],
            quantity: (int) $validated['quantity'],
            idempotencyKey: $validated['transfer_operation_key'],
            actorId: $this->user()->id,
        );
    }
}
