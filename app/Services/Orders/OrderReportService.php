<?php

namespace App\Services\Orders;

use App\Enums\Inventory\MovementBucket;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class OrderReportService
{
    public function consumedInventory(
        ?int $productId = null,
        ?int $warehouseId = null,
        ?string $orderNumber = null,
        int $perPage = 30,
    ): LengthAwarePaginator {
        return DB::table('inventory_movements')
            ->join(
                'shipments',
                'shipments.id',
                '=',
                'inventory_movements.business_reference_id',
            )
            ->join('orders', 'orders.id', '=', 'shipments.order_id')
            ->where('inventory_movements.business_reference_type', 'shipment_handoff')
            ->where('inventory_movements.source_bucket', MovementBucket::Packed->value)
            ->where('inventory_movements.destination_bucket', MovementBucket::Shipped->value)
            ->whereNotNull('inventory_movements.source_warehouse_id')
            ->whereNull('inventory_movements.destination_warehouse_id')
            ->when(
                $productId,
                fn ($query, int $id) => $query->where('inventory_movements.product_id', $id),
            )
            ->when(
                $warehouseId,
                fn ($query, int $id) => $query->where('inventory_movements.source_warehouse_id', $id),
            )
            ->when(
                $orderNumber,
                fn ($query, string $number) => $query->where('orders.order_number', 'like', "%{$number}%"),
            )
            ->groupBy('orders.id', 'orders.order_number')
            ->select([
                'orders.id as order_id',
                'orders.order_number',
            ])
            ->selectRaw('SUM(inventory_movements.quantity) as consumed_quantity')
            ->selectRaw('MIN(inventory_movements.created_at) as first_consumed_at')
            ->selectRaw('MAX(inventory_movements.created_at) as last_consumed_at')
            ->orderByDesc('last_consumed_at')
            ->orderByDesc('orders.id')
            ->paginate($perPage)
            ->withQueryString();
    }
}
