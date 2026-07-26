<?php

namespace App\Services\Reservations;

use App\DTOs\Inventory\Movement;
use App\DTOs\Orders\OrderItemProgress;
use App\DTOs\Reservations\ReserveOrderItemInput;
use App\DTOs\Reservations\ReserveOrderItemResult;
use App\Enums\Inventory\MovementBucket;
use App\Enums\Operations\Type;
use App\Enums\Reservations\Kind;
use App\Enums\Reservations\Status;
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

    private function validateReserveInput(ReserveOrderItemInput $input): void
    {
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
