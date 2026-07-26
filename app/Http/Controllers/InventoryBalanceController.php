<?php

namespace App\Http\Controllers;

use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\Warehouse;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;

final class InventoryBalanceController extends Controller
{
    public function index(): View
    {
        return view('inventory.balances.index', [
            'balances' => InventoryBalance::query()
                ->with([
                    'product:id,sku,name',
                    'warehouse:id,code,name',
                ])
                ->orderBy('id')
                ->paginate(20),
        ]);
    }

    public function show(InventoryBalance $inventoryBalance): View
    {
        $inventoryBalance->load([
            'product:id,sku,name',
            'warehouse:id,code,name',
        ]);

        return view('inventory.balances.show', [
            'balance' => $inventoryBalance,
            'recentMovements' => InventoryMovement::query()
                ->with([
                    'actor:id,name',
                    'sourceWarehouse:id,code',
                    'destinationWarehouse:id,code',
                ])
                ->where('product_id', $inventoryBalance->product_id)
                ->where(function ($query) use ($inventoryBalance): void {
                    $query
                        ->where('source_warehouse_id', $inventoryBalance->warehouse_id)
                        ->orWhere('destination_warehouse_id', $inventoryBalance->warehouse_id);
                })
                ->latest('id')
                ->limit(20)
                ->get(),
            'destinationWarehouses' => Warehouse::query()
                ->where('is_active', true)
                ->whereKeyNot($inventoryBalance->warehouse_id)
                ->orderBy('code')
                ->get(['id', 'code', 'name']),
            'adjustmentOperationKey' => (string) Str::uuid(),
            'transferOperationKey' => (string) Str::uuid(),
        ]);
    }
}
