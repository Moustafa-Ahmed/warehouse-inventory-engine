<?php

namespace App\Http\Controllers;

use App\DTOs\Orders\OrderItemProgress;
use App\Enums\Reservations\Kind;
use App\Enums\Reservations\Status;
use App\Exceptions\IdempotencyConflictException;
use App\Exceptions\InsufficientReservedQuantityException;
use App\Http\Requests\Reservations\AllocateReservationRequest;
use App\Http\Requests\Reservations\ConfirmReservationRequest;
use App\Http\Requests\Reservations\ReleaseReservationRequest;
use App\Http\Requests\Reservations\ReserveOrderItemRequest;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Reservation;
use App\Services\Orders\OrderItemProgressCalculator;
use App\Services\Reservations\ReservationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class ReservationController extends Controller
{
    public function store(
        ReserveOrderItemRequest $request,
        Order $order,
        OrderItem $item,
        ReservationService $reservations,
    ): RedirectResponse {
        try {
            $result = $reservations->reserve($request->toInput($item));
        } catch (IdempotencyConflictException $exception) {
            return $this->rejectedToOrder(
                $order,
                'reservation_operation_key',
                $exception->getMessage(),
                'conflict',
            );
        } catch (InvalidArgumentException $exception) {
            return $this->rejectedToOrder(
                $order,
                'reservation',
                $exception->getMessage(),
                'domain_rejection',
            );
        }

        return redirect()
            ->route('reservations.show', $result->reservationId)
            ->with('status', 'Reservation attempt completed.')
            ->with('allocation_result', [
                'operation_id' => $result->operationId,
                'requested_quantity' => $result->requestedQuantity,
                'allocated_quantity' => $result->allocatedQuantity,
                'outstanding_quantity' => $result->outstandingQuantity,
                'fully_allocated' => $result->fullyAllocated,
            ]);
    }

    public function show(
        Reservation $reservation,
        OrderItemProgressCalculator $progress,
    ): View {
        $reservation->load([
            'orderItem.order:id,order_number',
            'orderItem.product:id,sku,name',
            'warehouse:id,code,name',
            'transitions' => fn ($query) => $query
                ->with('actor:id,name')
                ->latest('id'),
        ]);
        $orderItem = $reservation->orderItem;
        $remainingRequestedQuantity = max(
            0,
            $reservation->requested_quantity
                - $reservation->reserved_quantity
                - $reservation->picked_quantity
                - $reservation->packed_quantity
                - $reservation->shipped_quantity
                - $reservation->released_quantity,
        );

        return view('reservations.show', [
            'reservation' => $reservation,
            'orderItemProgress' => $progress->calculate(
                orderedQuantity: $orderItem->ordered_quantity,
                cancelledQuantity: $orderItem->cancelled_quantity,
                reservedQuantity: $orderItem->reserved_quantity,
                pickedQuantity: $orderItem->picked_quantity,
                packedQuantity: $orderItem->packed_quantity,
                shippedQuantity: $orderItem->shipped_quantity,
                deliveredQuantity: $orderItem->delivered_quantity,
            ),
            'remainingRequestedQuantity' => $remainingRequestedQuantity,
            'canConfirm' => $reservation->kind === Kind::Temporary
                && $reservation->status === Status::Open
                && $reservation->expires_at?->isFuture() === true,
            'canRelease' => $reservation->reserved_quantity > 0,
            'canAllocate' => $reservation->status === Status::Open
                && $remainingRequestedQuantity > 0
                && (
                    $reservation->kind === Kind::Confirmed
                    || $reservation->expires_at?->isFuture() === true
                ),
            'confirmationOperationKey' => (string) Str::uuid(),
            'releaseOperationKey' => (string) Str::uuid(),
            'allocationRunKey' => (string) Str::uuid(),
        ]);
    }

    public function confirm(
        ConfirmReservationRequest $request,
        Reservation $reservation,
        ReservationService $reservations,
    ): RedirectResponse {
        try {
            $result = $reservations->confirm($request->toInput($reservation));
        } catch (IdempotencyConflictException $exception) {
            return $this->rejectedToReservation(
                $reservation,
                'confirmation_operation_key',
                $exception->getMessage(),
                'conflict',
            );
        } catch (InvalidArgumentException $exception) {
            return $this->rejectedToReservation(
                $reservation,
                'confirmation',
                $exception->getMessage(),
                'domain_rejection',
            );
        }

        return redirect()
            ->route('reservations.show', $reservation)
            ->with('status', 'Temporary reservation confirmed.')
            ->with('operation_result', [
                'type' => 'reservation_confirmed',
                'operation_id' => $result->operationId,
                'reservation_id' => $result->reservationId,
            ]);
    }

    public function release(
        ReleaseReservationRequest $request,
        Reservation $reservation,
        ReservationService $reservations,
    ): RedirectResponse {
        try {
            $result = $reservations->release($request->toInput($reservation));
        } catch (IdempotencyConflictException $exception) {
            return $this->rejectedToReservation(
                $reservation,
                'release_operation_key',
                $exception->getMessage(),
                'conflict',
            );
        } catch (InsufficientReservedQuantityException|InvalidArgumentException $exception) {
            return $this->rejectedToReservation(
                $reservation,
                'release',
                $exception->getMessage(),
                'domain_rejection',
            );
        }

        return redirect()
            ->route('reservations.show', $reservation)
            ->with('status', 'Reserved inventory released.')
            ->with('operation_result', [
                'type' => 'reservation_released',
                'operation_id' => $result->operationId,
                'released_quantity' => $result->releasedQuantity,
                'cancelled_quantity' => $result->cancelledQuantity,
                'remaining_reserved_quantity' => $result->remainingReservedQuantity,
                'outstanding_quantity' => $result->outstandingQuantity,
            ]);
    }

    public function allocate(
        AllocateReservationRequest $request,
        Reservation $reservation,
        ReservationService $reservations,
        OrderItemProgressCalculator $progress,
    ): RedirectResponse {
        $reservation->load('orderItem');
        $beforeAllocatedQuantity = $this->activeReservationQuantity($reservation);
        $beforeProgress = $this->progressFor($reservation->orderItem, $progress);

        try {
            $warehouseAllocatedQuantity = $reservations->allocateBackorders(
                runKey: $request->validated('allocation_run_key'),
                warehouseId: $reservation->warehouse_id,
            );
        } catch (InvalidArgumentException $exception) {
            return $this->rejectedToReservation(
                $reservation,
                'allocation',
                $exception->getMessage(),
                'domain_rejection',
            );
        }

        $reservation->refresh();
        $reservation->orderItem->refresh();
        $afterProgress = $this->progressFor($reservation->orderItem, $progress);

        return redirect()
            ->route('reservations.show', $reservation)
            ->with('status', 'Warehouse FIFO allocation run completed.')
            ->with('allocation_result', [
                'requested_quantity' => $beforeProgress->outstandingQuantity,
                'allocated_quantity' => $this->activeReservationQuantity($reservation)
                    - $beforeAllocatedQuantity,
                'outstanding_quantity' => $afterProgress->outstandingQuantity,
                'fully_allocated' => $afterProgress->outstandingQuantity === 0,
                'warehouse_allocated_quantity' => $warehouseAllocatedQuantity,
            ]);
    }

    private function activeReservationQuantity(Reservation $reservation): int
    {
        return $reservation->reserved_quantity
            + $reservation->picked_quantity
            + $reservation->packed_quantity
            + $reservation->shipped_quantity;
    }

    private function progressFor(
        OrderItem $orderItem,
        OrderItemProgressCalculator $progress,
    ): OrderItemProgress {
        return $progress->calculate(
            orderedQuantity: $orderItem->ordered_quantity,
            cancelledQuantity: $orderItem->cancelled_quantity,
            reservedQuantity: $orderItem->reserved_quantity,
            pickedQuantity: $orderItem->picked_quantity,
            packedQuantity: $orderItem->packed_quantity,
            shippedQuantity: $orderItem->shipped_quantity,
            deliveredQuantity: $orderItem->delivered_quantity,
        );
    }

    private function rejectedToOrder(
        Order $order,
        string $field,
        string $message,
        string $messageType,
    ): RedirectResponse {
        return redirect()
            ->route('orders.show', $order)
            ->withInput()
            ->withErrors([$field => $message])
            ->with('message_type', $messageType);
    }

    private function rejectedToReservation(
        Reservation $reservation,
        string $field,
        string $message,
        string $messageType,
    ): RedirectResponse {
        return redirect()
            ->route('reservations.show', $reservation)
            ->withInput()
            ->withErrors([$field => $message])
            ->with('message_type', $messageType);
    }
}
