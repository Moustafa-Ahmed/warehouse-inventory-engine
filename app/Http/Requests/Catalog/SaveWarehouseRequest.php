<?php

namespace App\Http\Requests\Catalog;

use App\DTOs\Catalog\SaveWarehouseInput;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveWarehouseRequest extends FormRequest
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
            'code' => [
                'required',
                'string',
                'max:255',
                Rule::unique('warehouses', 'code')->ignore($this->route('warehouse')),
            ],
            'name' => ['required', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_active' => $this->boolean('is_active')]);
    }

    public function toInput(): SaveWarehouseInput
    {
        $validated = $this->validated();

        return new SaveWarehouseInput(
            code: $validated['code'],
            name: $validated['name'],
            isActive: (bool) $validated['is_active'],
        );
    }
}
