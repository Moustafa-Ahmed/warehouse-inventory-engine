<?php

namespace App\Services\Reservations;

use App\DTOs\Inventory\Movement;
use App\DTOs\Orders\OrderItemProgress;
use App\DTOs\Reservations\ReleaseReservationInput;
use App\DTOs\Reservations\ReleaseReservationResult;
use App\DTOs\Reservations\ReserveOrderItemInput;
use App\DTOs\Reservations\ReserveOrderItemResult;
use App\Enums\Inventory\MovementBucket;
use App\Enums\Operations\Type;
use App\Enums\Reservations\Kind;
use App\Enums\Reservations\Status;
use App\Exceptions\InsufficientReservedQuantityException;
use App\Models\InventoryBalance;
use App\Models\Operation;
use App\Models\OrderItem;
use App\Models\Reservation;
use App\Models\ReservationTransition;
use App\Models\Warehouse;
use App\Services\Inventory\InventoryMovementService;
use App\Services\Operations\OperationService;
use App\Services\Orders\OrderItemProgressCalculator;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class ReservationService
{
    public function __construct(
        private readonly OperationService $operations,
        private readonly InventoryMovementService $movements,
        private readonly OrderItemProgressCalculator $progress,
    ) {}

    public function reserve(ReserveOrderItemInput $input): ReserveOrderItemResult
    {
        $this->validateReserveInput($input);

        $result = DB::transaction(
            fn (): array => $this->operations->execute(
                Type::ReserveOrderItem,
                $input->idempotencyKey,
                [
                    'order_item_id' => $input->orderItemId,
                    'warehouse_id' => $input->warehouseId,
                    'actor_id' => $input->actorId,
                    'source' => $input->source,
                ],
                fn (Operation $operation): array => $this->applyReservation($operation, $input),
            ),
            attempts: 3,
        );

        return new ReserveOrderItemResult(
            operationId: $result['operation_id'],
            reservationId: $result['reservation_id'],
            requestedQuantity: $result['requested_quantity'],
            allocatedQuantity: $result['allocated_quantity'],
            outstandingQuantity: $result['outstanding_quantity'],
            fullyAllocated: $result['fully_allocated'],
        );
    }

    public function release(ReleaseReservationInput $input): ReleaseReservationResult
    {
        $this->validateReleaseInput($input);

        $result = DB::transaction(
            fn (): array => $this->operations->execute(
                Type::ReleaseReservation,
                $input->idempotencyKey,
                [
                    'reservation_id' => $input->reservationId,
                    'quantity' => $input->quantity,
                    'cancel_order_demand' => $input->cancelOrderDemand,
                    'reason' => $input->reason,
                    'actor_id' => $input->actorId,
                    'source' => $input->source,
                ],
                fn (Operation $operation): array => $this->applyRelease($operation, $input),
            ),
            attempts: 3,
        );

        return new ReleaseReservationResult(
            operationId: $result['operation_id'],
            reservationId: $result['reservation_id'],
            releasedQuantity: $result['released_quantity'],
            cancelledQuantity: $result['cancelled_quantity'],
            remainingReservedQuantity: $result['remaining_reserved_quantity'],
            outstandingQuantity: $result['outstanding_quantity'],
        );
    }

    private function validateReserveInput(ReserveOrderItemInput $input): void
    {
        if (trim($input->idempotencyKey) === '') {
            throw new InvalidArgumentException('An idempotency key is required.');
        }

        if (trim($input->source) === '') {
            throw new InvalidArgumentException('A reservation source is required.');
        }
    }

    private function validateReleaseInput(ReleaseReservationInput $input): void
    {
        if ($input->quantity < 1) {
            throw new InvalidArgumentException('Released quantity must be positive.');
        }

        if (trim($input->reason) === '') {
            throw new InvalidArgumentException('A release reason is required.');
        }

        if (trim($input->idempotencyKey) === '') {
            throw new InvalidArgumentException('An idempotency key is required.');
        }

        if (trim($input->source) === '') {
            throw new InvalidArgumentException('A reservation source is required.');
        }
    }

    /**
     * @return array{
     *     operation_id: int,
     *     reservation_id: int|null,
     *     requested_quantity: int,
     *     allocated_quantity: int,
     *     outstanding_quantity: int,
     *     fully_allocated: bool
     * }
     */
    private function applyReservation(
        Operation $operation,
        ReserveOrderItemInput $input,
    ): array {
        $orderItem = OrderItem::query()
            ->lockForUpdate()
            ->findOrFail($input->orderItemId);
        $warehouse = Warehouse::query()->findOrFail($input->warehouseId);

        if (! $warehouse->is_active) {
            throw new InvalidArgumentException("Warehouse [{$warehouse->id}] is inactive.");
        }

        $beforeProgress = $this->progressFor($orderItem);

        if ($beforeProgress->outstandingQuantity === 0) {
            throw new InvalidArgumentException('The order item has no outstanding quantity to reserve.');
        }

        InventoryBalance::query()->firstOrCreate([
            'product_id' => $orderItem->product_id,
            'warehouse_id' => $warehouse->id,
        ]);
        $balance = InventoryBalance::query()
            ->where('product_id', $orderItem->product_id)
            ->where('warehouse_id', $warehouse->id)
            ->lockForUpdate()
            ->sole();
        $allocatedQuantity = min(
            $balance->available_quantity,
            $beforeProgress->outstandingQuantity,
        );
        $outstandingQuantity = $beforeProgress->outstandingQuantity - $allocatedQuantity;

        if ($allocatedQuantity === 0) {
            return [
                'operation_id' => $operation->id,
                'reservation_id' => null,
                'requested_quantity' => $beforeProgress->outstandingQuantity,
                'allocated_quantity' => 0,
                'outstanding_quantity' => $outstandingQuantity,
                'fully_allocated' => false,
            ];
        }

        $reservation = new Reservation([
            'order_item_id' => $orderItem->id,
            'warehouse_id' => $warehouse->id,
            'requested_quantity' => $beforeProgress->outstandingQuantity,
        ]);
        $reservation->forceFill([
            'kind' => Kind::Confirmed,
            'status' => Status::Open,
            'reserved_quantity' => $allocatedQuantity,
            'picked_quantity' => 0,
            'packed_quantity' => 0,
            'shipped_quantity' => 0,
            'released_quantity' => 0,
            'expires_at' => null,
        ])->save();

        $this->movements->apply(
            $operation,
            new Movement(
                productId: $orderItem->product_id,
                quantity: $allocatedQuantity,
                sourceWarehouseId: $warehouse->id,
                sourceBucket: MovementBucket::Available,
                destinationWarehouseId: $warehouse->id,
                destinationBucket: MovementBucket::Reserved,
                businessReferenceType: 'reservation',
                businessReferenceId: (string) $reservation->id,
                actorId: $input->actorId,
            ),
        );

        $orderItem->forceFill([
            'reserved_quantity' => $beforeProgress->reservedQuantity + $allocatedQuantity,
        ])->save();
        $afterProgress = $this->progressFor($orderItem);

        ReservationTransition::query()->create([
            'reservation_id' => $reservation->id,
            'operation_id' => $operation->id,
            'actor_id' => $input->actorId,
            'source' => $input->source,
            'reason' => 'Available inventory reserved',
            'before_kind' => Kind::Confirmed,
            'after_kind' => Kind::Confirmed,
            'before_status' => Status::Open,
            'after_status' => Status::Open,
            'before_reserved_quantity' => 0,
            'after_reserved_quantity' => $allocatedQuantity,
            'before_picked_quantity' => 0,
            'after_picked_quantity' => 0,
            'before_packed_quantity' => 0,
            'after_packed_quantity' => 0,
            'before_shipped_quantity' => 0,
            'after_shipped_quantity' => 0,
            'before_released_quantity' => 0,
            'after_released_quantity' => 0,
        ]);

        return [
            'operation_id' => $operation->id,
            'reservation_id' => $reservation->id,
            'requested_quantity' => $beforeProgress->outstandingQuantity,
            'allocated_quantity' => $allocatedQuantity,
            'outstanding_quantity' => $afterProgress->outstandingQuantity,
            'fully_allocated' => $afterProgress->outstandingQuantity === 0,
        ];
    }

    /**
     * @return array{
     *     operation_id: int,
     *     reservation_id: int,
     *     released_quantity: int,
     *     cancelled_quantity: int,
     *     remaining_reserved_quantity: int,
     *     outstanding_quantity: int
     * }
     */
    private function applyRelease(
        Operation $operation,
        ReleaseReservationInput $input,
    ): array {
        $reservation = Reservation::query()
            ->lockForUpdate()
            ->findOrFail($input->reservationId);
        $orderItem = OrderItem::query()
            ->lockForUpdate()
            ->findOrFail($reservation->order_item_id);

        if ($reservation->reserved_quantity < $input->quantity) {
            throw new InsufficientReservedQuantityException(
                $reservation->id,
                $input->quantity,
                $reservation->reserved_quantity,
            );
        }

        $beforeStatus = $reservation->status;
        $beforeReservedQuantity = $reservation->reserved_quantity;
        $beforeReleasedQuantity = $reservation->released_quantity;
        $remainingReservedQuantity = $beforeReservedQuantity - $input->quantity;
        $remainingActiveQuantity = $remainingReservedQuantity
            + $reservation->picked_quantity
            + $reservation->packed_quantity
            + $reservation->shipped_quantity;
        $afterStatus = $remainingActiveQuantity === 0
            ? Status::Released
            : Status::Open;

        $this->movements->apply(
            $operation,
            new Movement(
                productId: $orderItem->product_id,
                quantity: $input->quantity,
                sourceWarehouseId: $reservation->warehouse_id,
                sourceBucket: MovementBucket::Reserved,
                destinationWarehouseId: $reservation->warehouse_id,
                destinationBucket: MovementBucket::Available,
                businessReferenceType: 'reservation_release',
                businessReferenceId: (string) $reservation->id,
                actorId: $input->actorId,
                metadata: [
                    'cancel_order_demand' => $input->cancelOrderDemand,
                    'reason' => $input->reason,
                ],
            ),
        );

        $reservation->forceFill([
            'status' => $afterStatus,
            'reserved_quantity' => $remainingReservedQuantity,
            'released_quantity' => $beforeReleasedQuantity + $input->quantity,
        ])->save();
        $orderItem->forceFill([
            'reserved_quantity' => $orderItem->reserved_quantity - $input->quantity,
            'cancelled_quantity' => $orderItem->cancelled_quantity
                + ($input->cancelOrderDemand ? $input->quantity : 0),
        ])->save();
        $afterProgress = $this->progressFor($orderItem);

        ReservationTransition::query()->create([
            'reservation_id' => $reservation->id,
            'operation_id' => $operation->id,
            'actor_id' => $input->actorId,
            'source' => $input->source,
            'reason' => $input->reason,
            'before_kind' => $reservation->kind,
            'after_kind' => $reservation->kind,
            'before_status' => $beforeStatus,
            'after_status' => $afterStatus,
            'before_reserved_quantity' => $beforeReservedQuantity,
            'after_reserved_quantity' => $remainingReservedQuantity,
            'before_picked_quantity' => $reservation->picked_quantity,
            'after_picked_quantity' => $reservation->picked_quantity,
            'before_packed_quantity' => $reservation->packed_quantity,
            'after_packed_quantity' => $reservation->packed_quantity,
            'before_shipped_quantity' => $reservation->shipped_quantity,
            'after_shipped_quantity' => $reservation->shipped_quantity,
            'before_released_quantity' => $beforeReleasedQuantity,
            'after_released_quantity' => $reservation->released_quantity,
        ]);

        return [
            'operation_id' => $operation->id,
            'reservation_id' => $reservation->id,
            'released_quantity' => $input->quantity,
            'cancelled_quantity' => $input->cancelOrderDemand ? $input->quantity : 0,
            'remaining_reserved_quantity' => $remainingReservedQuantity,
            'outstanding_quantity' => $afterProgress->outstandingQuantity,
        ];
    }

    private function progressFor(OrderItem $orderItem): OrderItemProgress
    {
        return $this->progress->calculate(
            orderedQuantity: $orderItem->ordered_quantity,
            cancelledQuantity: $orderItem->cancelled_quantity,
            reservedQuantity: $orderItem->reserved_quantity,
            pickedQuantity: $orderItem->picked_quantity,
            packedQuantity: $orderItem->packed_quantity,
            shippedQuantity: $orderItem->shipped_quantity,
            deliveredQuantity: $orderItem->delivered_quantity,
        );
    }
}
