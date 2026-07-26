<?php

namespace App\Http\Controllers;

use App\Exceptions\IdempotencyConflictException;
use App\Http\Requests\Fulfillment\PackReservationRequest;
use App\Http\Requests\Fulfillment\PickReservationRequest;
use App\Http\Requests\Fulfillment\ReturnPickedInventoryRequest;
use App\Http\Requests\Fulfillment\UnpackReservationRequest;
use App\Models\Reservation;
use App\Services\Fulfillment\FulfillmentService;
use Illuminate\Http\RedirectResponse;
use InvalidArgumentException;

final class FulfillmentController extends Controller
{
    public function pick(
        PickReservationRequest $request,
        Reservation $reservation,
        FulfillmentService $fulfillment,
    ): RedirectResponse {
        try {
            $result = $fulfillment->pick($request->toInput($reservation));
        } catch (IdempotencyConflictException $exception) {
            return $this->rejected($reservation, 'pick_operation_key', $exception, 'conflict');
        } catch (InvalidArgumentException $exception) {
            return $this->rejected($reservation, 'pick', $exception, 'domain_rejection');
        }

        return $this->completed($reservation, 'Inventory picked.', [
            'operation_id' => $result->operationId,
            'moved_quantity' => $result->pickedQuantity,
            'remaining_source_quantity' => $result->remainingReservedQuantity,
        ]);
    }

    public function returnPicked(
        ReturnPickedInventoryRequest $request,
        Reservation $reservation,
        FulfillmentService $fulfillment,
    ): RedirectResponse {
        try {
            $result = $fulfillment->returnPicked($request->toInput($reservation));
        } catch (IdempotencyConflictException $exception) {
            return $this->rejected($reservation, 'return_operation_key', $exception, 'conflict');
        } catch (InvalidArgumentException $exception) {
            return $this->rejected($reservation, 'return', $exception, 'domain_rejection');
        }

        return $this->completed($reservation, 'Picked inventory returned to available.', [
            'operation_id' => $result->operationId,
            'moved_quantity' => $result->returnedQuantity,
            'remaining_source_quantity' => $result->remainingPickedQuantity,
        ]);
    }

    public function pack(
        PackReservationRequest $request,
        Reservation $reservation,
        FulfillmentService $fulfillment,
    ): RedirectResponse {
        try {
            $result = $fulfillment->pack($request->toInput($reservation));
        } catch (IdempotencyConflictException $exception) {
            return $this->rejected($reservation, 'pack_operation_key', $exception, 'conflict');
        } catch (InvalidArgumentException $exception) {
            return $this->rejected($reservation, 'pack', $exception, 'domain_rejection');
        }

        return $this->completed($reservation, 'Picked inventory packed.', [
            'operation_id' => $result->operationId,
            'moved_quantity' => $result->packedQuantity,
            'remaining_source_quantity' => $result->remainingPickedQuantity,
        ]);
    }

    public function unpack(
        UnpackReservationRequest $request,
        Reservation $reservation,
        FulfillmentService $fulfillment,
    ): RedirectResponse {
        try {
            $result = $fulfillment->unpack($request->toInput($reservation));
        } catch (IdempotencyConflictException $exception) {
            return $this->rejected($reservation, 'unpack_operation_key', $exception, 'conflict');
        } catch (InvalidArgumentException $exception) {
            return $this->rejected($reservation, 'unpack', $exception, 'domain_rejection');
        }

        return $this->completed($reservation, 'Packed inventory returned to picked.', [
            'operation_id' => $result->operationId,
            'moved_quantity' => $result->unpackedQuantity,
            'remaining_source_quantity' => $result->remainingPackedQuantity,
        ]);
    }

    /** @param array<string, int> $result */
    private function completed(
        Reservation $reservation,
        string $status,
        array $result,
    ): RedirectResponse {
        return redirect()
            ->route('reservations.show', $reservation)
            ->with('status', $status)
            ->with('fulfillment_result', $result);
    }

    private function rejected(
        Reservation $reservation,
        string $field,
        \Throwable $exception,
        string $messageType,
    ): RedirectResponse {
        return redirect()
            ->route('reservations.show', $reservation)
            ->withInput()
            ->withErrors([$field => $exception->getMessage()])
            ->with('message_type', $messageType);
    }
}
