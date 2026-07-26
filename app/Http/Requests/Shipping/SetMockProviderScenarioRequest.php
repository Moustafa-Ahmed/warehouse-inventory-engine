<?php

namespace App\Http\Requests\Shipping;

use App\Enums\Shipping\Scenario;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SetMockProviderScenarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('operate') === true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'scenario' => ['required', Rule::enum(Scenario::class)],
        ];
    }
}
