<x-layouts.app :title="'Order '.$order->order_number">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <header>
            <a class="small" href="{{ route('orders.index') }}">← Orders</a>
            <h1 class="h3 mt-2 mb-1">{{ $order->order_number }}</h1>
            <p class="text-body-secondary mb-0">Created {{ $order->created_at->format('Y-m-d H:i') }}</p>
        </header>
        <a class="btn btn-outline-primary" href="{{ route('orders.create') }}">Create another order</a>
    </div>

    @if (session('operation_result'))
        <section class="alert alert-success operation-result mb-4" aria-labelledby="order-operation-result">
            <h2 id="order-operation-result" class="h5">Operation result</h2>
            <dl class="row mb-0">
                <dt class="col-sm-4">Operation</dt>
                <dd class="col-sm-8">{{ session('operation_result.operation_id') }}</dd>
                @if (session('operation_result.type') === 'order_item_edited')
                    <dt class="col-sm-4">Ordered quantity</dt>
                    <dd class="col-sm-8">{{ session('operation_result.ordered_quantity') }}</dd>
                    <dt class="col-sm-4">Released reserved quantity</dt>
                    <dd class="col-sm-8">{{ session('operation_result.released_reserved_quantity') }}</dd>
                    <dt class="col-sm-4">Outstanding quantity</dt>
                    <dd class="col-sm-8">{{ session('operation_result.outstanding_quantity') }}</dd>
                @endif
            </dl>
        </section>
    @endif

    <div class="d-grid gap-4">
        @foreach ($itemRows as $row)
            @php
                $item = $row['item'];
                $progress = $row['progress'];
            @endphp
            <article class="card border-0 shadow-sm">
                <div class="card-header bg-white d-flex flex-wrap justify-content-between gap-2">
                    <div>
                        <h2 class="h5 mb-0">{{ $item->product->sku }} — {{ $item->product->name }}</h2>
                        <span class="small text-body-secondary">Order item {{ $item->id }}</span>
                    </div>
                    <x-ui.badge variant="{{ $progress->outstandingQuantity > 0 ? 'warning' : 'success' }}">
                        {{ $progress->outstandingQuantity }} outstanding
                    </x-ui.badge>
                </div>
                <div class="card-body">
                    <div class="row g-3 mb-4">
                        <section class="col-12 col-lg-4" aria-labelledby="allocation-{{ $item->id }}">
                            <div class="border rounded p-3 h-100">
                                <h3 id="allocation-{{ $item->id }}" class="h6">Allocation quantities</h3>
                                <dl class="row small mb-0">
                                    <dt class="col-7">Ordered</dt><dd class="col-5 text-end">{{ $progress->orderedQuantity }}</dd>
                                    <dt class="col-7">Allocated</dt><dd class="col-5 text-end">{{ $progress->allocatedQuantity }}</dd>
                                    <dt class="col-7">Outstanding</dt><dd class="col-5 text-end"><strong>{{ $progress->outstandingQuantity }}</strong></dd>
                                    <dt class="col-7">Cancelled</dt><dd class="col-5 text-end">{{ $progress->cancelledQuantity }}</dd>
                                </dl>
                            </div>
                        </section>
                        <section class="col-12 col-lg-4" aria-labelledby="fulfillment-{{ $item->id }}">
                            <div class="border rounded p-3 h-100">
                                <h3 id="fulfillment-{{ $item->id }}" class="h6">Fulfillment quantities</h3>
                                <dl class="row small mb-0">
                                    <dt class="col-7">Reserved</dt><dd class="col-5 text-end">{{ $progress->reservedQuantity }}</dd>
                                    <dt class="col-7">Picked</dt><dd class="col-5 text-end">{{ $progress->pickedQuantity }}</dd>
                                    <dt class="col-7">Packed</dt><dd class="col-5 text-end">{{ $progress->packedQuantity }}</dd>
                                    <dt class="col-7">Shipped</dt><dd class="col-5 text-end">{{ $progress->shippedQuantity }}</dd>
                                </dl>
                            </div>
                        </section>
                        <section class="col-12 col-lg-4" aria-labelledby="delivery-{{ $item->id }}">
                            <div class="border rounded p-3 h-100">
                                <h3 id="delivery-{{ $item->id }}" class="h6">Delivery quantities</h3>
                                <dl class="row small mb-0">
                                    <dt class="col-7">Shipped</dt><dd class="col-5 text-end">{{ $progress->shippedQuantity }}</dd>
                                    <dt class="col-7">Delivered</dt><dd class="col-5 text-end">{{ $progress->deliveredQuantity }}</dd>
                                    <dt class="col-7">Undelivered shipped</dt><dd class="col-5 text-end">{{ $progress->undeliveredShippedQuantity }}</dd>
                                </dl>
                            </div>
                        </section>
                    </div>

                    <div class="row g-4">
                        <div class="col-12 col-xl-5">
                            <h3 class="h6">Edit ordered quantity by delta</h3>
                            <form method="post" action="{{ route('orders.items.update', [$order, $item]) }}" class="row g-2">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="edit_operation_key" value="{{ $row['editOperationKey'] }}">
                                <div class="col-12 col-md-4">
                                    <label class="form-label" for="quantity_change_{{ $item->id }}">Change</label>
                                    <input class="form-control" id="quantity_change_{{ $item->id }}" name="quantity_change" type="number" required>
                                </div>
                                <div class="col-12 col-md-8">
                                    <label class="form-label" for="edit_reason_{{ $item->id }}">Reason</label>
                                    <input class="form-control" id="edit_reason_{{ $item->id }}" name="reason" type="text" maxlength="500" required>
                                </div>
                                <div class="col-12">
                                    <button class="btn btn-outline-primary btn-sm" type="submit">Apply quantity change</button>
                                </div>
                            </form>
                        </div>

                        <div class="col-12 col-xl-7">
                            <h3 class="h6">Reserve outstanding quantity</h3>
                            @if ($progress->outstandingQuantity === 0)
                                <p class="text-body-secondary mb-0">This item has no outstanding quantity to reserve.</p>
                            @elseif ($warehouses->isEmpty())
                                <p class="alert alert-warning mb-0">No active warehouse is available.</p>
                            @else
                                <form method="post" action="{{ route('orders.items.reservations.store', [$order, $item]) }}" class="row g-2">
                                    @csrf
                                    <input type="hidden" name="reservation_operation_key" value="{{ $row['reservationOperationKey'] }}">
                                    <div class="col-12 col-md-5">
                                        <label class="form-label" for="warehouse_{{ $item->id }}">Warehouse</label>
                                        <select class="form-select" id="warehouse_{{ $item->id }}" name="warehouse_id" required>
                                            <option value="">Select warehouse</option>
                                            @foreach ($warehouses as $warehouse)
                                                <option value="{{ $warehouse->id }}">{{ $warehouse->code }} — {{ $warehouse->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-12 col-md-3">
                                        <label class="form-label" for="kind_{{ $item->id }}">Kind</label>
                                        <select class="form-select" id="kind_{{ $item->id }}" name="kind" required>
                                            <option value="confirmed">Confirmed</option>
                                            <option value="temporary">Temporary</option>
                                        </select>
                                    </div>
                                    <div class="col-12 col-md-4">
                                        <label class="form-label" for="expires_at_{{ $item->id }}">Temporary expiry</label>
                                        <input class="form-control" id="expires_at_{{ $item->id }}" name="expires_at" type="datetime-local">
                                    </div>
                                    <div class="col-12">
                                        <button class="btn btn-primary btn-sm" type="submit">Attempt reservation</button>
                                    </div>
                                </form>
                            @endif
                        </div>
                    </div>

                    <hr>
                    <h3 class="h6">Reservations</h3>
                    @if ($item->reservations->isEmpty())
                        <p class="text-body-secondary mb-0">No reservation attempts yet.</p>
                    @else
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th scope="col">Reservation</th>
                                        <th scope="col">Warehouse</th>
                                        <th scope="col">Kind</th>
                                        <th scope="col">State</th>
                                        <th scope="col" class="text-end">Requested</th>
                                        <th scope="col" class="text-end">Reserved</th>
                                        <th scope="col"><span class="visually-hidden">Actions</span></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($item->reservations as $reservation)
                                        <tr>
                                            <td>{{ $reservation->id }}</td>
                                            <td>{{ $reservation->warehouse->code }}</td>
                                            <td>{{ ucfirst($reservation->kind->value) }}</td>
                                            <td>{{ ucfirst($reservation->status->value) }}</td>
                                            <td class="text-end">{{ $reservation->requested_quantity }}</td>
                                            <td class="text-end">{{ $reservation->reserved_quantity }}</td>
                                            <td class="text-end">
                                                <a class="btn btn-sm btn-outline-primary" href="{{ route('reservations.show', $reservation) }}">View</a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </article>
        @endforeach
    </div>
</x-layouts.app>
