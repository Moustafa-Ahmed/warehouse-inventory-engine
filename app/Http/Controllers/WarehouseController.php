<?php

namespace App\Http\Controllers;

use App\Http\Requests\Catalog\SaveWarehouseRequest;
use App\Models\Warehouse;
use App\Services\Catalog\WarehouseCatalogService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

final class WarehouseController extends Controller
{
    public function index(): View
    {
        return view('catalog.warehouses.index', [
            'warehouses' => Warehouse::query()
                ->orderByDesc('is_active')
                ->orderBy('code')
                ->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('catalog.warehouses.form', ['warehouse' => null]);
    }

    public function store(
        SaveWarehouseRequest $request,
        WarehouseCatalogService $warehouses,
    ): RedirectResponse {
        $warehouse = $warehouses->create($request->toInput());

        return redirect()
            ->route('warehouses.edit', $warehouse)
            ->with('status', 'Warehouse created.');
    }

    public function edit(Warehouse $warehouse): View
    {
        return view('catalog.warehouses.form', ['warehouse' => $warehouse]);
    }

    public function update(
        SaveWarehouseRequest $request,
        Warehouse $warehouse,
        WarehouseCatalogService $warehouses,
    ): RedirectResponse {
        $warehouses->update($warehouse, $request->toInput());

        return redirect()
            ->route('warehouses.edit', $warehouse)
            ->with('status', 'Warehouse updated.');
    }
}
