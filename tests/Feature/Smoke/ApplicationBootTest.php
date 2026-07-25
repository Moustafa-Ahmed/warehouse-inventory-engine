<?php

use App\Contracts\ShippingProvider;
use App\Services\Shipping\InMemoryProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('boots against the isolated MySQL test database', function () {
    expect(config('database.default'))->toBe('mysql')
        ->and(DB::connection()->getDriverName())->toBe('mysql')
        ->and(Schema::hasTable('users'))->toBeTrue()
        ->and(Schema::hasTable('products'))->toBeTrue()
        ->and(Schema::hasTable('warehouses'))->toBeTrue()
        ->and(Schema::hasTable('operations'))->toBeTrue()
        ->and(Schema::hasTable('inventory_balances'))->toBeTrue()
        ->and(Schema::hasTable('inventory_movements'))->toBeTrue()
        ->and(Schema::hasTable('orders'))->toBeTrue()
        ->and(Schema::hasTable('order_items'))->toBeTrue()
        ->and($this->app->make(ShippingProvider::class))->toBeInstanceOf(InMemoryProvider::class);

    $this->get('/')->assertSuccessful();
});
