<?php

namespace Tests\Support;

use App\DTOs\Reservations\ReserveOrderItemInput;
use App\Services\Reservations\ReservationService;
use Closure;

final class ConcurrentReservationAttempt
{
    /**
     * @return Closure(): array{
     *     operation_id: int,
     *     reservation_id: int,
     *     allocated_quantity: int,
     *     outstanding_quantity: int
     * }
     */
    public static function make(
        int $orderItemId,
        int $warehouseId,
        string $idempotencyKey,
        int $actorId,
    ): Closure {
        return static function () use (
            $orderItemId,
            $warehouseId,
            $idempotencyKey,
            $actorId,
        ): array {
            usleep(100_000);

            $result = app(ReservationService::class)->reserve(new ReserveOrderItemInput(
                orderItemId: $orderItemId,
                warehouseId: $warehouseId,
                idempotencyKey: $idempotencyKey,
                actorId: $actorId,
                source: 'concurrency_test',
            ));

            return [
                'operation_id' => $result->operationId,
                'reservation_id' => $result->reservationId,
                'allocated_quantity' => $result->allocatedQuantity,
                'outstanding_quantity' => $result->outstandingQuantity,
            ];
        };
    }
}
