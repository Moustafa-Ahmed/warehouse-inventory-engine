<x-layouts.app title="Products">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <header>
            <h1 class="h3 mb-1">Product catalog</h1>
            <p class="text-body-secondary mb-0">Reference products available to inventory and order workflows.</p>
        </header>
        <a class="btn btn-primary" href="{{ route('products.create') }}">Add product</a>
    </div>

    <nav class="nav nav-pills mb-3" aria-label="Catalog sections">
        <a class="nav-link active" aria-current="page" href="{{ route('products.index') }}">Products</a>
        <a class="nav-link" href="{{ route('warehouses.index') }}">Warehouses</a>
    </nav>

    <section class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">SKU</th>
                        <th scope="col">Name</th>
                        <th scope="col">Status</th>
                        <th scope="col"><span class="visually-hidden">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($products as $product)
                        <tr>
                            <td><strong>{{ $product->sku }}</strong></td>
                            <td>{{ $product->name }}</td>
                            <td>
                                <x-ui.badge :variant="$product->is_active ? 'success' : 'secondary'">
                                    {{ $product->is_active ? 'Active' : 'Inactive' }}
                                </x-ui.badge>
                            </td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-outline-primary" href="{{ route('products.edit', $product) }}">Edit</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center text-body-secondary py-5">
                                No products exist yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <div class="mt-4">{{ $products->links() }}</div>
</x-layouts.app>
