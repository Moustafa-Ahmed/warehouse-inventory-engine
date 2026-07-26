<?php

namespace Database\Seeders;

use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class WarehouseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $warehouses = [
            ['code' => 'CAI', 'name' => 'Cairo Warehouse'],
            ['code' => 'ALX', 'name' => 'Alexandria Warehouse'],
        ];

        foreach ($warehouses as $warehouse) {
            Warehouse::query()->firstOrCreate(
                ['code' => $warehouse['code']],
                [
                    'name' => $warehouse['name'],
                    'is_active' => true,
                ],
            );
        }
    }
}
