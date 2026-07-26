<?php

namespace App\Services\Orders;

use App\DTOs\Orders\CreateOrderInput;
use App\DTOs\Orders\CreateOrderItemInput;
use App\DTOs\Orders\CreateOrderResult;
use App\DTOs\Orders\EditOrderItemQuantityInput;
use App\DTOs\Orders\EditOrderItemQuantityResult;
use App\DTOs\Orders\OrderItemProgress;
use App\DTOs\Reservations\ReleaseReservationInput;
use App\Enums\Operations\Type;
use App\Exceptions\PhysicalReversalRequiredException;
use App\Models\Operation;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Reservation;
use App\Services\Operations\OperationService;
use App\Services\Reservations\ReservationService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

final class OrderService
{
    public function __construct(
        private readonly OperationService $operations,
        private readonly OrderItemProgressCalculator $progress,
        private readonly ReservationService $reservations,
    ) {}

    public function create(CreateOrderInput $input): CreateOrderResult
    {
        $this->validateInput($input);

        $request = [
            'order_number' => $input->orderNumber,
            'items' => array_map(
                fn (CreateOrderItemInput $item): array => [
                    'product_id' => $item->productId,
                    'ordered_quantity' => $item->orderedQuantity,
                ],
                $input->items,
            ),
        ];

        $result = DB::transaction(
            fn (): array => $this->operations->execute(
                Type::CreateOrder,
                $input->idempotencyKey,
                $request,
                fn (Operation $operation): array => $this->createOrder($operation, $input),
            ),
            attempts: 3,
        );

        return new CreateOrderResult(
            operationId: $result['operation_id'],
            orderId: $result['order_id'],
            orderNumber: $result['order_number'],
            items: $result['items'],
        );
    }

    public function editQuantity(EditOrderItemQuantityInput $input): EditOrderItemQuantityResult
    {
        $this->validateEditInput($input);

        $result = DB::transaction(
            fn (): array => $this->operations->execute(
                Type::EditOrderItemQuantity,
                $input->idempotencyKey,
                [
                    'order_item_id' => $input->orderItemId,
                    'quantity_change' => $input->quantityChange,
                    'reason' => $input->reason,
                    'actor_id' => $input->actorId,
                    'source' => $input->source,
                ],
                fn (Operation $operation): array => $this->applyQuantityEdit($operation, $input),
            ),
            attempts: 3,
        );

        return new EditOrderItemQuantityResult(
            operationId: $result['operation_id'],
            orderItemId: $result['order_item_id'],
            previousOrderedQuantity: $result['previous_ordered_quantity'],
            orderedQuantity: $result['ordered_quantity'],
            quantityChange: $result['quantity_change'],
            releasedReservedQuantity: $result['released_reserved_quantity'],
            outstandingQuantity: $result['outstanding_quantity'],
        );
    }

    private function validateInput(CreateOrderInput $input): void
    {
        if (trim($input->orderNumber) === '') {
            throw new InvalidArgumentException('An order number is required.');
        }

        if ($input->items === []) {
            throw new InvalidArgumentException('An order must contain at least one item.');
        }

        if (trim($input->idempotencyKey) === '') {
            throw new InvalidArgumentException('An idempotency key is required.');
        }

        foreach ($input->items as $item) {
            if (! $item instanceof CreateOrderItemInput) {
                throw new InvalidArgumentException('Every order item must be a CreateOrderItemInput.');
            }

            if ($item->orderedQuantity < 1) {
                throw new InvalidArgumentException('Every ordered quantity must be positive.');
            }
        }
    }

    private function validateEditInput(EditOrderItemQuantityInput $input): void
    {
        if ($input->quantityChange === 0) {
            throw new InvalidArgumentException('Order item quantity change must not be zero.');
        }

        if (trim($input->reason) === '') {
            throw new InvalidArgumentException('An order edit reason is required.');
        }

        if (trim($input->idempotencyKey) === '') {
            throw new InvalidArgumentException('An idempotency key is required.');
        }

        if (trim($input->source) === '') {
            throw new InvalidArgumentException('An order edit source is required.');
        }
    }

