<?php

use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Warehouse;

it('seeds reference catalogs with zero-valued inventory balances', function () {
    $this->seed();

    expect(Product::query()->orderBy('sku')->pluck('sku')->all())
        ->toBe([
            'LAPTOP-15',
            'MONITOR-27',
            'SCANNER-HH',
        ])
        ->and(Warehouse::query()->orderBy('code')->pluck('code')->all())
        ->toBe([
            'ALX',
            'CAI',
        ])
        ->and(InventoryBalance::query()->count())
        ->toBe(Product::query()->count() * Warehouse::query()->count())
        ->and(InventoryBalance::query()
            ->where('available_quantity', '!=', 0)
            ->orWhere('reserved_quantity', '!=', 0)
            ->orWhere('picked_quantity', '!=', 0)
            ->orWhere('packed_quantity', '!=', 0)
            ->doesntExist())
        ->toBeTrue()
        ->and(InventoryMovement::query()->doesntExist())
        ->toBeTrue();
});
