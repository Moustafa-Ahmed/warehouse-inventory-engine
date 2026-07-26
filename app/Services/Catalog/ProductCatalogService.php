<?php

namespace App\Services\Catalog;

use App\DTOs\Catalog\SaveProductInput;
use App\Models\Product;

final class ProductCatalogService
{
    public function create(SaveProductInput $input): Product
    {
        return Product::query()->create($this->attributes($input));
    }

    public function update(Product $product, SaveProductInput $input): Product
    {
        $product->update($this->attributes($input));

        return $product->refresh();
    }

    /**
     * @return array{sku: string, name: string, is_active: bool}
     */
    private function attributes(SaveProductInput $input): array
    {
        return [
            'sku' => trim($input->sku),
            'name' => trim($input->name),
            'is_active' => $input->isActive,
        ];
    }
}
