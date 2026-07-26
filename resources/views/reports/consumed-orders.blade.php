<x-layouts.app title="Orders that consumed inventory">
    <header class="mb-3">
        <h1 class="h3 mb-1">Orders that consumed inventory</h1>
        <p class="text-body-secondary mb-0">Only confirmed packed-to-external shipment movements count as consumption.</p>
    </header>
    <x-reports.navigation />

    <form method="get" action="{{ route('reports.consumed-orders') }}" class="card border-0 shadow-sm mb-4">
        <div class="card-body row g-3 align-items-end">
            <div class="col-12 col-md-4">
                <label class="form-label" for="consumed_order_number">Order number</label>
                <input class="form-control" id="consumed_order_number" name="order_number" type="text" value="{{ request('order_number') }}">
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label" for="consumed_product">Product</label>
                <select class="form-select" id="consumed_product" name="product_id">
                    <option value="">All products</option>
                    @foreach ($products as $product)
                        <option value="{{ $product->id }}" @selected((string) request('product_id') === (string) $product->id)>{{ $product->sku }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label" for="consumed_warehouse">Warehouse</label>
                <select class="form-select" id="consumed_warehouse" name="warehouse_id">
                    <option value="">All warehouses</option>
                    @foreach ($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}" @selected((string) request('warehouse_id') === (string) $warehouse->id)>{{ $warehouse->code }}</option>
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
                        <th scope="col">Order</th>
                        <th scope="col" class="text-end">Consumed quantity</th>
                        <th scope="col">First consumption</th>
                        <th scope="col">Latest consumption</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td><a href="{{ route('orders.show', $row->order_id) }}"><strong>{{ $row->order_number }}</strong></a></td>
                            <td class="text-end"><strong>{{ $row->consumed_quantity }}</strong></td>
                            <td>{{ $row->first_consumed_at }}</td>
                            <td>{{ $row->last_consumed_at }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-body-secondary py-5">No confirmed inventory consumption matches these filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
    <div class="mt-4">{{ $rows->links() }}</div>
</x-layouts.app>
