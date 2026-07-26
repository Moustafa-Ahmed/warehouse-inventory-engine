<x-layouts.app :title="'Inventory · '.$balance->product->sku.' · '.$balance->warehouse->code">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <header>
            <a class="small" href="{{ route('inventory.balances.index') }}">← Inventory balances</a>
            <h1 class="h3 mt-2 mb-1">{{ $balance->product->sku }} at {{ $balance->warehouse->code }}</h1>
            <p class="text-body-secondary mb-0">
                {{ $balance->product->name }} · {{ $balance->warehouse->name }}
            </p>
        </header>
        <a class="btn btn-outline-primary" href="{{ route('inventory.receipts.create') }}">Receive stock</a>
    </div>

    <section class="row g-3 mb-4" aria-label="Current inventory quantities">
        @foreach ([
            'Available' => $balance->available_quantity,
            'Reserved' => $balance->reserved_quantity,
            'Picked' => $balance->picked_quantity,
            'Packed' => $balance->packed_quantity,
        ] as $label => $quantity)
            <div class="col-6 col-lg-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <p class="small text-uppercase text-body-secondary mb-1">{{ $label }}</p>
                        <p class="display-6 mb-0">{{ $quantity }}</p>
                    </div>
                </div>
            </div>
        @endforeach
    </section>

    @if (session('operation_result'))
        <section class="alert alert-success operation-result mb-4" aria-labelledby="inventory-operation-result">
            <h2 id="inventory-operation-result" class="h5">Operation result</h2>
            <dl class="row mb-0">
                <dt class="col-sm-4">Type</dt>
                <dd class="col-sm-8">{{ ucfirst(session('operation_result.type')) }}</dd>
                <dt class="col-sm-4">Operation</dt>
                <dd class="col-sm-8">{{ session('operation_result.operation_id') }}</dd>
                <dt class="col-sm-4">Movement</dt>
                <dd class="col-sm-8">{{ session('operation_result.movement_id') }}</dd>
                @if (session('operation_result.type') === 'adjustment')
                    <dt class="col-sm-4">Quantity change</dt>
                    <dd class="col-sm-8">{{ session('operation_result.quantity_change') }}</dd>
                    <dt class="col-sm-4">Available now</dt>
                    <dd class="col-sm-8">{{ session('operation_result.available_quantity') }}</dd>
                @else
                    <dt class="col-sm-4">Transferred</dt>
                    <dd class="col-sm-8">{{ session('operation_result.transferred_quantity') }}</dd>
                    <dt class="col-sm-4">Source available now</dt>
                    <dd class="col-sm-8">{{ session('operation_result.source_available_quantity') }}</dd>
                    <dt class="col-sm-4">Destination available now</dt>
                    <dd class="col-sm-8">{{ session('operation_result.destination_available_quantity') }}</dd>
                @endif
            </dl>
        </section>
    @endif

    <div class="row g-4 mb-4">
        <div class="col-12 col-lg-6">
            <section class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h2 class="h5">Adjust available inventory</h2>
                    <p class="small text-body-secondary">Use a positive number to add stock or a negative number to remove available stock.</p>
                    <form method="post" action="{{ route('inventory.adjustments.store', $balance) }}" class="row g-3">
                        @csrf
                        <input
                            type="hidden"
                            name="adjustment_operation_key"
                            value="{{ old('adjustment_operation_key', $adjustmentOperationKey) }}"
                        >
                        <div class="col-12">
                            <label class="form-label" for="quantity_change">Quantity change</label>
                            <input
                                @class(['form-control', 'is-invalid' => $errors->has('quantity_change')])
                                id="quantity_change"
                                name="quantity_change"
                                type="number"
                                value="{{ old('quantity_change') }}"
                                required
                            >
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="reason">Reason</label>
                            <textarea
                                @class(['form-control', 'is-invalid' => $errors->has('reason')])
                                id="reason"
                                name="reason"
                                rows="3"
                                maxlength="500"
                                required
                            >{{ old('reason') }}</textarea>
                        </div>
                        <div class="col-12">
                            <button class="btn btn-primary" type="submit">Record adjustment</button>
                        </div>
                    </form>
                </div>
            </section>
        </div>

        <div class="col-12 col-lg-6">
            <section class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h2 class="h5">Transfer available inventory</h2>
                    <p class="small text-body-secondary">Reserved, picked, and packed stock remains at this warehouse.</p>
                    @if ($destinationWarehouses->isEmpty())
                        <p class="alert alert-warning mb-0">Create another active warehouse before transferring inventory.</p>
                    @else
                        <form method="post" action="{{ route('inventory.transfers.store', $balance) }}" class="row g-3">
                            @csrf
                            <input
                                type="hidden"
                                name="transfer_operation_key"
                                value="{{ old('transfer_operation_key', $transferOperationKey) }}"
                            >
                            <div class="col-12">
                                <label class="form-label" for="destination_warehouse_id">Destination warehouse</label>
                                <select
                                    @class(['form-select', 'is-invalid' => $errors->has('destination_warehouse_id')])
                                    id="destination_warehouse_id"
                                    name="destination_warehouse_id"
                                    required
                                >
                                    <option value="">Select a warehouse</option>
                                    @foreach ($destinationWarehouses as $warehouse)
                                        <option
                                            value="{{ $warehouse->id }}"
                                            @selected((string) old('destination_warehouse_id') === (string) $warehouse->id)
                                        >
                                            {{ $warehouse->code }} — {{ $warehouse->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="transfer_quantity">Quantity</label>
                                <input
                                    @class(['form-control', 'is-invalid' => $errors->has('quantity')])
                                    id="transfer_quantity"
                                    name="quantity"
                                    type="number"
                                    min="1"
                                    value="{{ old('quantity') }}"
                                    required
                                >
                            </div>
                            <div class="col-12">
                                <button class="btn btn-primary" type="submit">Transfer inventory</button>
                            </div>
                        </form>
                    @endif
                </div>
            </section>
        </div>
    </div>

    <section class="card border-0 shadow-sm">
        <div class="card-header bg-white">
            <h2 class="h5 mb-0">Recent movements</h2>
        </div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">Time</th>
                        <th scope="col">Source</th>
                        <th scope="col">Destination</th>
                        <th scope="col" class="text-end">Quantity</th>
                        <th scope="col">Reference</th>
                        <th scope="col">Actor</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($recentMovements as $movement)
                        <tr>
                            <td>{{ $movement->created_at->format('Y-m-d H:i') }}</td>
                            <td>
                                @if ($movement->sourceWarehouse)
                                    {{ $movement->sourceWarehouse->code }} / {{ $movement->source_bucket->value }}
                                @else
                                    External
                                @endif
                            </td>
                            <td>
                                @if ($movement->destinationWarehouse)
                                    {{ $movement->destinationWarehouse->code }} / {{ $movement->destination_bucket->value }}
                                @else
                                    External / {{ $movement->destination_bucket?->value ?? 'adjustment' }}
                                @endif
                            </td>
                            <td class="text-end">{{ $movement->quantity }}</td>
                            <td>{{ $movement->business_reference_type }} / {{ $movement->business_reference_id }}</td>
                            <td>{{ $movement->actor?->name ?? 'System' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-body-secondary py-4">No movements found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</x-layouts.app>
