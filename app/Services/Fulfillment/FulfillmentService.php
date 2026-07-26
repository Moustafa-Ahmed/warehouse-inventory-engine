<?php

namespace App\Services\Fulfillment;

use App\DTOs\Fulfillment\PickReservationInput;
use App\DTOs\Fulfillment\PickReservationResult;
use App\DTOs\Inventory\Movement;
use App\Enums\Inventory\MovementBucket;
use App\Enums\Operations\Type;
use App\Enums\Reservations\Kind;
use App\Enums\Reservations\Status;
use App\Models\Operation;
use App\Models\OrderItem;
use App\Models\Reservation;
use App\Models\ReservationTransition;
use App\Services\Inventory\InventoryMovementService;
use App\Services\Operations\OperationService;
use App\Services\Orders\OrderItemProgressCalculator;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class FulfillmentService
{
    public function __construct(
        private readonly OperationService $operations,
        private readonly InventoryMovementService $movements,
        private readonly OrderItemProgressCalculator $progress,
    ) {}

    public function pick(PickReservationInput $input): PickReservationResult
    {
        $this->validatePickInput($input);

        $result = DB::transaction(
            fn (): array => $this->operations->execute(
                Type::PickReservation,
                $input->idempotencyKey,
                [
                    'reservation_id' => $input->reservationId,
                    'quantity' => $input->quantity,
                    'actor_id' => $input->actorId,
                    'source' => $input->source,
                ],
                fn (Operation $operation): array => $this->applyPick($operation, $input),
            ),
            attempts: 3,
        );

        return new PickReservationResult(
            operationId: $result['operation_id'],
            reservationId: $result['reservation_id'],
            pickedQuantity: $result['picked_quantity'],
            remainingReservedQuantity: $result['remaining_reserved_quantity'],
            totalPickedQuantity: $result['total_picked_quantity'],
            outstandingQuantity: $result['outstanding_quantity'],
        );
    }

    private function validatePickInput(PickReservationInput $input): void
    {
        if ($input->quantity < 1) {
            throw new InvalidArgumentException('Pick quantity must be positive.');
        }

        if (trim($input->idempotencyKey) === '') {
            throw new InvalidArgumentException('An idempotency key is required.');
        }

        if (trim($input->source) === '') {
            throw new InvalidArgumentException('A fulfillment source is required.');
        }
    }

    /**
     * @return array{
     *     operation_id: int,
     *     reservation_id: int,
     *     picked_quantity: int,
     *     remaining_reserved_quantity: int,
     *     total_picked_quantity: int,
     *     outstanding_quantity: int
     * }
     */
    private function applyPick(
        Operation $operation,
        PickReservationInput $input,
    ): array {
        $orderItemId = Reservation::query()
            ->whereKey($input->reservationId)
            ->valueOrFail('order_item_id');
        $orderItem = OrderItem::query()
            ->lockForUpdate()
            ->findOrFail($orderItemId);
        $reservation = Reservation::query()
            ->lockForUpdate()
            ->findOrFail($input->reservationId);

        if ($reservation->kind !== Kind::Confirmed || $reservation->status !== Status::Open) {
            throw new InvalidArgumentException(
                'Only an open confirmed reservation can be picked.'
            );
        }

        if ($input->quantity > $reservation->reserved_quantity) {
            throw new InvalidArgumentException(
                'Pick quantity cannot exceed the reservation reserved quantity.'
            );
        }

        $beforeReservedQuantity = $reservation->reserved_quantity;
        $beforePickedQuantity = $reservation->picked_quantity;

        $this->movements->apply(
            $operation,
            new Movement(
                productId: $orderItem->product_id,
                quantity: $input->quantity,
                sourceWarehouseId: $reservation->warehouse_id,
                sourceBucket: MovementBucket::Reserved,
                destinationWarehouseId: $reservation->warehouse_id,
                destinationBucket: MovementBucket::Picked,
                businessReferenceType: 'reservation_pick',
                businessReferenceId: (string) $reservation->id,
                actorId: $input->actorId,
            ),
        );

        $reservation->forceFill([
            'reserved_quantity' => $beforeReservedQuantity - $input->quantity,
            'picked_quantity' => $beforePickedQuantity + $input->quantity,
        ])->save();
        $orderItem->forceFill([
            'reserved_quantity' => $orderItem->reserved_quantity - $input->quantity,
            'picked_quantity' => $orderItem->picked_quantity + $input->quantity,
        ])->save();
        $progress = $this->progress->calculate(
            orderedQuantity: $orderItem->ordered_quantity,
            cancelledQuantity: $orderItem->cancelled_quantity,
            reservedQuantity: $orderItem->reserved_quantity,
            pickedQuantity: $orderItem->picked_quantity,
            packedQuantity: $orderItem->packed_quantity,
            shippedQuantity: $orderItem->shipped_quantity,
            deliveredQuantity: $orderItem->delivered_quantity,
        );

        ReservationTransition::query()->create([
            'reservation_id' => $reservation->id,
            'operation_id' => $operation->id,
            'actor_id' => $input->actorId,
            'source' => $input->source,
            'reason' => 'Reserved inventory picked',
            'before_kind' => $reservation->kind,
            'after_kind' => $reservation->kind,
            'before_status' => $reservation->status,
            'after_status' => $reservation->status,
            'before_reserved_quantity' => $beforeReservedQuantity,
            'after_reserved_quantity' => $reservation->reserved_quantity,
            'before_picked_quantity' => $beforePickedQuantity,
            'after_picked_quantity' => $reservation->picked_quantity,
            'before_packed_quantity' => $reservation->packed_quantity,
            'after_packed_quantity' => $reservation->packed_quantity,
            'before_shipped_quantity' => $reservation->shipped_quantity,
            'after_shipped_quantity' => $reservation->shipped_quantity,
            'before_released_quantity' => $reservation->released_quantity,
            'after_released_quantity' => $reservation->released_quantity,
        ]);

        return [
            'operation_id' => $operation->id,
            'reservation_id' => $reservation->id,
            'picked_quantity' => $input->quantity,
            'remaining_reserved_quantity' => $reservation->reserved_quantity,
            'total_picked_quantity' => $reservation->picked_quantity,
            'outstanding_quantity' => $progress->outstandingQuantity,
        ];
    }
}
