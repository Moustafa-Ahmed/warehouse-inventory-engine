<?php

namespace App\Http\Requests\Reservations;

use App\DTOs\Reservations\ReleaseReservationInput;
use App\Models\Reservation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ReleaseReservationRequest extends FormRequest
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
            'quantity' => ['required', 'integer', 'min:1'],
            'cancel_order_demand' => ['required', 'boolean'],
            'reason' => ['required', 'string', 'max:500'],
            'release_operation_key' => ['required', 'uuid'],
        ];
    }

    public function toInput(Reservation $reservation): ReleaseReservationInput
    {
        $validated = $this->validated();

        return new ReleaseReservationInput(
            reservationId: $reservation->id,
            quantity: (int) $validated['quantity'],
            cancelOrderDemand: (bool) $validated['cancel_order_demand'],
            reason: $validated['reason'],
            idempotencyKey: $validated['release_operation_key'],
            actorId: $this->user()->id,
            source: 'administrator_ui',
        );
    }
}
