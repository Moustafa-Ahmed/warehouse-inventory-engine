<?php

namespace App\Services\Shipping;

use App\DTOs\Inventory\Movement;
use App\DTOs\Shipping\CreateShipmentInput;
use App\DTOs\Shipping\CreateShipmentItemInput;
use App\DTOs\Shipping\CreateShipmentResult;
use App\Enums\Inventory\MovementBucket;
use App\Enums\Operations\Type;
use App\Enums\ProviderSubmissions\Status as ProviderSubmissionStatus;
use App\Enums\ProviderWebhookReceipts\Status as ReceiptStatus;
use App\Enums\Reservations\Kind;
use App\Enums\Reservations\Status as ReservationStatus;
use App\Enums\Shipments\Status;
use App\Enums\Shipping\EventType;
use App\Models\Operation;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProviderSubmission;
use App\Models\ProviderWebhookReceipt;
use App\Models\Reservation;
use App\Models\ReservationTransition;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use App\Models\Warehouse;
use App\Services\Inventory\InventoryMovementService;
use App\Services\Operations\OperationService;
use App\Services\Orders\OrderItemProgressCalculator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class ShipmentService
{
    public function __construct(
        private readonly OperationService $operations,
        private readonly InventoryMovementService $movements,
        private readonly OrderItemProgressCalculator $progress,
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

    public function confirmHandoff(int $providerWebhookReceiptId): void
    {
        $receipt = ProviderWebhookReceipt::query()->findOrFail($providerWebhookReceiptId);

        DB::transaction(
            fn (): array => $this->operations->execute(
                Type::ConfirmShipmentHandoff,
                'provider-webhook-receipt-'.$receipt->id,
                [
                    'provider_webhook_receipt_id' => $receipt->id,
                    'raw_body_hash' => hash('sha256', $receipt->raw_body),
                ],
                fn (Operation $operation): array => $this->applyHandoffConfirmation(
                    $operation,
                    $receipt->id,
                ),
            ),
            attempts: 3,
        );
    }

    public function confirmDelivery(int $providerWebhookReceiptId): void
    {
        $receipt = ProviderWebhookReceipt::query()->findOrFail($providerWebhookReceiptId);

        DB::transaction(
            fn (): array => $this->operations->execute(
                Type::ConfirmShipmentDelivery,
                'provider-webhook-receipt-'.$receipt->id,
                [
                    'provider_webhook_receipt_id' => $receipt->id,
                    'raw_body_hash' => hash('sha256', $receipt->raw_body),
                ],
                fn (Operation $operation): array => $this->applyDeliveryConfirmation(
                    $operation,
                    $receipt->id,
                ),
            ),
            attempts: 3,
        );
    }

    /**
     * @return array{operation_id: int, shipment_id: int}
     */
    private function applyDeliveryConfirmation(
        Operation $operation,
        int $receiptId,
    ): array {
        $receipt = ProviderWebhookReceipt::query()
            ->lockForUpdate()
            ->findOrFail($receiptId);

        if (
            $receipt->event_type !== EventType::DeliveryConfirmed
            || ! in_array($receipt->status, [
                ReceiptStatus::Pending,
                ReceiptStatus::RetryableFailure,
            ], true)
        ) {
            throw new InvalidArgumentException(
                'The provider webhook receipt is not eligible for delivery confirmation.'
            );
        }

        /** @var array<string, mixed> $payload */
        $payload = json_decode($receipt->raw_body, true, flags: JSON_THROW_ON_ERROR);
        $submission = ProviderSubmission::query()
            ->where('provider_request_key', $payload['provider_request_key'])
            ->lockForUpdate()
            ->firstOrFail();

        if (
            $submission->external_shipment_id === null
            || ! hash_equals(
                $submission->external_shipment_id,
                $payload['external_shipment_id'],
            )
        ) {
            throw new InvalidArgumentException(
                'The delivery callback does not match the provider submission.'
            );
        }

        $shipment = Shipment::query()
            ->lockForUpdate()
            ->findOrFail($submission->shipment_id);

        if ($shipment->status !== Status::Shipped) {
            throw new InvalidArgumentException(
                'Delivery can advance only after carrier handoff is confirmed.'
            );
        }

        $callbackItems = collect($payload['items'])
            ->mapWithKeys(fn (array $item): array => [
                (int) $item['shipment_item_id'] => (int) $item['quantity'],
            ])
            ->sortKeys();
        $shipmentItems = ShipmentItem::query()
            ->where('shipment_id', $shipment->id)
            ->whereIn('id', $callbackItems->keys())
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        if ($shipmentItems->count() !== $callbackItems->count()) {
            throw new InvalidArgumentException(
                'The delivery callback contains an item outside the shipment.'
            );
        }

        $reservationIds = $shipmentItems->pluck('reservation_id');
        $reservations = Reservation::query()
            ->whereIn('id', $reservationIds)
            ->orderBy('id')
            ->get()
            ->keyBy('id');
        $orderItems = OrderItem::query()
            ->whereIn('id', $reservations->pluck('order_item_id')->unique())
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        foreach ($callbackItems as $shipmentItemId => $quantity) {
            $shipmentItem = $shipmentItems->get($shipmentItemId);
            $remainingQuantity = $shipmentItem->quantity
                - $shipmentItem->delivered_quantity;

            if ($quantity < 1 || $quantity > $remainingQuantity) {
                throw new InvalidArgumentException(
                    'Delivered quantity must be positive and cannot exceed the shipment item quantity.'
                );
            }

            $reservation = $reservations->get($shipmentItem->reservation_id);
            $orderItem = $orderItems->get($reservation->order_item_id);
            $shipmentItem->forceFill([
                'delivered_quantity' => $shipmentItem->delivered_quantity + $quantity,
            ])->save();
            $orderItem->forceFill([
                'delivered_quantity' => $orderItem->delivered_quantity + $quantity,
            ])->save();
            $this->progress->calculate(
                $orderItem->ordered_quantity,
                $orderItem->cancelled_quantity,
                $orderItem->reserved_quantity,
                $orderItem->picked_quantity,
                $orderItem->packed_quantity,
                $orderItem->shipped_quantity,
                $orderItem->delivered_quantity,
            );
        }

        $receipt->forceFill([
            'status' => ReceiptStatus::Processed,
            'failure_reason' => null,
            'processed_at' => now(),
        ])->save();

        return [
            'operation_id' => $operation->id,
            'shipment_id' => $shipment->id,
        ];
    }

    /**
     * @return array{operation_id: int, shipment_id: int}
     */
    private function applyHandoffConfirmation(
        Operation $operation,
        int $receiptId,
    ): array {
        $receipt = ProviderWebhookReceipt::query()
            ->lockForUpdate()
            ->findOrFail($receiptId);

        if (
            $receipt->event_type !== EventType::ShipmentConfirmed
            || ! in_array($receipt->status, [
                ReceiptStatus::Pending,
                ReceiptStatus::RetryableFailure,
            ], true)
        ) {
            throw new InvalidArgumentException(
                'The provider webhook receipt is not eligible for shipment confirmation.'
            );
        }

        /** @var array<string, mixed> $payload */
        $payload = json_decode($receipt->raw_body, true, flags: JSON_THROW_ON_ERROR);
        $submission = ProviderSubmission::query()
            ->where('provider_request_key', $payload['provider_request_key'])
            ->lockForUpdate()
            ->firstOrFail();

        if (
            $submission->external_shipment_id !== null
            && ! hash_equals(
                $submission->external_shipment_id,
                $payload['external_shipment_id'],
            )
        ) {
            throw new InvalidArgumentException(
                'The callback external shipment does not match the provider submission.'
            );
        }

        $shipment = Shipment::query()
            ->lockForUpdate()
            ->findOrFail($submission->shipment_id);

        if ($shipment->status !== Status::PendingHandoff) {
            throw new InvalidArgumentException('Only a shipment pending handoff can be confirmed.');
        }

        $shipmentItems = ShipmentItem::query()
            ->where('shipment_id', $shipment->id)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
        $callbackItems = collect($payload['items'])
            ->mapWithKeys(fn (array $item): array => [
                (int) $item['shipment_item_id'] => (int) $item['quantity'],
            ])
            ->sortKeys();
        $expectedItems = $shipmentItems
            ->mapWithKeys(fn (ShipmentItem $item): array => [
                $item->id => $item->quantity,
            ])
            ->sortKeys();

        if ($callbackItems->all() !== $expectedItems->all()) {
            throw new InvalidArgumentException(
                'Shipment confirmation must include every composed shipment item.'
            );
        }

        $reservationIds = $shipmentItems->pluck('reservation_id')->sort()->values();
        $reservations = Reservation::query()
            ->whereIn('id', $reservationIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');
        $orderItems = OrderItem::query()
            ->whereIn('id', $reservations->pluck('order_item_id')->unique())
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        foreach ($shipmentItems as $shipmentItem) {
            $reservation = $reservations->get($shipmentItem->reservation_id);
            $orderItem = $orderItems->get($reservation->order_item_id);
            $quantity = $shipmentItem->quantity;

            if (
                $reservation->kind !== Kind::Confirmed
                || $reservation->status !== ReservationStatus::Open
                || $reservation->warehouse_id !== $shipment->warehouse_id
                || $orderItem?->order_id !== $shipment->order_id
            ) {
                throw new InvalidArgumentException(
                    'Shipment inventory must remain an open confirmed reservation for its order and warehouse.'
                );
            }

            if ($reservation->packed_quantity < $quantity) {
                throw new InvalidArgumentException(
                    'Shipment confirmation exceeds the reservation packed quantity.'
                );
            }

            $beforePackedQuantity = $reservation->packed_quantity;
            $beforeShippedQuantity = $reservation->shipped_quantity;
            $afterPackedQuantity = $beforePackedQuantity - $quantity;
            $afterShippedQuantity = $beforeShippedQuantity + $quantity;
            $afterStatus = $reservation->reserved_quantity
                + $reservation->picked_quantity
                + $afterPackedQuantity === 0
                    ? ReservationStatus::Closed
                    : ReservationStatus::Open;

            $this->movements->apply($operation, new Movement(
                productId: $orderItem->product_id,
                quantity: $quantity,
                sourceWarehouseId: $shipment->warehouse_id,
                sourceBucket: MovementBucket::Packed,
                destinationWarehouseId: null,
                destinationBucket: MovementBucket::Shipped,
                businessReferenceType: 'shipment_handoff',
                businessReferenceId: (string) $shipment->id,
                metadata: ['shipment_item_id' => $shipmentItem->id],
            ));

            $reservation->forceFill([
                'status' => $afterStatus,
                'packed_quantity' => $afterPackedQuantity,
                'shipped_quantity' => $afterShippedQuantity,
            ])->save();
            $orderItem->forceFill([
                'packed_quantity' => $orderItem->packed_quantity - $quantity,
                'shipped_quantity' => $orderItem->shipped_quantity + $quantity,
            ])->save();
            $this->progress->calculate(
                $orderItem->ordered_quantity,
                $orderItem->cancelled_quantity,
                $orderItem->reserved_quantity,
                $orderItem->picked_quantity,
                $orderItem->packed_quantity,
                $orderItem->shipped_quantity,
                $orderItem->delivered_quantity,
            );

            ReservationTransition::query()->create([
                'reservation_id' => $reservation->id,
                'operation_id' => $operation->id,
                'source' => 'provider_webhook',
                'reason' => 'Carrier handoff confirmed',
                'before_kind' => $reservation->kind,
                'after_kind' => $reservation->kind,
                'before_status' => ReservationStatus::Open,
                'after_status' => $afterStatus,
                'before_reserved_quantity' => $reservation->reserved_quantity,
                'after_reserved_quantity' => $reservation->reserved_quantity,
                'before_picked_quantity' => $reservation->picked_quantity,
                'after_picked_quantity' => $reservation->picked_quantity,
                'before_packed_quantity' => $beforePackedQuantity,
                'after_packed_quantity' => $afterPackedQuantity,
                'before_shipped_quantity' => $beforeShippedQuantity,
                'after_shipped_quantity' => $afterShippedQuantity,
                'before_released_quantity' => $reservation->released_quantity,
                'after_released_quantity' => $reservation->released_quantity,
            ]);
        }

        $shipment->forceFill([
            'status' => Status::Shipped,
            'shipped_at' => $receipt->occurred_at,
        ])->save();
        $submission->forceFill([
            'status' => ProviderSubmissionStatus::Accepted,
            'external_shipment_id' => $payload['external_shipment_id'],
            'failure_reason' => null,
            'resolved_at' => now(),
        ])->save();
        $receipt->forceFill([
            'status' => ReceiptStatus::Processed,
            'failure_reason' => null,
            'processed_at' => now(),
        ])->save();

        return [
            'operation_id' => $operation->id,
            'shipment_id' => $shipment->id,
        ];
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
