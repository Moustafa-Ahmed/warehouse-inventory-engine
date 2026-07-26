<?php

namespace App\Http\Requests\Fulfillment;

use App\DTOs\Fulfillment\PackReservationInput;
use App\Models\Reservation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PackReservationRequest extends FormRequest
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
            'pack_operation_key' => ['required', 'uuid'],
        ];
    }

    public function toInput(Reservation $reservation): PackReservationInput
    {
        return new PackReservationInput(
            reservationId: $reservation->id,
            quantity: (int) $this->validated('quantity'),
            idempotencyKey: $this->validated('pack_operation_key'),
            actorId: $this->user()->id,
            source: 'administrator_ui',
        );
    }
}
