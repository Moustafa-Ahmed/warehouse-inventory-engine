<x-layouts.app title="Orders">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <header>
            <h1 class="h3 mb-1">Orders</h1>
            <p class="text-body-secondary mb-0">Demand, reservation, fulfillment, and delivery quantities.</p>
        </header>
        <a class="btn btn-primary" href="{{ route('orders.create') }}">Create order</a>
    </div>

    <section class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">Order</th>
                        <th scope="col" class="text-end">Items</th>
                        <th scope="col">Created</th>
                        <th scope="col"><span class="visually-hidden">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($orders as $order)
                        <tr>
                            <td><strong>{{ $order->order_number }}</strong></td>
                            <td class="text-end">{{ $order->items_count }}</td>
                            <td>{{ $order->created_at->format('Y-m-d H:i') }}</td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-outline-primary" href="{{ route('orders.show', $order) }}">View</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center text-body-secondary py-5">
                                No orders exist yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <div class="mt-4">
        {{ $orders->links() }}
    </div>
</x-layouts.app>
