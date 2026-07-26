<x-layouts.app title="Receive stock">
    <div class="row g-4">
        <div class="col-12 col-xl-7">
            <section class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <h1 class="h3">Receive stock</h1>
                    <p class="text-body-secondary">
                        Add externally received units to one warehouse’s available inventory.
                    </p>

                    <form method="post" action="{{ route('inventory.receipts.store') }}" class="row g-3">
                        @csrf
                        <input type="hidden" name="operation_key" value="{{ old('operation_key', $operationKey) }}">

                        <div class="col-12">
                            <label class="form-label" for="product_id">Product</label>
                            <select
                                @class(['form-select', 'is-invalid' => $errors->has('product_id')])
                                id="product_id"
                                name="product_id"
                                required
                            >
                                <option value="">Select a product</option>
                                @foreach ($products as $product)
                                    <option value="{{ $product->id }}" @selected((string) old('product_id') === (string) $product->id)>
                                        {{ $product->sku }} — {{ $product->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label" for="warehouse_id">Warehouse</label>
                            <select
                                @class(['form-select', 'is-invalid' => $errors->has('warehouse_id')])
                                id="warehouse_id"
                                name="warehouse_id"
                                required
                            >
                                <option value="">Select a warehouse</option>
                                @foreach ($warehouses as $warehouse)
                                    <option value="{{ $warehouse->id }}" @selected((string) old('warehouse_id') === (string) $warehouse->id)>
                                        {{ $warehouse->code }} — {{ $warehouse->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-12 col-md-4">
                            <label class="form-label" for="quantity">Quantity</label>
                            <input
                                @class(['form-control', 'is-invalid' => $errors->has('quantity')])
                                id="quantity"
                                name="quantity"
                                type="number"
                                min="1"
                                value="{{ old('quantity') }}"
                                required
                            >
                        </div>

                        <div class="col-12 col-md-8">
                            <label class="form-label" for="source_reference">Source reference</label>
                            <input
                                @class(['form-control', 'is-invalid' => $errors->has('source_reference')])
                                id="source_reference"
                                name="source_reference"
                                type="text"
                                maxlength="255"
                                value="{{ old('source_reference') }}"
                                required
                            >
                        </div>

                        <div class="col-12">
                            <button class="btn btn-primary" type="submit">Record receipt</button>
                        </div>
                    </form>
                </div>
            </section>
        </div>

        <div class="col-12 col-xl-5">
            @if (session('operation_result'))
                <section class="card border-0 shadow-sm operation-result" aria-labelledby="receipt-result">
                    <div class="card-body">
                        <h2 id="receipt-result" class="h5">Receipt result</h2>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <tbody>
                                    <tr>
                                        <th scope="row">Operation</th>
                                        <td>{{ session('operation_result.operation_id') }}</td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Movement</th>
                                        <td>{{ session('operation_result.movement_id') }}</td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Received</th>
                                        <td>{{ session('operation_result.received_quantity') }}</td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Available now</th>
                                        <td>{{ session('operation_result.available_quantity') }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>
            @else
                <aside class="card border-0 bg-primary-subtle">
                    <div class="card-body">
                        <h2 class="h5">Idempotent browser workflow</h2>
                        <p class="mb-0">
                            This form carries one operation key. Replaying the same submission returns its stored result instead of receiving stock twice.
                        </p>
                    </div>
                </aside>
            @endif
        </div>
    </div>
</x-layouts.app>
