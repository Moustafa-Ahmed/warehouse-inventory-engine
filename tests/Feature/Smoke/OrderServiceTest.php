<?php

use App\DTOs\Orders\CreateOrderInput;
use App\DTOs\Orders\CreateOrderItemInput;
use App\Models\Operation;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\Orders\OrderService;

it('creates multi-item order demand once and reports every line as outstanding', function () {
    $products = Product::factory()->count(2)->create();
    $input = new CreateOrderInput(
        orderNumber: 'ORD-SMOKE-001',
        items: [
            new CreateOrderItemInput($products[0]->id, 7),
            new CreateOrderItemInput($products[1]->id, 3),
        ],
        idempotencyKey: 'create-order-smoke-001',
    );
    $service = app(OrderService::class);

    $result = $service->create($input);
    $replayedResult = $service->create($input);
    $items = OrderItem::query()
        ->where('order_id', $result->orderId)
        ->orderBy('id')
        ->get();

    expect($replayedResult)->toEqual($result)
        ->and($result->orderNumber)->toBe('ORD-SMOKE-001')
        ->and($result->items)->toHaveCount(2)
        ->and(array_column($result->items, 'ordered_quantity'))->toBe([7, 3])
        ->and(array_column($result->items, 'outstanding_quantity'))->toBe([7, 3])
        ->and($items->pluck('reserved_quantity')->all())->toBe([0, 0])
        ->and($items->pluck('shipped_quantity')->all())->toBe([0, 0])
        ->and(Order::query()->where('order_number', 'ORD-SMOKE-001')->count())->toBe(1)
        ->and(Operation::query()->where('idempotency_key', 'create-order-smoke-001')->count())->toBe(1);
});

it('rejects invalid quantity and inactive product demand before creating an order', function () {
    $activeProduct = Product::factory()->create();
    $inactiveProduct = Product::factory()->inactive()->create();
    $service = app(OrderService::class);

    expect(fn () => $service->create(new CreateOrderInput(
        orderNumber: 'ORD-SMOKE-INVALID-QUANTITY',
        items: [new CreateOrderItemInput($activeProduct->id, 0)],
        idempotencyKey: 'create-order-smoke-invalid-quantity',
    )))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $service->create(new CreateOrderInput(
            orderNumber: 'ORD-SMOKE-INACTIVE',
            items: [new CreateOrderItemInput($inactiveProduct->id, 1)],
            idempotencyKey: 'create-order-smoke-inactive',
        )))->toThrow(InvalidArgumentException::class)
        ->and(Order::query()->whereIn('order_number', [
            'ORD-SMOKE-INVALID-QUANTITY',
            'ORD-SMOKE-INACTIVE',
        ])->doesntExist())->toBeTrue()
        ->and(Operation::query()->whereIn('idempotency_key', [
            'create-order-smoke-invalid-quantity',
            'create-order-smoke-inactive',
        ])->doesntExist())->toBeTrue();
});
