<?php

namespace App\Services\Inventory;

use App\Enums\Inventory\MovementBucket;
use App\Models\InventoryMovement;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class InventoryReportService
{
    public function inventory(
        ?int $productId = null,
        ?int $warehouseId = null,
        int $perPage = 25,
    ): LengthAwarePaginator {
        $shippedQuantities = DB::table('inventory_movements')
            ->select([
                'product_id',
                'source_warehouse_id as warehouse_id',
            ])
            ->selectRaw('SUM(quantity) as shipped_quantity')
            ->where('business_reference_type', 'shipment_handoff')
            ->where('source_bucket', MovementBucket::Packed->value)
            ->where('destination_bucket', MovementBucket::Shipped->value)
            ->whereNotNull('source_warehouse_id')
            ->whereNull('destination_warehouse_id')
            ->groupBy('product_id', 'source_warehouse_id');

        return DB::table('inventory_balances')
            ->join('products', 'products.id', '=', 'inventory_balances.product_id')
            ->join('warehouses', 'warehouses.id', '=', 'inventory_balances.warehouse_id')
            ->leftJoinSub($shippedQuantities, 'shipped', function ($join): void {
                $join->on('shipped.product_id', '=', 'inventory_balances.product_id')
                    ->on('shipped.warehouse_id', '=', 'inventory_balances.warehouse_id');
            })
            ->select([
                'inventory_balances.id',
                'inventory_balances.product_id',
                'inventory_balances.warehouse_id',
                'products.sku',
                'products.name as product_name',
                'warehouses.code as warehouse_code',
                'warehouses.name as warehouse_name',
                'inventory_balances.available_quantity',
                'inventory_balances.reserved_quantity',
                'inventory_balances.picked_quantity',
                'inventory_balances.packed_quantity',
            ])
            ->selectRaw(
                'inventory_balances.available_quantity
                    + inventory_balances.reserved_quantity
                    + inventory_balances.picked_quantity
                    + inventory_balances.packed_quantity as on_hand_quantity'
            )
            ->selectRaw('COALESCE(shipped.shipped_quantity, 0) as shipped_quantity')
            ->when($productId, fn ($query, int $id) => $query->where('inventory_balances.product_id', $id))
            ->when($warehouseId, fn ($query, int $id) => $query->where('inventory_balances.warehouse_id', $id))
            ->orderBy('products.sku')
            ->orderBy('warehouses.code')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function movements(
        ?int $productId = null,
        ?int $warehouseId = null,
        ?MovementBucket $bucket = null,
        ?string $referenceType = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        int $perPage = 30,
    ): LengthAwarePaginator {
        return InventoryMovement::query()
            ->with([
                'product:id,sku,name',
                'sourceWarehouse:id,code',
                'destinationWarehouse:id,code',
                'actor:id,name',
            ])
            ->when($productId, fn ($query, int $id) => $query->where('product_id', $id))
            ->when($warehouseId, function ($query, int $id): void {
                $query->where(function ($endpointQuery) use ($id): void {
                    $endpointQuery
                        ->where('source_warehouse_id', $id)
                        ->orWhere('destination_warehouse_id', $id);
                });
            })
            ->when($bucket, function ($query, MovementBucket $movementBucket): void {
                $query->where(function ($bucketQuery) use ($movementBucket): void {
                    $bucketQuery
                        ->where('source_bucket', $movementBucket->value)
                        ->orWhere('destination_bucket', $movementBucket->value);
                });
            })
            ->when($referenceType, fn ($query, string $type) => $query->where('business_reference_type', $type))
            ->when(
                $dateFrom,
                fn ($query, string $date) => $query->where(
                    'created_at',
                    '>=',
                    CarbonImmutable::parse($date)->startOfDay(),
                ),
            )
            ->when(
                $dateTo,
                fn ($query, string $date) => $query->where(
                    'created_at',
                    '<=',
                    CarbonImmutable::parse($date)->endOfDay(),
                ),
            )
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }
}
