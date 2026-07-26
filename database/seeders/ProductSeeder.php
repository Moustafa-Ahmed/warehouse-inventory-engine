<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $products = [
            ['sku' => 'LAPTOP-15', 'name' => '15-inch Laptop'],
            ['sku' => 'MONITOR-27', 'name' => '27-inch Monitor'],
            ['sku' => 'SCANNER-HH', 'name' => 'Handheld Barcode Scanner'],
        ];

        foreach ($products as $product) {
            Product::query()->firstOrCreate(
                ['sku' => $product['sku']],
                [
                    'name' => $product['name'],
                    'is_active' => true,
                ],
            );
        }
    }
}
