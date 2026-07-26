<?php

namespace App\Http\Requests\Shipping;

use App\DTOs\Shipping\CreateShipmentInput;
use App\DTOs\Shipping\CreateShipmentItemInput;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CreateShipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('operate') === true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'order_id' => ['required', 'integer', 'exists:orders,id'],
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*' => ['nullable', 'integer', 'min:1'],
            'shipment_operation_key' => ['required', 'uuid'],
        ];
    }

    public function toInput(): CreateShipmentInput
    {
        $validated = $this->validated();
        $items = [];

        foreach ($validated['items'] as $reservationId => $quantity) {
            if ($quantity === null) {
                continue;
            }

            $items[] = new CreateShipmentItemInput(
                reservationId: (int) $reservationId,
                quantity: (int) $quantity,
            );
        }

        return new CreateShipmentInput(
            orderId: (int) $validated['order_id'],
            warehouseId: (int) $validated['warehouse_id'],
            items: $items,
            idempotencyKey: $validated['shipment_operation_key'],
        );
    }
}
