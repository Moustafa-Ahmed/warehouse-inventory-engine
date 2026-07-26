<?php

use App\DTOs\Inventory\ReceiveStockInput;
use App\Models\InventoryBalance;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Inventory\InventoryService;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

it('loads inventory pages and performs ordinary adjustment and transfer workflows', function () {
    Queue::fake();
    config()->set('administrator.email', 'inventory-ui-administrator@example.test');

    $administrator = User::factory()->create([
        'email' => 'inventory-ui-administrator@example.test',
    ]);
    $product = Product::factory()->create();
    $sourceWarehouse = Warehouse::factory()->create();
    $destinationWarehouse = Warehouse::factory()->create();

    app(InventoryService::class)->receive(new ReceiveStockInput(
        productId: $product->id,
        warehouseId: $sourceWarehouse->id,
        quantity: 10,
        sourceReference: 'inventory-ui-setup',
        idempotencyKey: (string) Str::uuid(),
        actorId: $administrator->id,
    ));

    $sourceBalance = InventoryBalance::query()
        ->whereBelongsTo($product)
        ->whereBelongsTo($sourceWarehouse)
        ->sole();

    $this->actingAs($administrator)
        ->get(route('inventory.balances.index'))
        ->assertSuccessful()
        ->assertSee($product->sku)
        ->assertSee($sourceWarehouse->code);

    $this->get(route('inventory.balances.show', $sourceBalance))
        ->assertSuccessful()
        ->assertSee('Adjust available inventory')
        ->assertSee('Transfer available inventory')
        ->assertSee('Recent movements');

    $this->post(route('inventory.adjustments.store', $sourceBalance), [
        'quantity_change' => -2,
        'reason' => 'Correct the physical count.',
        'adjustment_operation_key' => (string) Str::uuid(),
    ])->assertRedirect(route('inventory.balances.show', $sourceBalance))
        ->assertSessionHas('status', 'Inventory adjustment recorded.')
        ->assertSessionHas('operation_result.available_quantity', 8);

    $this->post(route('inventory.transfers.store', $sourceBalance), [
        'destination_warehouse_id' => $destinationWarehouse->id,
        'quantity' => 3,
        'transfer_operation_key' => (string) Str::uuid(),
    ])->assertRedirect(route('inventory.balances.show', $sourceBalance))
        ->assertSessionHas('status', 'Available inventory transferred.')
        ->assertSessionHas('operation_result.source_available_quantity', 5)
        ->assertSessionHas('operation_result.destination_available_quantity', 3);

    expect($sourceBalance->fresh()->available_quantity)->toBe(5)
        ->and(
            InventoryBalance::query()
                ->whereBelongsTo($product)
                ->whereBelongsTo($destinationWarehouse)
                ->valueOrFail('available_quantity')
        )->toBe(3);
});
