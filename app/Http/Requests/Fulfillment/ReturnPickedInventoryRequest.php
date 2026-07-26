<?php

namespace App\Http\Requests\Fulfillment;

use App\DTOs\Fulfillment\ReturnPickedInventoryInput;
use App\Models\Reservation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ReturnPickedInventoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('operate') === true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'quantity' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:500'],
            'return_operation_key' => ['required', 'uuid'],
        ];
    }

    public function toInput(Reservation $reservation): ReturnPickedInventoryInput
    {
        return new ReturnPickedInventoryInput(
            reservationId: $reservation->id,
            quantity: (int) $this->validated('quantity'),
            reason: $this->validated('reason'),
            idempotencyKey: $this->validated('return_operation_key'),
            actorId: $this->user()->id,
            source: 'administrator_ui',
        );
    }
}
