<x-layouts.app title="Inventory report">
    <header class="mb-3">
        <h1 class="h3 mb-1">Inventory report</h1>
        <p class="text-body-secondary mb-0">
            Current warehouse buckets and shipped totals derived from confirmed packed-to-external movements.
        </p>
    </header>
    <x-reports.navigation />

    <form method="get" action="{{ route('reports.inventory') }}" class="card border-0 shadow-sm mb-4">
        <div class="card-body row g-3 align-items-end">
            <div class="col-12 col-md-5">
                <label class="form-label" for="product_id">Product</label>
                <select class="form-select" id="product_id" name="product_id">
                    <option value="">All products</option>
                    @foreach ($products as $product)
                        <option value="{{ $product->id }}" @selected((string) request('product_id') === (string) $product->id)>
                            {{ $product->sku }} — {{ $product->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-5">
                <label class="form-label" for="warehouse_id">Warehouse</label>
                <select class="form-select" id="warehouse_id" name="warehouse_id">
                    <option value="">All warehouses</option>
                    @foreach ($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}" @selected((string) request('warehouse_id') === (string) $warehouse->id)>
                            {{ $warehouse->code }} — {{ $warehouse->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-2">
                <button class="btn btn-primary w-100" type="submit">Filter</button>
            </div>
        </div>
    </form>

    <section class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">Product</th>
                        <th scope="col">Warehouse</th>
                        <th scope="col" class="text-end">Available</th>
                        <th scope="col" class="text-end">Reserved</th>
                        <th scope="col" class="text-end">Picked</th>
                        <th scope="col" class="text-end">Packed</th>
                        <th scope="col" class="text-end">On hand</th>
                        <th scope="col" class="text-end">Shipped</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td><strong>{{ $row->sku }}</strong><span class="d-block small text-body-secondary">{{ $row->product_name }}</span></td>
                            <td><strong>{{ $row->warehouse_code }}</strong><span class="d-block small text-body-secondary">{{ $row->warehouse_name }}</span></td>
                            <td class="text-end">{{ $row->available_quantity }}</td>
                            <td class="text-end">{{ $row->reserved_quantity }}</td>
                            <td class="text-end">{{ $row->picked_quantity }}</td>
                            <td class="text-end">{{ $row->packed_quantity }}</td>
                            <td class="text-end"><strong>{{ $row->on_hand_quantity }}</strong></td>
                            <td class="text-end"><strong>{{ $row->shipped_quantity }}</strong></td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-body-secondary py-5">No matching inventory balances.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
    <div class="mt-4">{{ $rows->links() }}</div>
</x-layouts.app>
