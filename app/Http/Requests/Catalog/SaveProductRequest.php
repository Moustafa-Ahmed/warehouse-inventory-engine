<?php

namespace App\Http\Requests\Catalog;

use App\DTOs\Catalog\SaveProductInput;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveProductRequest extends FormRequest
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
            'sku' => [
                'required',
                'string',
                'max:255',
                Rule::unique('products', 'sku')->ignore($this->route('product')),
            ],
            'name' => ['required', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_active' => $this->boolean('is_active')]);
    }

    public function toInput(): SaveProductInput
    {
        $validated = $this->validated();

        return new SaveProductInput(
            sku: $validated['sku'],
            name: $validated['name'],
            isActive: (bool) $validated['is_active'],
        );
    }
}
