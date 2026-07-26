<?php

namespace Database\Seeders;

use App\Models\InventoryBalance;
use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class InventoryBalanceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $products = Product::query()->get(['id']);
        $warehouses = Warehouse::query()->get(['id']);

        foreach ($products as $product) {
            foreach ($warehouses as $warehouse) {
                InventoryBalance::query()->firstOrCreate([
                    'product_id' => $product->id,
                    'warehouse_id' => $warehouse->id,
                ]);
            }
        }
    }
}
