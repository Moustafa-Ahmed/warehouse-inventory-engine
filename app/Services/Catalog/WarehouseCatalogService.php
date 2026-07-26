<?php

namespace App\Services\Catalog;

use App\DTOs\Catalog\SaveWarehouseInput;
use App\Models\Warehouse;

final class WarehouseCatalogService
{
    public function create(SaveWarehouseInput $input): Warehouse
    {
        return Warehouse::query()->create($this->attributes($input));
    }

    public function update(
        Warehouse $warehouse,
        SaveWarehouseInput $input,
    ): Warehouse {
        $warehouse->update($this->attributes($input));

        return $warehouse->refresh();
    }

    /**
     * @return array{code: string, name: string, is_active: bool}
     */
    private function attributes(SaveWarehouseInput $input): array
    {
        return [
            'code' => trim($input->code),
            'name' => trim($input->name),
            'is_active' => $input->isActive,
        ];
    }
}
