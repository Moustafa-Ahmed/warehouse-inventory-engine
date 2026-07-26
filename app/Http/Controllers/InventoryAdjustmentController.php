<?php

namespace App\Http\Controllers;

use App\Exceptions\IdempotencyConflictException;
use App\Exceptions\InsufficientSourceQuantityException;
use App\Http\Requests\Inventory\AdjustInventoryRequest;
use App\Models\InventoryBalance;
use App\Services\Inventory\InventoryService;
use Illuminate\Http\RedirectResponse;
use InvalidArgumentException;
use Throwable;

final class InventoryAdjustmentController extends Controller
{
    public function __invoke(
        AdjustInventoryRequest $request,
        InventoryBalance $inventoryBalance,
        InventoryService $inventory,
    ): RedirectResponse {
        try {
            $result = $inventory->adjust($request->toInput($inventoryBalance));
        } catch (IdempotencyConflictException $exception) {
            return $this->rejected($inventoryBalance, 'adjustment_operation_key', $exception, 'conflict');
        } catch (InsufficientSourceQuantityException|InvalidArgumentException $exception) {
            return $this->rejected($inventoryBalance, 'adjustment', $exception, 'domain_rejection');
        }

        return redirect()
            ->route('inventory.balances.show', $inventoryBalance)
            ->with('status', 'Inventory adjustment recorded.')
            ->with('operation_result', [
                'type' => 'adjustment',
                'operation_id' => $result->operationId,
                'movement_id' => $result->movementId,
                'quantity_change' => $result->quantityChange,
                'available_quantity' => $result->availableQuantity,
                'reason' => $result->reason,
            ]);
    }

    private function rejected(
        InventoryBalance $inventoryBalance,
        string $field,
        Throwable $exception,
        string $messageType,
    ): RedirectResponse {
        return redirect()
            ->route('inventory.balances.show', $inventoryBalance)
            ->withInput()
            ->withErrors([$field => $exception->getMessage()])
            ->with('message_type', $messageType);
    }
}
