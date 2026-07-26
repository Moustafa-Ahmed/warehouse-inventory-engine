<?php

namespace App\Http\Requests\Fulfillment;

use App\DTOs\Fulfillment\PickReservationInput;
use App\Models\Reservation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PickReservationRequest extends FormRequest
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
            'pick_operation_key' => ['required', 'uuid'],
        ];
    }

    public function toInput(Reservation $reservation): PickReservationInput
    {
        return new PickReservationInput(
            reservationId: $reservation->id,
            quantity: (int) $this->validated('quantity'),
            idempotencyKey: $this->validated('pick_operation_key'),
            actorId: $this->user()->id,
            source: 'administrator_ui',
        );
    }
}
