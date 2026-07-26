<x-layouts.app title="Reservation report">
    <header class="mb-3">
        <h1 class="h3 mb-1">Reservation report</h1>
        <p class="text-body-secondary mb-0">Open commitments by default, with warehouse, order, age, expiry, kind, and state filters.</p>
    </header>
    <x-reports.navigation />

    <form method="get" action="{{ route('reports.reservations') }}" class="card border-0 shadow-sm mb-4">
        <div class="card-body row g-3 align-items-end">
            <div class="col-12 col-md-4 col-xl-3">
                <label class="form-label" for="reservation_product">Product</label>
                <select class="form-select" id="reservation_product" name="product_id">
                    <option value="">All products</option>
                    @foreach ($products as $product)
                        <option value="{{ $product->id }}" @selected((string) request('product_id') === (string) $product->id)>{{ $product->sku }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-4 col-xl-3">
                <label class="form-label" for="reservation_warehouse">Warehouse</label>
                <select class="form-select" id="reservation_warehouse" name="warehouse_id">
                    <option value="">All warehouses</option>
                    @foreach ($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}" @selected((string) request('warehouse_id') === (string) $warehouse->id)>{{ $warehouse->code }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-4 col-xl-3">
                <label class="form-label" for="order_number">Order number</label>
                <input class="form-control" id="order_number" name="order_number" type="text" value="{{ request('order_number') }}">
            </div>
            <div class="col-6 col-md-3 col-xl-2">
                <label class="form-label" for="reservation_status">State</label>
                <select class="form-select" id="reservation_status" name="status">
                    @foreach ($statuses as $status)
                        <option value="{{ $status->value }}" @selected(request('status', 'open') === $status->value)>
                            {{ ucfirst($status->value) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-3 col-xl-2">
                <label class="form-label" for="reservation_kind">Kind</label>
                <select class="form-select" id="reservation_kind" name="kind">
                    <option value="">All kinds</option>
                    @foreach ($kinds as $kind)
                        <option value="{{ $kind->value }}" @selected(request('kind') === $kind->value)>{{ ucfirst($kind->value) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-3 col-xl-2">
                <label class="form-label" for="minimum_age_days">Minimum age (days)</label>
                <input class="form-control" id="minimum_age_days" name="minimum_age_days" type="number" min="0" value="{{ request('minimum_age_days') }}">
            </div>
            <div class="col-6 col-md-3 col-xl-2">
                <label class="form-label" for="expires_after">Expires after</label>
                <input class="form-control" id="expires_after" name="expires_after" type="date" value="{{ request('expires_after') }}">
            </div>
            <div class="col-6 col-md-3 col-xl-2">
                <label class="form-label" for="expires_before">Expires before</label>
                <input class="form-control" id="expires_before" name="expires_before" type="date" value="{{ request('expires_before') }}">
            </div>
            <div class="col-12 col-md-3 col-xl-2">
                <button class="btn btn-primary w-100" type="submit">Filter</button>
            </div>
        </div>
    </form>

    <section class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">Reservation</th>
                        <th scope="col">Order</th>
                        <th scope="col">Product</th>
                        <th scope="col">Warehouse</th>
                        <th scope="col">Kind / state</th>
                        <th scope="col" class="text-end">Requested</th>
                        <th scope="col" class="text-end">Reserved</th>
                        <th scope="col" class="text-end">Picked</th>
                        <th scope="col" class="text-end">Packed</th>
                        <th scope="col">Created / expiry</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $reservation)
                        <tr>
                            <td><a href="{{ route('reservations.show', $reservation) }}">{{ $reservation->id }}</a></td>
                            <td><a href="{{ route('orders.show', $reservation->orderItem->order) }}">{{ $reservation->orderItem->order->order_number }}</a></td>
                            <td>{{ $reservation->orderItem->product->sku }}</td>
                            <td>{{ $reservation->warehouse->code }}</td>
                            <td>{{ $reservation->kind->value }} / {{ $reservation->status->value }}</td>
                            <td class="text-end">{{ $reservation->requested_quantity }}</td>
                            <td class="text-end">{{ $reservation->reserved_quantity }}</td>
                            <td class="text-end">{{ $reservation->picked_quantity }}</td>
                            <td class="text-end">{{ $reservation->packed_quantity }}</td>
                            <td>
                                {{ $reservation->created_at->format('Y-m-d H:i') }}
                                <span class="d-block small text-body-secondary">{{ $reservation->expires_at?->format('Y-m-d H:i') ?? 'No expiry' }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="text-center text-body-secondary py-5">No reservations match these filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
    <div class="mt-4">{{ $rows->links() }}</div>
</x-layouts.app>
