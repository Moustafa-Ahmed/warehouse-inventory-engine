<?php

namespace App\Services\Orders;

use App\DTOs\Orders\CreateOrderInput;
use App\DTOs\Orders\CreateOrderItemInput;
use App\DTOs\Orders\CreateOrderResult;
use App\Enums\Operations\Type;
use App\Models\Operation;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\Operations\OperationService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class OrderService
{
    public function __construct(
        private readonly OperationService $operations,
        private readonly OrderItemProgressCalculator $progress,
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
}
