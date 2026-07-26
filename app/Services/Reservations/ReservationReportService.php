<?php

namespace App\Services\Reservations;

use App\Enums\Reservations\Kind;
use App\Enums\Reservations\Status;
use App\Models\Reservation;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ReservationReportService
{
    public function partialAllocations(int $perPage = 10): LengthAwarePaginator
    {
        return Reservation::query()
            ->with([
                'orderItem.order:id,order_number',
                'orderItem.product:id,sku,name',
                'warehouse:id,code,name',
            ])
            ->where('status', Status::Open->value)
            ->whereRaw(
                'reserved_quantity + picked_quantity + packed_quantity + shipped_quantity > 0'
            )
            ->whereRaw(
                'requested_quantity > reserved_quantity + picked_quantity + packed_quantity + shipped_quantity + released_quantity'
            )
            ->select('reservations.*')
            ->selectRaw(
                'requested_quantity - reserved_quantity - picked_quantity - packed_quantity - shipped_quantity - released_quantity as outstanding_quantity'
            )
            ->orderBy('created_at')
            ->orderBy('id')
            ->paginate($perPage);
    }

    public function expiringReservations(
        CarbonImmutable $cutoff,
        int $perPage = 10,
    ): LengthAwarePaginator {
        return Reservation::query()
            ->with([
                'orderItem.order:id,order_number',
                'orderItem.product:id,sku,name',
                'warehouse:id,code,name',
            ])
            ->where('status', Status::Open->value)
            ->where('kind', Kind::Temporary->value)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $cutoff)
            ->orderBy('expires_at')
            ->orderBy('id')
            ->paginate($perPage);
    }

    public function reservations(
        Status $status = Status::Open,
        ?Kind $kind = null,
        ?int $productId = null,
        ?int $warehouseId = null,
        ?string $orderNumber = null,
        ?int $minimumAgeDays = null,
        ?string $expiresAfter = null,
        ?string $expiresBefore = null,
        int $perPage = 30,
    ): LengthAwarePaginator {
        return Reservation::query()
            ->with([
                'orderItem.order:id,order_number',
                'orderItem.product:id,sku,name',
                'warehouse:id,code,name',
            ])
            ->where('status', $status->value)
            ->when($kind, fn ($query, Kind $reservationKind) => $query->where('kind', $reservationKind->value))
            ->when(
                $productId,
                fn ($query, int $id) => $query->whereHas(
                    'orderItem',
                    fn ($itemQuery) => $itemQuery->where('product_id', $id),
                ),
            )
            ->when($warehouseId, fn ($query, int $id) => $query->where('warehouse_id', $id))
            ->when(
                $orderNumber,
                fn ($query, string $number) => $query->whereHas(
                    'orderItem.order',
                    fn ($orderQuery) => $orderQuery->where('order_number', 'like', "%{$number}%"),
                ),
            )
            ->when(
                $minimumAgeDays !== null,
                fn ($query) => $query->where(
                    'created_at',
                    '<=',
                    now()->subDays($minimumAgeDays),
                ),
            )
            ->when(
                $expiresAfter,
                fn ($query, string $date) => $query->where(
                    'expires_at',
                    '>=',
                    CarbonImmutable::parse($date)->startOfDay(),
                ),
            )
            ->when(
                $expiresBefore,
                fn ($query, string $date) => $query->where(
                    'expires_at',
                    '<=',
                    CarbonImmutable::parse($date)->endOfDay(),
                ),
            )
            ->orderBy('created_at')
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();
    }
}
