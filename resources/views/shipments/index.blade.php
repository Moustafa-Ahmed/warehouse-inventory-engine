<x-layouts.app title="Shipments">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <header>
            <h1 class="h3 mb-1">Shipments</h1>
            <p class="text-body-secondary mb-0">Packed shipment composition and provider handoff history.</p>
        </header>
        <a class="btn btn-primary" href="{{ route('shipments.create') }}">Compose shipment</a>
    </div>

    <section class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">Shipment</th>
                        <th scope="col">Order</th>
                        <th scope="col">Warehouse</th>
                        <th scope="col">State</th>
                        <th scope="col" class="text-end">Items</th>
                        <th scope="col">Created</th>
                        <th scope="col"><span class="visually-hidden">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($shipments as $shipment)
                        <tr>
                            <td><strong>{{ $shipment->id }}</strong></td>
                            <td>{{ $shipment->order->order_number }}</td>
                            <td>{{ $shipment->warehouse->code }}</td>
                            <td>{{ str_replace('_', ' ', ucfirst($shipment->status->value)) }}</td>
                            <td class="text-end">{{ $shipment->items_count }}</td>
                            <td>{{ $shipment->created_at->format('Y-m-d H:i') }}</td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-outline-primary" href="{{ route('shipments.show', $shipment) }}">View</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-body-secondary py-5">No shipments exist yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <div class="mt-4">{{ $shipments->links() }}</div>
</x-layouts.app>
