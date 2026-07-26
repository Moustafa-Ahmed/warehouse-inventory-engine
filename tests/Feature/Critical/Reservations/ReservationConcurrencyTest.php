<?php

use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\Operation;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Reservation;
use App\Models\ReservationTransition;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Facades\Concurrency;
use Tests\Support\ConcurrentReservationAttempt;

/**
 * Child processes use independent MySQL connections so this test exercises the actual balance row lock.
 */
it('allows exactly one user to reserve the final available unit', function () {
    $product = Product::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $users = User::factory()->count(2)->create();
    $orderItems = OrderItem::factory()
        ->count(2)
        ->for($product)
        ->outstanding(1)
        ->create();
    $balance = InventoryBalance::factory()
        ->for($product)
        ->for($warehouse)
        ->create(['available_quantity' => 1]);

    $results = Concurrency::run([
        ConcurrentReservationAttempt::make(
            $orderItems[0]->id,
            $warehouse->id,
            'concurrent-final-unit-001',
            $users[0]->id,
        ),
        ConcurrentReservationAttempt::make(
            $orderItems[1]->id,
            $warehouse->id,
            'concurrent-final-unit-002',
            $users[1]->id,
        ),
    ]);
    $allocatedQuantities = array_column($results, 'allocated_quantity');
    sort($allocatedQuantities);

    expect($allocatedQuantities)->toBe([0, 1])
        ->and($balance->refresh()->available_quantity)->toBe(0)
        ->and($balance->reserved_quantity)->toBe(1)
        ->and((int) OrderItem::query()->sum('reserved_quantity'))->toBe(1)
        ->and(Reservation::query()->count())->toBe(2)
        ->and((int) Reservation::query()->sum('reserved_quantity'))->toBe(1)
        ->and(InventoryMovement::query()->count())->toBe(1)
        ->and(ReservationTransition::query()->count())->toBe(1)
        ->and(ReservationTransition::query()->sole()->actor_id)
        ->toBeIn($users->modelKeys())
        ->and(Operation::query()->count())->toBe(2);
});

it('executes concurrent duplicate reservation intent only once', function () {
    $product = Product::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $actor = User::factory()->create();
    $orderItem = OrderItem::factory()
        ->for($product)
        ->outstanding(1)
        ->create();
    $balance = InventoryBalance::factory()
        ->for($product)
        ->for($warehouse)
        ->create(['available_quantity' => 1]);
    $attempt = ConcurrentReservationAttempt::make(
        $orderItem->id,
        $warehouse->id,
        'concurrent-duplicate-reservation-001',
        $actor->id,
    );

    $results = Concurrency::run([$attempt, $attempt]);

    expect($results[1])->toBe($results[0])
        ->and($results[0]['allocated_quantity'])->toBe(1)
        ->and($balance->refresh()->available_quantity)->toBe(0)
        ->and($balance->reserved_quantity)->toBe(1)
        ->and($orderItem->refresh()->reserved_quantity)->toBe(1)
        ->and(Reservation::query()->count())->toBe(1)
        ->and(InventoryMovement::query()->count())->toBe(1)
        ->and(ReservationTransition::query()->count())->toBe(1)
        ->and(Operation::query()
            ->where('idempotency_key', 'concurrent-duplicate-reservation-001')
            ->count())->toBe(1);
});
