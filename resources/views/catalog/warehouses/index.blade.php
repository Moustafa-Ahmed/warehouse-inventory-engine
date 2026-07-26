<x-layouts.app title="Warehouses">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <header>
            <h1 class="h3 mb-1">Warehouse catalog</h1>
            <p class="text-body-secondary mb-0">Reference locations available to inventory and fulfillment workflows.</p>
        </header>
        <a class="btn btn-primary" href="{{ route('warehouses.create') }}">Add warehouse</a>
    </div>

    <nav class="nav nav-pills mb-3" aria-label="Catalog sections">
        <a class="nav-link" href="{{ route('products.index') }}">Products</a>
        <a class="nav-link active" aria-current="page" href="{{ route('warehouses.index') }}">Warehouses</a>
    </nav>

    <section class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">Code</th>
                        <th scope="col">Name</th>
                        <th scope="col">Status</th>
                        <th scope="col"><span class="visually-hidden">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($warehouses as $warehouse)
                        <tr>
                            <td><strong>{{ $warehouse->code }}</strong></td>
                            <td>{{ $warehouse->name }}</td>
                            <td><x-ui.badge :value="$warehouse->is_active ? 'active' : 'inactive'" /></td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-outline-primary" href="{{ route('warehouses.edit', $warehouse) }}">Edit</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center text-body-secondary py-5">
                                No warehouses exist yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <div class="mt-4">{{ $warehouses->links() }}</div>
</x-layouts.app>
