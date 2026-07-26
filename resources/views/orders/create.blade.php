<x-layouts.app title="Create order">
    <div class="row justify-content-center">
        <div class="col-12 col-xl-9">
            <section class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <a class="small" href="{{ route('orders.index') }}">← Orders</a>
                    <h1 class="h3 mt-2">Create order</h1>
                    <p class="text-body-secondary">
                        The first line is required. Two optional lines allow a multi-product order without JavaScript.
                    </p>

                    <form method="post" action="{{ route('orders.store') }}" class="d-grid gap-4">
                        @csrf
                        <input type="hidden" name="order_operation_key" value="{{ old('order_operation_key', $operationKey) }}">

                        <div>
                            <label class="form-label" for="order_number">Order number</label>
                            <input
                                @class(['form-control', 'is-invalid' => $errors->has('order_number')])
                                id="order_number"
                                name="order_number"
                                type="text"
                                maxlength="255"
                                value="{{ old('order_number') }}"
                                required
                            >
                        </div>

                        <fieldset>
                            <legend class="h5">Order items</legend>
                            <div class="d-grid gap-3">
                                @for ($index = 0; $index < 3; $index++)
                                    <div class="row g-3 p-3 border rounded">
                                        <div class="col-12 col-md-8">
                                            <label class="form-label" for="item_{{ $index }}_product">
                                                Product {{ $index + 1 }} @if ($index > 0) <span class="text-body-secondary">(optional)</span> @endif
                                            </label>
                                            <select
                                                @class(['form-select', 'is-invalid' => $errors->has("items.$index.product_id")])
                                                id="item_{{ $index }}_product"
                                                name="items[{{ $index }}][product_id]"
                                                @required($index === 0)
                                            >
                                                <option value="">Select a product</option>
                                                @foreach ($products as $product)
                                                    <option
                                                        value="{{ $product->id }}"
                                                        @selected((string) old("items.$index.product_id") === (string) $product->id)
                                                    >
                                                        {{ $product->sku }} — {{ $product->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-12 col-md-4">
                                            <label class="form-label" for="item_{{ $index }}_quantity">Ordered quantity</label>
                                            <input
                                                @class(['form-control', 'is-invalid' => $errors->has("items.$index.ordered_quantity")])
                                                id="item_{{ $index }}_quantity"
                                                name="items[{{ $index }}][ordered_quantity]"
                                                type="number"
                                                min="1"
                                                value="{{ old("items.$index.ordered_quantity") }}"
                                                @required($index === 0)
                                            >
                                        </div>
                                    </div>
                                @endfor
                            </div>
                        </fieldset>

                        <div>
                            <button class="btn btn-primary" type="submit">Create order</button>
                        </div>
                    </form>
                </div>
            </section>
        </div>
    </div>
</x-layouts.app>
