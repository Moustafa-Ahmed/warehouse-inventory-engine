<?php

namespace App\DTOs\Catalog;

final readonly class SaveWarehouseInput
{
    public function __construct(
        public string $code,
        public string $name,
        public bool $isActive,
    ) {}
}
