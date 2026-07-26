<?php

namespace App\Http\Requests\Reservations;

use App\DTOs\Reservations\ConfirmReservationInput;
use App\Models\Reservation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ConfirmReservationRequest extends FormRequest
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
            'confirmation_operation_key' => ['required', 'uuid'],
        ];
    }

    public function toInput(Reservation $reservation): ConfirmReservationInput
    {
        return new ConfirmReservationInput(
            reservationId: $reservation->id,
            idempotencyKey: $this->validated('confirmation_operation_key'),
            actorId: $this->user()->id,
            source: 'administrator_ui',
        );
    }
}
