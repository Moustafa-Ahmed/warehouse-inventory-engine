<?php

namespace App\Services\Shipping;

use App\DTOs\Shipping\CreateShipmentInput;
use App\DTOs\Shipping\CreateShipmentItemInput;
use App\DTOs\Shipping\CreateShipmentResult;
use App\Enums\Operations\Type;
use App\Enums\Reservations\Kind;
use App\Enums\Reservations\Status as ReservationStatus;
use App\Enums\Shipments\Status;
use App\Models\Operation;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Reservation;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use App\Models\Warehouse;
use App\Services\Operations\OperationService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class ShipmentService
{
    public function __construct(
        private readonly OperationService $operations,
    ) {}

    public function create(CreateShipmentInput $input): CreateShipmentResult
    {
        $this->validateInput($input);

        $result = DB::transaction(
            fn (): array => $this->operations->execute(
                Type::CreateShipment,
                $input->idempotencyKey,
                [
                    'order_id' => $input->orderId,
                    'warehouse_id' => $input->warehouseId,
                    'items' => array_map(
                        fn (CreateShipmentItemInput $item): array => [
                            'reservation_id' => $item->reservationId,
                            'quantity' => $item->quantity,
                        ],
                        $input->items,
                    ),
                ],
                fn (Operation $operation): array => $this->composeShipment($operation, $input),
            ),
            attempts: 3,
        );

        return new CreateShipmentResult(
            operationId: $result['operation_id'],
            shipmentId: $result['shipment_id'],
            orderId: $result['order_id'],
            warehouseId: $result['warehouse_id'],
            items: $result['items'],
        );
    }

    private function validateInput(CreateShipmentInput $input): void
    {
        if ($input->items === []) {
            throw new InvalidArgumentException('A shipment must contain at least one item.');
        }

        if (trim($input->idempotencyKey) === '') {
            throw new InvalidArgumentException('An idempotency key is required.');
        }

        $reservationIds = [];

        foreach ($input->items as $item) {
            if (! $item instanceof CreateShipmentItemInput) {
                throw new InvalidArgumentException(
                    'Every shipment item must be a CreateShipmentItemInput.'
                );
            }

            if ($item->quantity < 1) {
                throw new InvalidArgumentException('Shipment item quantity must be positive.');
            }

            if (in_array($item->reservationId, $reservationIds, true)) {
                throw new InvalidArgumentException(
                    'A reservation can appear only once in a shipment.'
                );
            }

            $reservationIds[] = $item->reservationId;
        }
    }

    /**
     * @return array{
     *     operation_id: int,
     *     shipment_id: int,
     *     order_id: int,
     *     warehouse_id: int,
     *     items: array<int, array{
     *         shipment_item_id: int,
     *         reservation_id: int,
     *         quantity: int
     *     }>
     * }
     */
    private function composeShipment(
        Operation $operation,
        CreateShipmentInput $input,
    ): array {
        $order = Order::query()->findOrFail($input->orderId);
        $warehouse = Warehouse::query()->findOrFail($input->warehouseId);

        if (! $warehouse->is_active) {
            throw new InvalidArgumentException('A shipment requires an active warehouse.');
        }

        $reservationIds = array_map(
            fn (CreateShipmentItemInput $item): int => $item->reservationId,
            $input->items,
        );
        $orderItemIds = Reservation::query()
            ->whereIn('id', $reservationIds)
            ->pluck('order_item_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->sort()
            ->values()
            ->all();
        $orderItems = OrderItem::query()
            ->whereIn('id', $orderItemIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');
        $reservations = Reservation::query()
            ->whereIn('id', $reservationIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        if ($reservations->count() !== count($reservationIds)) {
            throw new InvalidArgumentException('One or more shipment reservations do not exist.');
        }

        $this->validateReservations(
            $input,
            $orderItems,
            $reservations,
        );

        $shipment = new Shipment([
            'order_id' => $order->id,
            'warehouse_id' => $warehouse->id,
        ]);
        $shipment->forceFill([
            'status' => Status::PendingHandoff,
            'shipped_at' => null,
        ])->save();
        $createdItems = [];

        foreach ($input->items as $item) {
            $shipmentItem = new ShipmentItem([
                'reservation_id' => $item->reservationId,
                'quantity' => $item->quantity,
            ]);
            $shipmentItem->forceFill(['delivered_quantity' => 0]);
            $shipment->items()->save($shipmentItem);

            $createdItems[] = [
                'shipment_item_id' => $shipmentItem->id,
                'reservation_id' => $shipmentItem->reservation_id,
                'quantity' => $shipmentItem->quantity,
            ];
        }

        return [
            'operation_id' => $operation->id,
            'shipment_id' => $shipment->id,
            'order_id' => $shipment->order_id,
            'warehouse_id' => $shipment->warehouse_id,
            'items' => $createdItems,
        ];
    }

    /**
     * @param  Collection<int, OrderItem>  $orderItems
     * @param  Collection<int, Reservation>  $reservations
     */
    private function validateReservations(
        CreateShipmentInput $input,
        Collection $orderItems,
        Collection $reservations,
    ): void {
        foreach ($input->items as $item) {
            $reservation = $reservations->get($item->reservationId);
            $orderItem = $orderItems->get($reservation->order_item_id);

            if (
                $reservation->kind !== Kind::Confirmed
                || $reservation->status !== ReservationStatus::Open
            ) {
                throw new InvalidArgumentException(
                    'Shipment inventory requires an open confirmed reservation.'
                );
            }

            if (
                $reservation->warehouse_id !== $input->warehouseId
                || $orderItem?->order_id !== $input->orderId
            ) {
                throw new InvalidArgumentException(
                    'Every shipment reservation must belong to its order and warehouse.'
                );
            }

            $assignedPackedQuantity = (int) ShipmentItem::query()
                ->join('shipments', 'shipments.id', '=', 'shipment_items.shipment_id')
                ->where('shipment_items.reservation_id', $reservation->id)
                ->where('shipments.status', Status::PendingHandoff->value)
                ->sum('shipment_items.quantity');
            $unassignedPackedQuantity = $reservation->packed_quantity
                - $assignedPackedQuantity;

            if ($item->quantity > $unassignedPackedQuantity) {
                throw new InvalidArgumentException(
                    'Shipment quantity cannot exceed unassigned packed inventory.'
                );
            }
        }
    }
}
