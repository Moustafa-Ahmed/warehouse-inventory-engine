<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Receive stock</title>
</head>
<body>
    <main>
        <p><a href="{{ route('operations.home') }}">Warehouse operations</a></p>
        <h1>Receive stock</h1>

        @if (session('status'))
            <p role="status">{{ session('status') }}</p>
        @endif

        @if ($errors->any())
            <div role="alert" data-message-type="{{ session('message_type', 'validation') }}">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if (session('operation_result'))
            <section aria-labelledby="receipt-result">
                <h2 id="receipt-result">Receipt result</h2>
                <dl>
                    <dt>Operation</dt>
                    <dd>{{ session('operation_result.operation_id') }}</dd>
                    <dt>Movement</dt>
                    <dd>{{ session('operation_result.movement_id') }}</dd>
                    <dt>Received quantity</dt>
                    <dd>{{ session('operation_result.received_quantity') }}</dd>
                    <dt>Available quantity</dt>
                    <dd>{{ session('operation_result.available_quantity') }}</dd>
                </dl>
            </section>
        @endif

        <form method="post" action="{{ route('inventory.receipts.store') }}">
            @csrf
            <input type="hidden" name="operation_key" value="{{ old('operation_key', $operationKey) }}">

            <label for="product_id">Product</label>
            <select id="product_id" name="product_id" required>
                <option value="">Select a product</option>
                @foreach ($products as $product)
                    <option value="{{ $product->id }}" @selected((string) old('product_id') === (string) $product->id)>
                        {{ $product->sku }} — {{ $product->name }}
                    </option>
                @endforeach
            </select>

            <label for="warehouse_id">Warehouse</label>
            <select id="warehouse_id" name="warehouse_id" required>
                <option value="">Select a warehouse</option>
                @foreach ($warehouses as $warehouse)
                    <option value="{{ $warehouse->id }}" @selected((string) old('warehouse_id') === (string) $warehouse->id)>
                        {{ $warehouse->code }} — {{ $warehouse->name }}
                    </option>
                @endforeach
            </select>

            <label for="quantity">Quantity</label>
            <input id="quantity" name="quantity" type="number" min="1" value="{{ old('quantity') }}" required>

            <label for="source_reference">Source reference</label>
            <input id="source_reference" name="source_reference" type="text" maxlength="255" value="{{ old('source_reference') }}" required>

            <button type="submit">Record receipt</button>
        </form>
    </main>
</body>
</html>
