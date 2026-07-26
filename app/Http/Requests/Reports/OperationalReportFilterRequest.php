<?php

namespace App\Http\Requests\Reports;

use App\Enums\Inventory\MovementBucket;
use App\Enums\Reservations\Kind;
use App\Enums\Reservations\Status;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OperationalReportFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('operate') === true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'order_number' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::enum(Status::class)],
            'kind' => ['nullable', Rule::enum(Kind::class)],
            'minimum_age_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'expires_after' => ['nullable', 'date'],
            'expires_before' => ['nullable', 'date', 'after_or_equal:expires_after'],
            'bucket' => ['nullable', Rule::enum(MovementBucket::class)],
            'reference_type' => ['nullable', 'string', 'max:255'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ];
    }
}
