<x-layouts.app title="Compose shipment">
    <div class="row justify-content-center">
        <div class="col-12 col-xl-10">
            <header class="mb-4">
                <a class="small" href="{{ route('shipments.index') }}">← Shipments</a>
                <h1 class="h3 mt-2 mb-1">Compose shipment</h1>
                <p class="text-body-secondary mb-0">
                    Select one order and warehouse group, then assign its unassigned packed quantities.
                </p>
            </header>

            <section class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <form method="get" action="{{ route('shipments.create') }}" class="row g-3 align-items-end">
                        <div class="col-12 col-md-9">
                            <label class="form-label" for="group">Packed order and warehouse</label>
                            <select class="form-select" id="group" name="group" required>
                                <option value="">Select packed inventory</option>
                                @foreach ($groups as $group)
                                    <option
                                        value="{{ $group['key'] }}"
                                        @selected($selectedGroup && $selectedGroup['key'] === $group['key'])
                                    >
                                        {{ $group['order_number'] }} — {{ $group['warehouse_code'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 col-md-3">
                            <button class="btn btn-outline-primary w-100" type="submit">Load packed items</button>
                        </div>
                    </form>
                </div>
            </section>

            @if ($groups->isEmpty())
                <p class="alert alert-info">No unshipped packed reservations are available. Pick and pack a confirmed reservation first.</p>
            @elseif ($selectedGroup)
                <section class="card border-0 shadow-sm">
                    <div class="card-body">
                        <h2 class="h5">{{ $selectedGroup['order_number'] }} from {{ $selectedGroup['warehouse_code'] }}</h2>
                        @if ($reservationRows->isEmpty())
                            <p class="alert alert-warning mb-0">All packed quantity in this group is already assigned to a pending shipment.</p>
                        @else
                            <form method="post" action="{{ route('shipments.store') }}">
                                @csrf
                                <input type="hidden" name="order_id" value="{{ $selectedGroup['order_id'] }}">
                                <input type="hidden" name="warehouse_id" value="{{ $selectedGroup['warehouse_id'] }}">
                                <input type="hidden" name="shipment_operation_key" value="{{ old('shipment_operation_key', $operationKey) }}">

                                <div class="table-responsive mb-3">
                                    <table class="table align-middle mb-0">
                                        <thead>
                                            <tr>
                                                <th scope="col">Reservation</th>
                                                <th scope="col">Product</th>
                                                <th scope="col" class="text-end">Unassigned packed</th>
                                                <th scope="col">Shipment quantity</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($reservationRows as $row)
                                                <tr>
                                                    <td>
                                                        <a href="{{ route('reservations.show', $row['reservation']) }}">
                                                            {{ $row['reservation']->id }}
                                                        </a>
                                                    </td>
                                                    <td>{{ $row['reservation']->orderItem->product->sku }}</td>
                                                    <td class="text-end">{{ $row['unassigned_quantity'] }}</td>
                                                    <td>
                                                        <input
                                                            class="form-control"
                                                            name="items[{{ $row['reservation']->id }}]"
                                                            type="number"
                                                            min="1"
                                                            max="{{ $row['unassigned_quantity'] }}"
                                                            value="{{ old('items.'.$row['reservation']->id) }}"
                                                        >
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                                <button class="btn btn-primary" type="submit">Compose shipment</button>
                            </form>
                        @endif
                    </div>
                </section>
            @endif
        </div>
    </div>
</x-layouts.app>
