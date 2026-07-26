<?php

namespace App\Http\Controllers;

use App\Exceptions\IdempotencyConflictException;
use App\Http\Requests\Orders\CreateOrderRequest;
use App\Models\Order;
use App\Models\Product;
use App\Models\Warehouse;
use App\Services\Orders\OrderItemProgressCalculator;
use App\Services\Orders\OrderService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class OrderController extends Controller
{
    public function index(): View
    {
        return view('orders.index', [
            'orders' => Order::query()
                ->withCount('items')
                ->latest('id')
                ->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('orders.create', [
            'products' => Product::query()
                ->where('is_active', true)
                ->orderBy('sku')
                ->get(['id', 'sku', 'name']),
            'operationKey' => (string) Str::uuid(),
        ]);
    }

    public function store(
        CreateOrderRequest $request,
        OrderService $orders,
    ): RedirectResponse {
        try {
            $result = $orders->create($request->toInput());
        } catch (IdempotencyConflictException $exception) {
            return redirect()
                ->route('orders.create')
                ->withInput()
                ->withErrors(['order_operation_key' => $exception->getMessage()])
                ->with('message_type', 'conflict');
        } catch (UniqueConstraintViolationException) {
            return redirect()
                ->route('orders.create')
                ->withInput()
                ->withErrors(['order_number' => 'That order number already exists.'])
                ->with('message_type', 'domain_rejection');
        } catch (InvalidArgumentException $exception) {
            return redirect()
                ->route('orders.create')
                ->withInput()
                ->withErrors(['order' => $exception->getMessage()])
                ->with('message_type', 'domain_rejection');
        }

        return redirect()
            ->route('orders.show', $result->orderId)
            ->with('status', 'Order created.')
            ->with('operation_result', [
                'type' => 'order_created',
                'operation_id' => $result->operationId,
                'order_id' => $result->orderId,
            ]);
    }

    public function show(
        Order $order,
        OrderItemProgressCalculator $progress,
    ): View {
        $order->load([
            'items' => [
                'product:id,sku,name',
                'reservations' => [
                    'warehouse:id,code,name',
                ],
            ],
        ]);

        return view('orders.show', [
            'order' => $order,
            'itemRows' => $order->items->map(fn ($item): array => [
                'item' => $item,
                'progress' => $progress->calculate(
                    orderedQuantity: $item->ordered_quantity,
                    cancelledQuantity: $item->cancelled_quantity,
                    reservedQuantity: $item->reserved_quantity,
                    pickedQuantity: $item->picked_quantity,
                    packedQuantity: $item->packed_quantity,
                    shippedQuantity: $item->shipped_quantity,
                    deliveredQuantity: $item->delivered_quantity,
                ),
                'editOperationKey' => (string) Str::uuid(),
                'reservationOperationKey' => (string) Str::uuid(),
            ]),
            'warehouses' => Warehouse::query()
                ->where('is_active', true)
                ->orderBy('code')
                ->get(['id', 'code', 'name']),
        ]);
    }
}
