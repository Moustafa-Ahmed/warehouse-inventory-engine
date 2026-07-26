<?php

use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Warehouse;

it('seeds reference catalogs with zero-valued inventory balances', function () {
    $movementCount = InventoryMovement::query()->count();

    $this->seed();

    $products = Product::query()
        ->whereIn('sku', ['LAPTOP-15', 'MONITOR-27', 'SCANNER-HH'])
        ->orderBy('sku')
        ->get();
    $warehouses = Warehouse::query()
        ->whereIn('code', ['ALX', 'CAI'])
        ->orderBy('code')
        ->get();
    $seededBalances = InventoryBalance::query()
        ->whereIn('product_id', $products->modelKeys())
        ->whereIn('warehouse_id', $warehouses->modelKeys());

    expect($products->pluck('sku')->all())
        ->toBe([
            'LAPTOP-15',
            'MONITOR-27',
            'SCANNER-HH',
        ])
        ->and($warehouses->pluck('code')->all())
        ->toBe([
            'ALX',
            'CAI',
        ])
        ->and((clone $seededBalances)->count())
        ->toBe(6)
        ->and((clone $seededBalances)
            ->where(function ($query) {
                $query->where('available_quantity', '!=', 0)
                    ->orWhere('reserved_quantity', '!=', 0)
                    ->orWhere('picked_quantity', '!=', 0)
                    ->orWhere('packed_quantity', '!=', 0);
            })
            ->doesntExist())
        ->toBeTrue()
        ->and(InventoryMovement::query()->count())
        ->toBe($movementCount);
});
