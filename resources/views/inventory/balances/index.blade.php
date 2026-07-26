<x-layouts.app title="Inventory balances">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <header>
            <h1 class="h3 mb-1">Inventory balances</h1>
            <p class="text-body-secondary mb-0">Current warehouse projections backed by the movement ledger.</p>
        </header>
        <a class="btn btn-primary" href="{{ route('inventory.receipts.create') }}">Receive stock</a>
    </div>

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
                        <th scope="col"><span class="visually-hidden">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($balances as $balance)
                        <tr>
                            <td>
                                <strong>{{ $balance->product->sku }}</strong>
                                <span class="d-block small text-body-secondary">{{ $balance->product->name }}</span>
                            </td>
                            <td>
                                <strong>{{ $balance->warehouse->code }}</strong>
                                <span class="d-block small text-body-secondary">{{ $balance->warehouse->name }}</span>
                            </td>
                            <td class="text-end">{{ $balance->available_quantity }}</td>
                            <td class="text-end">{{ $balance->reserved_quantity }}</td>
                            <td class="text-end">{{ $balance->picked_quantity }}</td>
                            <td class="text-end">{{ $balance->packed_quantity }}</td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-outline-primary" href="{{ route('inventory.balances.show', $balance) }}">
                                    View
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-body-secondary py-5">
                                No inventory balances exist yet. Receive stock to create the first one.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <div class="mt-4">
        {{ $balances->links() }}
    </div>
</x-layouts.app>
