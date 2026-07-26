<?php

namespace App\Http\Controllers;

use App\Exceptions\IdempotencyConflictException;
use App\Http\Requests\Inventory\ReceiveStockRequest;
use App\Models\Product;
use App\Models\Warehouse;
use App\Services\Inventory\InventoryService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class InventoryReceiptController extends Controller
{
    public function create(): View
    {
        return view('inventory.receipts.create', [
            'operationKey' => (string) Str::uuid(),
            'products' => Product::query()
                ->where('is_active', true)
                ->orderBy('sku')
                ->get(['id', 'sku', 'name']),
            'warehouses' => Warehouse::query()
                ->where('is_active', true)
                ->orderBy('code')
                ->get(['id', 'code', 'name']),
        ]);
    }

    public function store(
        ReceiveStockRequest $request,
        InventoryService $inventory,
    ): RedirectResponse {
        try {
            $result = $inventory->receive($request->toInput());
        } catch (IdempotencyConflictException $exception) {
            return redirect()
                ->route('inventory.receipts.create')
                ->withInput()
                ->withErrors(['operation_key' => $exception->getMessage()])
                ->with('message_type', 'conflict');
        } catch (InvalidArgumentException $exception) {
            return redirect()
                ->route('inventory.receipts.create')
                ->withInput()
                ->withErrors(['domain' => $exception->getMessage()])
                ->with('message_type', 'domain_rejection');
        }

        return redirect()
            ->route('inventory.receipts.create')
            ->with('status', 'Stock receipt recorded.')
            ->with('operation_result', [
                'operation_id' => $result->operationId,
                'movement_id' => $result->movementId,
                'received_quantity' => $result->receivedQuantity,
                'available_quantity' => $result->availableQuantity,
            ]);
    }
}
