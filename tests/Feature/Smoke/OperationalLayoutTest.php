<?php

use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;

it('renders the authenticated operational shell without requiring JavaScript', function () {
    config()->set('administrator.email', 'layout-administrator@example.test');

    Product::factory()->create();
    Warehouse::factory()->create();

    $administrator = User::factory()->create([
        'email' => 'layout-administrator@example.test',
    ]);

    $this->actingAs($administrator)
        ->get(route('operations.home'))
        ->assertSuccessful()
        ->assertSee('Warehouse Engine')
        ->assertSee('Authenticated operational interface')
        ->assertSee('bootstrap@5.3.8')
        ->assertSee('jquery-3.7.1.min.js')
        ->assertSee('data-confirm=', escape: false);

    $this->get(route('inventory.receipts.create'))
        ->assertSuccessful()
        ->assertSee('Receive stock')
        ->assertSee('Record receipt')
        ->assertSee('name="operation_key"', escape: false);
});

it('renders the guest login shell', function () {
    $this->get(route('login'))
        ->assertSuccessful()
        ->assertSee('Administrator login')
        ->assertSee('bootstrap@5.3.8')
        ->assertSee('name="email"', escape: false)
        ->assertSee('name="password"', escape: false);
});
