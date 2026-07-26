<?php

namespace App\DTOs\Catalog;

final readonly class SaveProductInput
{
    public function __construct(
        public string $sku,
        public string $name,
        public bool $isActive,
    ) {}
}
