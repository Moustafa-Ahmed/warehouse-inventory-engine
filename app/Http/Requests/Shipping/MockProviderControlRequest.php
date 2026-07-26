<?php

namespace App\Http\Requests\Shipping;

use Illuminate\Foundation\Http\FormRequest;

class MockProviderControlRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('operate') === true;
    }

    /** @return array<string, never> */
    public function rules(): array
    {
        return [];
    }
}