    /**
     * @return array{
     *     operation_id: int,
     *     order_id: int,
     *     order_number: string,
     *     items: array<int, array{
     *         order_item_id: int,
     *         product_id: int,
     *         ordered_quantity: int,
     *         outstanding_quantity: int
     *     }>
     * }
     */
    private function createOrder(Operation $operation, CreateOrderInput $input): array
    {
        $products = Product::query()
            ->whereIn('id', array_unique(array_map(
                fn (CreateOrderItemInput $item): int => $item->productId,
                $input->items,
            )))
            ->get()
            ->keyBy('id');

        foreach ($input->items as $item) {
            $product = $products->get($item->productId);

            if ($product === null) {
                throw new InvalidArgumentException("Product [{$item->productId}] does not exist.");
            }

            if (! $product->is_active) {
                throw new InvalidArgumentException("Product [{$item->productId}] is inactive.");
            }
        }

        $order = Order::query()->create([
            'order_number' => $input->orderNumber,
        ]);
        $createdItems = [];

        foreach ($input->items as $item) {
            $progress = $this->progress->calculate(
                orderedQuantity: $item->orderedQuantity,
                cancelledQuantity: 0,
                reservedQuantity: 0,
                pickedQuantity: 0,
                packedQuantity: 0,
                shippedQuantity: 0,
                deliveredQuantity: 0,
            );
            $orderItem = new OrderItem([
                'product_id' => $item->productId,
                'ordered_quantity' => $progress->orderedQuantity,
            ]);

            $orderItem->forceFill([
                'cancelled_quantity' => $progress->cancelledQuantity,
                'reserved_quantity' => $progress->reservedQuantity,
                'picked_quantity' => $progress->pickedQuantity,
                'packed_quantity' => $progress->packedQuantity,
                'shipped_quantity' => $progress->shippedQuantity,
                'delivered_quantity' => $progress->deliveredQuantity,
            ]);
            $order->items()->save($orderItem);

            $createdItems[] = [
                'order_item_id' => $orderItem->id,
                'product_id' => $orderItem->product_id,
                'ordered_quantity' => $progress->orderedQuantity,
                'outstanding_quantity' => $progress->outstandingQuantity,
            ];
        }

        return [
            'operation_id' => $operation->id,
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'items' => $createdItems,
        ];
    }

    /**
     * @return array{
     *     operation_id: int,
     *     order_item_id: int,
     *     previous_ordered_quantity: int,
     *     ordered_quantity: int,
     *     quantity_change: int,
     *     released_reserved_quantity: int,
     *     outstanding_quantity: int
     * }
     */
    private function applyQuantityEdit(
        Operation $operation,
        EditOrderItemQuantityInput $input,
    ): array {
        $orderItem = OrderItem::query()
            ->lockForUpdate()
            ->findOrFail($input->orderItemId);
        $beforeProgress = $this->progressFor($orderItem);
        $orderedQuantity = $orderItem->ordered_quantity + $input->quantityChange;

        if ($orderedQuantity < 1) {
            throw new InvalidArgumentException('Ordered quantity must remain positive.');
        }

        if ($orderedQuantity < $orderItem->shipped_quantity + $orderItem->cancelled_quantity) {
            throw new InvalidArgumentException(
                'Ordered quantity cannot be reduced below shipped and cancelled quantity.'
            );
        }

        $releasedReservedQuantity = 0;

        if ($input->quantityChange < 0) {
            $requestedReduction = abs($input->quantityChange);
            $reducibleQuantity = $beforeProgress->outstandingQuantity
                + $beforeProgress->reservedQuantity;

            if ($requestedReduction > $reducibleQuantity) {
                throw new PhysicalReversalRequiredException(
                    orderItemId: $orderItem->id,
                    requestedReduction: $requestedReduction,
                    reducibleQuantity: $reducibleQuantity,
                    pickedQuantity: $orderItem->picked_quantity,
                    packedQuantity: $orderItem->packed_quantity,
                );
            }

            $releasedReservedQuantity = max(
                0,
                $requestedReduction - $beforeProgress->outstandingQuantity,
            );
            $this->releaseReservedQuantityForEdit(
                $orderItem,
                $releasedReservedQuantity,
                $input,
            );
            $orderItem->refresh();
        }

        $orderItem->forceFill([
            'ordered_quantity' => $orderedQuantity,
        ])->save();
        $afterProgress = $this->progressFor($orderItem);

        return [
            'operation_id' => $operation->id,
            'order_item_id' => $orderItem->id,
            'previous_ordered_quantity' => $beforeProgress->orderedQuantity,
            'ordered_quantity' => $afterProgress->orderedQuantity,
            'quantity_change' => $input->quantityChange,
            'released_reserved_quantity' => $releasedReservedQuantity,
            'outstanding_quantity' => $afterProgress->outstandingQuantity,
        ];
    }

    private function releaseReservedQuantityForEdit(
        OrderItem $orderItem,
        int $quantity,
        EditOrderItemQuantityInput $input,
    ): void {
        if ($quantity === 0) {
            return;
        }

        $remainingQuantity = $quantity;
        $reservations = Reservation::query()
            ->where('order_item_id', $orderItem->id)
            ->where('reserved_quantity', '>', 0)
            ->orderBy('id')
            ->get();

        foreach ($reservations as $reservation) {
            $releaseQuantity = min($remainingQuantity, $reservation->reserved_quantity);

            $this->reservations->release(new ReleaseReservationInput(
                reservationId: $reservation->id,
                quantity: $releaseQuantity,
                cancelOrderDemand: false,
                reason: $input->reason,
                idempotencyKey: $this->releaseOperationKey(
                    $input->idempotencyKey,
                    $reservation->id,
                    $releaseQuantity,
                ),
                actorId: $input->actorId,
                source: $input->source,
            ));
            $remainingQuantity -= $releaseQuantity;

            if ($remainingQuantity === 0) {
                break;
            }
        }

        if ($remainingQuantity !== 0) {
            throw new LogicException(
                'Order item reserved quantity does not match its reservation records.'
            );
        }
    }

    private function releaseOperationKey(
        string $editOperationKey,
        int $reservationId,
        int $quantity,
    ): string {
        return 'order-edit-release-'.hash(
            'sha256',
            "{$editOperationKey}|{$reservationId}|{$quantity}",
        );
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
