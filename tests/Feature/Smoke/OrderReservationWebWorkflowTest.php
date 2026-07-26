<?php

use App\DTOs\Inventory\ReceiveStockInput;
use App\Models\InventoryBalance;
use App\Models\Order;
use App\Models\Product;
use App\Models\Reservation;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Inventory\InventoryService;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

it('shows explicit partial allocation and exposes the valid reservation workflows', function () {
    Queue::fake();
    config()->set('administrator.email', 'order-ui-administrator@example.test');

    $administrator = User::factory()->create([
        'email' => 'order-ui-administrator@example.test',
    ]);
    $product = Product::factory()->create();
    $warehouse = Warehouse::factory()->create();

    app(InventoryService::class)->receive(new ReceiveStockInput(
        productId: $product->id,
        warehouseId: $warehouse->id,
        quantity: 6,
        sourceReference: 'order-ui-stock',
        idempotencyKey: (string) Str::uuid(),
        actorId: $administrator->id,
    ));

    $this->actingAs($administrator)
        ->get(route('orders.index'))
        ->assertSuccessful()
        ->assertSee('Create order');

    $this->post(route('orders.store'), [
        'order_number' => 'WEB-ORDER-001',
        'items' => [[
            'product_id' => $product->id,
            'ordered_quantity' => 10,
        ]],
        'order_operation_key' => (string) Str::uuid(),
    ])->assertRedirect()
        ->assertSessionHas('status', 'Order created.');

    $order = Order::query()->with('items')->sole();
    $orderItem = $order->items->sole();

    $this->get(route('orders.show', $order))
        ->assertSuccessful()
        ->assertSee('Allocation quantities')
        ->assertSee('Fulfillment quantities')
        ->assertSee('Delivery quantities')
        ->assertSee('10 outstanding');

    $this->post(route('orders.items.reservations.store', [$order, $orderItem]), [
        'warehouse_id' => $warehouse->id,
        'kind' => 'temporary',
        'expires_at' => now()->addHour()->format('Y-m-d\TH:i'),
        'reservation_operation_key' => (string) Str::uuid(),
    ])->assertRedirect()
        ->assertSessionHas('status', 'Reservation attempt completed.')
        ->assertSessionHas('allocation_result.requested_quantity', 10)
        ->assertSessionHas('allocation_result.allocated_quantity', 6)
        ->assertSessionHas('allocation_result.outstanding_quantity', 4)
        ->assertSessionHas('allocation_result.fully_allocated', false);

    $reservation = Reservation::query()->sole();

    $this->get(route('reservations.show', $reservation))
        ->assertSuccessful()
        ->assertSee('This is a partial reservation')
        ->assertSee('Confirm temporary reservation')
        ->assertSee('Allocate available stock now')
        ->assertSee('Release reserved stock')
        ->assertSee('Transition timeline');

    $this->post(route('reservations.confirm', $reservation), [
        'confirmation_operation_key' => (string) Str::uuid(),
    ])->assertRedirect(route('reservations.show', $reservation))
        ->assertSessionHas('status', 'Temporary reservation confirmed.');

    $this->post(route('reservations.release', $reservation), [
        'quantity' => 1,
        'cancel_order_demand' => '0',
        'reason' => 'Demonstrate release without cancelling demand.',
        'release_operation_key' => (string) Str::uuid(),
    ])->assertRedirect(route('reservations.show', $reservation))
        ->assertSessionHas('status', 'Reserved inventory released.')
        ->assertSessionHas('operation_result.outstanding_quantity', 5);

    $this->post(route('reservations.allocate', $reservation), [
        'allocation_run_key' => (string) Str::uuid(),
    ])->assertRedirect(route('reservations.show', $reservation))
        ->assertSessionHas('status', 'Warehouse FIFO allocation run completed.')
        ->assertSessionHas('allocation_result.allocated_quantity', 1)
        ->assertSessionHas('allocation_result.outstanding_quantity', 4);

    $this->patch(route('orders.items.update', [$order, $orderItem]), [
        'quantity_change' => 2,
        'reason' => 'Customer requested two more units.',
        'edit_operation_key' => (string) Str::uuid(),
    ])->assertRedirect(route('orders.show', $order))
        ->assertSessionHas('status', 'Order item quantity updated.')
        ->assertSessionHas('operation_result.ordered_quantity', 12)
        ->assertSessionHas('operation_result.outstanding_quantity', 6);

    expect($orderItem->fresh()->ordered_quantity)->toBe(12)
        ->and($orderItem->fresh()->reserved_quantity)->toBe(6)
        ->and(
            InventoryBalance::query()
                ->whereBelongsTo($product)
                ->whereBelongsTo($warehouse)
                ->valueOrFail('available_quantity')
        )->toBe(0);
});
