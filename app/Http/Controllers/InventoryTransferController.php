<?php

namespace App\Http\Controllers;

use App\Exceptions\IdempotencyConflictException;
use App\Exceptions\InsufficientSourceQuantityException;
use App\Http\Requests\Inventory\TransferInventoryRequest;
use App\Models\InventoryBalance;
use App\Services\Inventory\InventoryService;
use Illuminate\Http\RedirectResponse;
use InvalidArgumentException;
use Throwable;

final class InventoryTransferController extends Controller
{
    public function __invoke(
        TransferInventoryRequest $request,
        InventoryBalance $inventoryBalance,
        InventoryService $inventory,
    ): RedirectResponse {
        try {
            $result = $inventory->transfer($request->toInput($inventoryBalance));
        } catch (IdempotencyConflictException $exception) {
            return $this->rejected($inventoryBalance, 'transfer_operation_key', $exception, 'conflict');
        } catch (InsufficientSourceQuantityException|InvalidArgumentException $exception) {
            return $this->rejected($inventoryBalance, 'transfer', $exception, 'domain_rejection');
        }

        return redirect()
            ->route('inventory.balances.show', $inventoryBalance)
            ->with('status', 'Available inventory transferred.')
            ->with('operation_result', [
                'type' => 'transfer',
                'operation_id' => $result->operationId,
                'movement_id' => $result->movementId,
                'transferred_quantity' => $result->transferredQuantity,
                'source_available_quantity' => $result->sourceAvailableQuantity,
                'destination_available_quantity' => $result->destinationAvailableQuantity,
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
