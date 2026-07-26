<?php

use App\DTOs\Inventory\ReceiveStockInput;
use App\DTOs\Reservations\ReserveOrderItemInput;
use App\Jobs\AllocateBackorderJob;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\Operation;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Reservation;
use App\Models\ReservationTransition;
use App\Models\Warehouse;
use App\Services\Inventory\InventoryService;
use App\Services\Reservations\ReservationService;
use Illuminate\Support\Facades\Queue;

it('recovers FIFO backorders through both receipt jobs and the safety-net command', function () {
    $product = Product::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $balance = InventoryBalance::factory()
        ->for($product)
        ->for($warehouse)
        ->create(['available_quantity' => 6]);
    $oldestItem = OrderItem::factory()
        ->for($product)
        ->outstanding(10)
        ->create();
    $newerItem = OrderItem::factory()
        ->for($product)
        ->outstanding(4)
        ->create();
    $reservations = app(ReservationService::class);
    $oldestReservationResult = $reservations->reserve(new ReserveOrderItemInput(
        orderItemId: $oldestItem->id,
        warehouseId: $warehouse->id,
        idempotencyKey: 'backorder-initial-oldest',
        source: 'test',
    ));
    $newerReservationResult = $reservations->reserve(new ReserveOrderItemInput(
        orderItemId: $newerItem->id,
        warehouseId: $warehouse->id,
        idempotencyKey: 'backorder-initial-newer',
        source: 'test',
    ));
    Queue::fake();

    $firstReceipt = app(InventoryService::class)->receive(new ReceiveStockInput(
        productId: $product->id,
        warehouseId: $warehouse->id,
        quantity: 4,
        sourceReference: 'backorder-receipt-001',
        idempotencyKey: 'backorder-receipt-operation-001',
    ));

    Queue::assertPushed(
        AllocateBackorderJob::class,
        fn (AllocateBackorderJob $job): bool => $job->warehouseId === $warehouse->id
            && $job->runKey === 'stock-receipt-'.$firstReceipt->operationId,
    );

    $job = new AllocateBackorderJob(
        warehouseId: $warehouse->id,
        runKey: 'stock-receipt-'.$firstReceipt->operationId,
    );
    $job->handle($reservations);
    $job->handle($reservations);

    $oldestReservation = Reservation::query()->findOrFail(
        $oldestReservationResult->reservationId
    );
    $newerReservation = Reservation::query()->findOrFail(
        $newerReservationResult->reservationId
    );

    expect($oldestReservation->reserved_quantity)->toBe(10)
        ->and($oldestItem->refresh()->reserved_quantity)->toBe(10)
        ->and($newerReservation->reserved_quantity)->toBe(0)
        ->and($newerItem->refresh()->reserved_quantity)->toBe(0)
        ->and($balance->refresh()->available_quantity)->toBe(0)
        ->and($balance->reserved_quantity)->toBe(10);

    app(InventoryService::class)->receive(new ReceiveStockInput(
        productId: $product->id,
        warehouseId: $warehouse->id,
        quantity: 4,
        sourceReference: 'backorder-receipt-002',
        idempotencyKey: 'backorder-receipt-operation-002',
    ));

    $this->artisan('inventory:allocate-backorders', [
        '--run-key' => 'backorder-safety-net-001',
        '--batch' => 50,
    ])->assertSuccessful()
        ->expectsOutputToContain('Allocated 4 backordered units.');

    expect($newerReservation->refresh()->reserved_quantity)->toBe(4)
        ->and($newerItem->refresh()->reserved_quantity)->toBe(4)
        ->and($balance->refresh()->available_quantity)->toBe(0)
        ->and($balance->reserved_quantity)->toBe(14)
        ->and(InventoryMovement::query()->count())->toBe(5)
        ->and(ReservationTransition::query()->count())->toBe(3)
        ->and(Reservation::query()->count())->toBe(2)
        ->and(Operation::query()->count())->toBe(7);
});
