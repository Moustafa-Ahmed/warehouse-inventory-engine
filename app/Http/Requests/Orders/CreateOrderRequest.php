<?php

namespace App\Http\Requests\Orders;

use App\DTOs\Orders\CreateOrderInput;
use App\DTOs\Orders\CreateOrderItemInput;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CreateOrderRequest extends FormRequest
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
            'order_number' => ['required', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1', 'max:5'],
            'items.*' => ['required', 'array:product_id,ordered_quantity'],
            'items.0.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.0.ordered_quantity' => ['required', 'integer', 'min:1'],
            'items.*.product_id' => [
                'nullable',
                'required_with:items.*.ordered_quantity',
                'integer',
                'exists:products,id',
            ],
            'items.*.ordered_quantity' => [
                'nullable',
                'required_with:items.*.product_id',
                'integer',
                'min:1',
            ],
            'order_operation_key' => ['required', 'uuid'],
        ];
    }

    public function toInput(): CreateOrderInput
    {
        $validated = $this->validated();
        $items = array_values(array_filter(
            $validated['items'],
            fn (array $item): bool => isset($item['product_id'], $item['ordered_quantity']),
        ));

        return new CreateOrderInput(
            orderNumber: $validated['order_number'],
            items: array_map(
                fn (array $item): CreateOrderItemInput => new CreateOrderItemInput(
                    productId: (int) $item['product_id'],
                    orderedQuantity: (int) $item['ordered_quantity'],
                ),
                $items,
            ),
            idempotencyKey: $validated['order_operation_key'],
        );
    }
}
