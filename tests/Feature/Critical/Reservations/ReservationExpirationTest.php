<?php

use App\DTOs\Reservations\ConfirmReservationInput;
use App\DTOs\Reservations\ReserveOrderItemInput;
use App\Enums\Reservations\Kind;
use App\Enums\Reservations\Status;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\Operation;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Reservation;
use App\Models\ReservationTransition;
use App\Models\Warehouse;
use App\Services\Reservations\ReservationService;

it('expires a temporary hold once while preserving a confirmed reservation', function () {
    $this->freezeSecond();

    $product = Product::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $balance = InventoryBalance::factory()
        ->for($product)
        ->for($warehouse)
        ->create(['available_quantity' => 6]);
    $expiringItem = OrderItem::factory()
        ->for($product)
        ->outstanding(3)
        ->create();
    $confirmingItem = OrderItem::factory()
        ->for($product)
        ->outstanding(3)
        ->create();
    $service = app(ReservationService::class);
    $expiringResult = $service->reserve(new ReserveOrderItemInput(
        orderItemId: $expiringItem->id,
        warehouseId: $warehouse->id,
        idempotencyKey: 'temporary-expiring-001',
        source: 'test',
        kind: Kind::Temporary,
        expiresAt: now()->addMinute()->toDateTimeImmutable(),
    ));
    $confirmingResult = $service->reserve(new ReserveOrderItemInput(
        orderItemId: $confirmingItem->id,
        warehouseId: $warehouse->id,
        idempotencyKey: 'temporary-confirming-001',
        source: 'test',
        kind: Kind::Temporary,
        expiresAt: now()->addHour()->toDateTimeImmutable(),
    ));
    $confirmation = $service->confirm(new ConfirmReservationInput(
        reservationId: $confirmingResult->reservationId,
        idempotencyKey: 'confirm-temporary-001',
        source: 'test',
    ));

    expect($confirmation->kind)->toBe(Kind::Confirmed);

    $this->travel(2)->minutes();

    $this->artisan('reservations:expire', ['--batch' => 50])
        ->assertSuccessful()
        ->expectsOutputToContain('Expired 1 temporary reservations.');
    $this->artisan('reservations:expire', ['--batch' => 50])
        ->assertSuccessful()
        ->expectsOutputToContain('Expired 0 temporary reservations.');

    $expiredReservation = Reservation::query()->findOrFail($expiringResult->reservationId);
    $confirmedReservation = Reservation::query()->findOrFail($confirmingResult->reservationId);

    expect($expiredReservation->status)->toBe(Status::Expired)
        ->and($expiredReservation->reserved_quantity)->toBe(0)
        ->and($expiredReservation->released_quantity)->toBe(3)
        ->and($expiringItem->refresh()->reserved_quantity)->toBe(0)
        ->and($confirmedReservation->kind)->toBe(Kind::Confirmed)
        ->and($confirmedReservation->status)->toBe(Status::Open)
        ->and($confirmedReservation->expires_at)->toBeNull()
        ->and($confirmedReservation->reserved_quantity)->toBe(3)
        ->and($confirmingItem->refresh()->reserved_quantity)->toBe(3)
        ->and($balance->refresh()->available_quantity)->toBe(3)
        ->and($balance->reserved_quantity)->toBe(3)
        ->and(InventoryMovement::query()->count())->toBe(3)
        ->and(ReservationTransition::query()->count())->toBe(5)
        ->and(Operation::query()->count())->toBe(5);
});
