<x-layouts.app title="Inventory movement history">
    <header class="mb-3">
        <h1 class="h3 mb-1">Inventory movement history</h1>
        <p class="text-body-secondary mb-0">Canonical append-only ledger entries with explicit endpoints and business references.</p>
    </header>
    <x-reports.navigation />

    <form method="get" action="{{ route('reports.movements') }}" class="card border-0 shadow-sm mb-4">
        <div class="card-body row g-3 align-items-end">
            <div class="col-12 col-md-4 col-xl-3">
                <label class="form-label" for="movement_product">Product</label>
                <select class="form-select" id="movement_product" name="product_id">
                    <option value="">All products</option>
                    @foreach ($products as $product)
                        <option value="{{ $product->id }}" @selected((string) request('product_id') === (string) $product->id)>{{ $product->sku }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-4 col-xl-3">
                <label class="form-label" for="movement_warehouse">Warehouse endpoint</label>
                <select class="form-select" id="movement_warehouse" name="warehouse_id">
                    <option value="">All warehouses</option>
                    @foreach ($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}" @selected((string) request('warehouse_id') === (string) $warehouse->id)>{{ $warehouse->code }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-4 col-xl-2">
                <label class="form-label" for="movement_bucket">Bucket endpoint</label>
                <select class="form-select" id="movement_bucket" name="bucket">
                    <option value="">All buckets</option>
                    @foreach ($buckets as $bucket)
                        <option value="{{ $bucket->value }}" @selected(request('bucket') === $bucket->value)>{{ ucfirst($bucket->value) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-4 col-xl-2">
                <label class="form-label" for="reference_type">Reference type</label>
                <input class="form-control" id="reference_type" name="reference_type" type="text" value="{{ request('reference_type') }}">
            </div>
            <div class="col-6 col-md-4 col-xl-2">
                <label class="form-label" for="date_from">From</label>
                <input class="form-control" id="date_from" name="date_from" type="date" value="{{ request('date_from') }}">
            </div>
            <div class="col-6 col-md-4 col-xl-2">
                <label class="form-label" for="date_to">To</label>
                <input class="form-control" id="date_to" name="date_to" type="date" value="{{ request('date_to') }}">
            </div>
            <div class="col-12 col-md-4 col-xl-2">
                <button class="btn btn-primary w-100" type="submit">Filter</button>
            </div>
        </div>
    </form>

    <section class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">Time</th>
                        <th scope="col">Product</th>
                        <th scope="col">Source</th>
                        <th scope="col">Destination</th>
                        <th scope="col" class="text-end">Quantity</th>
                        <th scope="col">Business reference</th>
                        <th scope="col">Actor</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $movement)
                        <tr>
                            <td>{{ $movement->created_at->format('Y-m-d H:i:s') }}</td>
                            <td>{{ $movement->product->sku }}</td>
                            <td>{{ $movement->sourceWarehouse?->code ?? 'External' }} / {{ $movement->source_bucket?->value ?? 'external' }}</td>
                            <td>{{ $movement->destinationWarehouse?->code ?? 'External' }} / {{ $movement->destination_bucket?->value ?? 'external' }}</td>
                            <td class="text-end">{{ $movement->quantity }}</td>
                            <td>{{ $movement->business_reference_type }} / {{ $movement->business_reference_id }}</td>
                            <td>{{ $movement->actor?->name ?? 'System' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-body-secondary py-5">No movements match these filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
    <div class="mt-4">{{ $rows->links() }}</div>
</x-layouts.app>
