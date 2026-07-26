<?php

namespace App\Http\Controllers;

use App\Enums\Inventory\MovementBucket;
use App\Enums\Reservations\Kind;
use App\Enums\Reservations\Status;
use App\Http\Requests\Reports\OperationalReportFilterRequest;
use App\Models\Product;
use App\Models\Warehouse;
use App\Services\Inventory\InventoryReportService;
use App\Services\Orders\OrderReportService;
use App\Services\Reservations\ReservationReportService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;

final class OperationalReportController extends Controller
{
    public function inventory(
        OperationalReportFilterRequest $request,
        InventoryReportService $reports,
    ): View {
        return view('reports.inventory', [
            ...$this->filterOptions(),
            'rows' => $reports->inventory(
                productId: $this->nullableInt($request, 'product_id'),
                warehouseId: $this->nullableInt($request, 'warehouse_id'),
            ),
        ]);
    }

    public function reservations(
        OperationalReportFilterRequest $request,
        ReservationReportService $reports,
    ): View {
        return view('reports.reservations', [
            ...$this->filterOptions(),
            'rows' => $reports->reservations(
                status: Status::from($request->validated('status', Status::Open->value)),
                kind: $request->filled('kind')
                    ? Kind::from($request->validated('kind'))
                    : null,
                productId: $this->nullableInt($request, 'product_id'),
                warehouseId: $this->nullableInt($request, 'warehouse_id'),
                orderNumber: $request->validated('order_number'),
                minimumAgeDays: $this->nullableInt($request, 'minimum_age_days'),
                expiresAfter: $request->validated('expires_after'),
                expiresBefore: $request->validated('expires_before'),
            ),
            'statuses' => Status::cases(),
            'kinds' => Kind::cases(),
        ]);
    }

    public function consumedOrders(
        OperationalReportFilterRequest $request,
        OrderReportService $reports,
    ): View {
        return view('reports.consumed-orders', [
            ...$this->filterOptions(),
            'rows' => $reports->consumedInventory(
                productId: $this->nullableInt($request, 'product_id'),
                warehouseId: $this->nullableInt($request, 'warehouse_id'),
                orderNumber: $request->validated('order_number'),
            ),
        ]);
    }

    public function movements(
        OperationalReportFilterRequest $request,
        InventoryReportService $reports,
    ): View {
        return view('reports.movements', [
            ...$this->filterOptions(),
            'rows' => $reports->movements(
                productId: $this->nullableInt($request, 'product_id'),
                warehouseId: $this->nullableInt($request, 'warehouse_id'),
                bucket: $request->filled('bucket')
                    ? MovementBucket::from($request->validated('bucket'))
                    : null,
                referenceType: $request->validated('reference_type'),
                dateFrom: $request->validated('date_from'),
                dateTo: $request->validated('date_to'),
            ),
            'buckets' => MovementBucket::cases(),
        ]);
    }

    /** @return array{products: Collection, warehouses: Collection} */
    private function filterOptions(): array
    {
        return [
            'products' => Product::query()
                ->orderBy('sku')
                ->get(['id', 'sku', 'name']),
            'warehouses' => Warehouse::query()
                ->orderBy('code')
                ->get(['id', 'code', 'name']),
        ];
    }

    private function nullableInt(
        OperationalReportFilterRequest $request,
        string $key,
    ): ?int {
        return $request->filled($key)
            ? (int) $request->validated($key)
            : null;
    }
}
