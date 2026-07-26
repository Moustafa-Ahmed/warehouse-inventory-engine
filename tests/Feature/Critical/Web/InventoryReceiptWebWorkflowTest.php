<?php

use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\Operation;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Str;

it('authorizes, redirects, replays, and conflicts through the receipt form', function () {
    config()->set('administrator.email', 'administrator@example.test');
    $administrator = User::factory()->create([
        'email' => 'administrator@example.test',
    ]);
    $product = Product::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $operationKey = (string) Str::uuid();
    $payload = [
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 5,
        'source_reference' => 'browser-receipt-001',
        'operation_key' => $operationKey,
    ];

    $this->actingAs($administrator)
        ->get(route('inventory.receipts.create'))
        ->assertSuccessful()
        ->assertSee('name="operation_key"', false)
        ->assertSee($product->sku)
        ->assertSee($warehouse->code);

    $firstResponse = $this->actingAs($administrator)
        ->post(route('inventory.receipts.store'), $payload)
        ->assertRedirect(route('inventory.receipts.create'))
        ->assertSessionHas('status', 'Stock receipt recorded.')
        ->assertSessionHas('operation_result.received_quantity', 5)
        ->assertSessionHas('operation_result.available_quantity', 5);
    $operationId = $firstResponse->getSession()->get('operation_result')['operation_id'];

    $this->get(route('inventory.receipts.create'))
        ->assertSuccessful()
        ->assertSee('Receipt result')
        ->assertSee('Stock receipt recorded.');

    $replayResponse = $this->post(route('inventory.receipts.store'), $payload)
        ->assertRedirect(route('inventory.receipts.create'))
        ->assertSessionHas('operation_result.operation_id', $operationId)
        ->assertSessionHas('operation_result.available_quantity', 5);

    expect($replayResponse->getSession()->get('operation_result')['operation_id'])
        ->toBe($operationId)
        ->and(InventoryBalance::query()->sole()->available_quantity)->toBe(5)
        ->and(Operation::query()->count())->toBe(1)
        ->and(InventoryMovement::query()->count())->toBe(1);

    $this->post(route('inventory.receipts.store'), [
        ...$payload,
        'quantity' => 6,
    ])->assertRedirect(route('inventory.receipts.create'))
        ->assertSessionHas('message_type', 'conflict')
        ->assertSessionHasErrors('operation_key');

    expect(InventoryBalance::query()->sole()->available_quantity)->toBe(5)
        ->and(Operation::query()->count())->toBe(1)
        ->and(InventoryMovement::query()->count())->toBe(1);

    $otherUser = User::factory()->create();

    $this->actingAs($otherUser)
        ->post(route('inventory.receipts.store'), [
            ...$payload,
            'operation_key' => (string) Str::uuid(),
        ])
        ->assertForbidden();
});
