<?php

namespace App\Http\Controllers;

use App\Exceptions\IdempotencyConflictException;
use App\Exceptions\PhysicalReversalRequiredException;
use App\Http\Requests\Orders\EditOrderItemQuantityRequest;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\Orders\OrderService;
use Illuminate\Http\RedirectResponse;
use InvalidArgumentException;

final class OrderItemController extends Controller
{
    public function update(
        EditOrderItemQuantityRequest $request,
        Order $order,
        OrderItem $item,
        OrderService $orders,
    ): RedirectResponse {
        try {
            $result = $orders->editQuantity($request->toInput($item));
        } catch (IdempotencyConflictException $exception) {
            return $this->rejected($order, 'edit_operation_key', $exception->getMessage(), 'conflict');
        } catch (PhysicalReversalRequiredException|InvalidArgumentException $exception) {
            return $this->rejected($order, 'order_item', $exception->getMessage(), 'domain_rejection');
        }

        return redirect()
            ->route('orders.show', $order)
            ->with('status', 'Order item quantity updated.')
            ->with('operation_result', [
                'type' => 'order_item_edited',
                'operation_id' => $result->operationId,
                'order_item_id' => $result->orderItemId,
                'ordered_quantity' => $result->orderedQuantity,
                'quantity_change' => $result->quantityChange,
                'released_reserved_quantity' => $result->releasedReservedQuantity,
                'outstanding_quantity' => $result->outstandingQuantity,
            ]);
    }

    private function rejected(
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
}
