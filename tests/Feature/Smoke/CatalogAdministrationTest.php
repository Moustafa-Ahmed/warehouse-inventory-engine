<?php

use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;

it('lets the administrator create edit and deactivate catalog records', function () {
    config()->set('administrator.email', 'catalog-administrator@example.test');
    $administrator = User::factory()->create([
        'email' => 'catalog-administrator@example.test',
    ]);

    $this->get(route('products.index'))->assertRedirect(route('login'));

    $this->actingAs($administrator)
        ->get(route('products.index'))
        ->assertSuccessful()
        ->assertSee('Product catalog')
        ->assertSee('Add product');

    $this->post(route('products.store'), [
        'sku' => 'CATALOG-001',
        'name' => 'Catalog test product',
        'is_active' => '1',
    ])->assertRedirect()
        ->assertSessionHas('status', 'Product created.');

    $product = Product::query()->where('sku', 'CATALOG-001')->sole();

    $this->get(route('products.edit', $product))
        ->assertSuccessful()
        ->assertSee('Edit product')
        ->assertSee('CATALOG-001');

    $this->patch(route('products.update', $product), [
        'sku' => 'CATALOG-001',
        'name' => 'Updated catalog product',
    ])->assertRedirect(route('products.edit', $product))
        ->assertSessionHas('status', 'Product updated.');

    expect($product->refresh()->name)->toBe('Updated catalog product')
        ->and($product->is_active)->toBeFalse();

    $this->get(route('warehouses.index'))
        ->assertSuccessful()
        ->assertSee('Warehouse catalog')
        ->assertSee('Add warehouse');

    $this->post(route('warehouses.store'), [
        'code' => 'CAT-WH',
        'name' => 'Catalog test warehouse',
        'is_active' => '1',
    ])->assertRedirect()
        ->assertSessionHas('status', 'Warehouse created.');

    $warehouse = Warehouse::query()->where('code', 'CAT-WH')->sole();

    $this->patch(route('warehouses.update', $warehouse), [
        'code' => 'CAT-WH',
        'name' => 'Updated catalog warehouse',
    ])->assertRedirect(route('warehouses.edit', $warehouse))
        ->assertSessionHas('status', 'Warehouse updated.');

    expect($warehouse->refresh()->name)->toBe('Updated catalog warehouse')
        ->and($warehouse->is_active)->toBeFalse();

    $this->get(route('products.index'))
        ->assertSuccessful()
        ->assertSee('Inactive');
    $this->get(route('warehouses.index'))
        ->assertSuccessful()
        ->assertSee('Inactive');
});

it('authorizes catalog forms and validates unique identifiers', function () {
    config()->set('administrator.email', 'catalog-owner@example.test');
    $administrator = User::factory()->create([
        'email' => 'catalog-owner@example.test',
    ]);
    $otherUser = User::factory()->create();
    Product::factory()->create(['sku' => 'EXISTING-SKU']);
    Warehouse::factory()->create(['code' => 'EXISTING-WH']);

    $this->actingAs($otherUser)
        ->get(route('products.create'))
        ->assertForbidden();

    $this->actingAs($administrator)
        ->post(route('products.store'), [
            'sku' => 'EXISTING-SKU',
            'name' => 'Duplicate product',
            'is_active' => '1',
        ])->assertSessionHasErrors('sku');

    $this->post(route('warehouses.store'), [
        'code' => 'EXISTING-WH',
        'name' => 'Duplicate warehouse',
        'is_active' => '1',
    ])->assertSessionHasErrors('code');
});
