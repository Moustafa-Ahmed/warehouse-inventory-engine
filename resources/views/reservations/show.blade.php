<x-layouts.app :title="'Reservation '.$reservation->id">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <header>
            <a class="small" href="{{ route('orders.show', $reservation->orderItem->order) }}">
                ← Order {{ $reservation->orderItem->order->order_number }}
            </a>
            <h1 class="h3 mt-2 mb-1">Reservation {{ $reservation->id }}</h1>
            <p class="text-body-secondary mb-0">
                {{ $reservation->orderItem->product->sku }} at {{ $reservation->warehouse->code }}
            </p>
        </header>
        <div class="d-flex gap-2">
            <x-ui.badge variant="secondary">{{ ucfirst($reservation->kind->value) }}</x-ui.badge>
            <x-ui.badge variant="{{ $reservation->status->value === 'open' ? 'primary' : 'secondary' }}">
                {{ ucfirst($reservation->status->value) }}
            </x-ui.badge>
        </div>
    </div>

    @if (session('allocation_result'))
        <section class="alert alert-info operation-result mb-4" aria-labelledby="allocation-result">
            <h2 id="allocation-result" class="h5">Allocation result</h2>
            <dl class="row mb-0">
                <dt class="col-sm-5">Requested</dt>
                <dd class="col-sm-7">{{ session('allocation_result.requested_quantity') }}</dd>
                <dt class="col-sm-5">Allocated in this attempt</dt>
                <dd class="col-sm-7">{{ session('allocation_result.allocated_quantity') }}</dd>
                <dt class="col-sm-5">Outstanding after attempt</dt>
                <dd class="col-sm-7"><strong>{{ session('allocation_result.outstanding_quantity') }}</strong></dd>
                <dt class="col-sm-5">Fully allocated</dt>
                <dd class="col-sm-7">{{ session('allocation_result.fully_allocated') ? 'Yes' : 'No' }}</dd>
                @if (session()->has('allocation_result.warehouse_allocated_quantity'))
                    <dt class="col-sm-5">Allocated across warehouse FIFO run</dt>
                    <dd class="col-sm-7">{{ session('allocation_result.warehouse_allocated_quantity') }}</dd>
                @endif
            </dl>
            @unless (session('allocation_result.fully_allocated'))
                <p class="mb-0 mt-2">
                    This is a partial reservation. The outstanding quantity still requires a later allocation.
                </p>
            @endunless
        </section>
    @endif

    @if (session('operation_result'))
        <section class="alert alert-success operation-result mb-4" aria-labelledby="reservation-operation-result">
            <h2 id="reservation-operation-result" class="h5">Operation result</h2>
            <dl class="row mb-0">
                <dt class="col-sm-5">Operation</dt>
                <dd class="col-sm-7">{{ session('operation_result.operation_id') }}</dd>
                @if (session('operation_result.type') === 'reservation_released')
                    <dt class="col-sm-5">Released</dt>
                    <dd class="col-sm-7">{{ session('operation_result.released_quantity') }}</dd>
                    <dt class="col-sm-5">Cancelled demand</dt>
                    <dd class="col-sm-7">{{ session('operation_result.cancelled_quantity') }}</dd>
                    <dt class="col-sm-5">Outstanding demand</dt>
                    <dd class="col-sm-7">{{ session('operation_result.outstanding_quantity') }}</dd>
                @endif
            </dl>
        </section>
    @endif

    @if (session('fulfillment_result'))
        <section class="alert alert-success operation-result mb-4" aria-labelledby="fulfillment-result">
            <h2 id="fulfillment-result" class="h5">Fulfillment operation result</h2>
            <dl class="row mb-0">
                <dt class="col-sm-5">Operation</dt>
                <dd class="col-sm-7">{{ session('fulfillment_result.operation_id') }}</dd>
                <dt class="col-sm-5">Quantity moved</dt>
                <dd class="col-sm-7">{{ session('fulfillment_result.moved_quantity') }}</dd>
                <dt class="col-sm-5">Remaining source quantity</dt>
                <dd class="col-sm-7">{{ session('fulfillment_result.remaining_source_quantity') }}</dd>
            </dl>
        </section>
    @endif

    <div class="row g-4 mb-4">
        <div class="col-12 col-xl-7">
            <section class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white">
                    <h2 class="h5 mb-0">Reservation quantities</h2>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th scope="col">Requested</th>
                                <th scope="col">Awaiting allocation</th>
                                <th scope="col">Reserved</th>
                                <th scope="col">Picked</th>
                                <th scope="col">Packed</th>
                                <th scope="col">Shipped</th>
                                <th scope="col">Released</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>{{ $reservation->requested_quantity }}</td>
                                <td><strong>{{ $remainingRequestedQuantity }}</strong></td>
                                <td>{{ $reservation->reserved_quantity }}</td>
                                <td>{{ $reservation->picked_quantity }}</td>
                                <td>{{ $reservation->packed_quantity }}</td>
                                <td>{{ $reservation->shipped_quantity }}</td>
                                <td>{{ $reservation->released_quantity }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="card-body border-top">
                    <h3 class="h6">Order-item progress</h3>
                    <dl class="row small mb-0">
                        <dt class="col-7">Ordered</dt><dd class="col-5 text-end">{{ $orderItemProgress->orderedQuantity }}</dd>
                        <dt class="col-7">Allocated across reservations</dt><dd class="col-5 text-end">{{ $orderItemProgress->allocatedQuantity }}</dd>
                        <dt class="col-7">Outstanding demand</dt><dd class="col-5 text-end"><strong>{{ $orderItemProgress->outstandingQuantity }}</strong></dd>
                        <dt class="col-7">Delivered</dt><dd class="col-5 text-end">{{ $orderItemProgress->deliveredQuantity }}</dd>
                    </dl>
                </div>
            </section>
        </div>

        <div class="col-12 col-xl-5">
            <section class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white">
                    <h2 class="h5 mb-0">Available actions</h2>
                </div>
                <div class="card-body d-grid gap-4">
                    @if ($canConfirm)
                        <form method="post" action="{{ route('reservations.confirm', $reservation) }}">
                            @csrf
                            <input type="hidden" name="confirmation_operation_key" value="{{ $confirmationOperationKey }}">
                            <button class="btn btn-primary" type="submit">Confirm temporary reservation</button>
                        </form>
                    @endif

                    @if ($canAllocate)
                        <form method="post" action="{{ route('reservations.allocate', $reservation) }}">
                            @csrf
                            <input type="hidden" name="allocation_run_key" value="{{ $allocationRunKey }}">
                            <p class="small text-body-secondary">
                                Runs the warehouse FIFO allocator. Older eligible demand may be allocated first.
                            </p>
                            <button class="btn btn-outline-primary" type="submit">Allocate available stock now</button>
                        </form>
                    @endif

                    @if ($canRelease)
                        <form method="post" action="{{ route('reservations.release', $reservation) }}" class="row g-3">
                            @csrf
                            <input type="hidden" name="release_operation_key" value="{{ $releaseOperationKey }}">
                            <div class="col-12">
                                <label class="form-label" for="release_quantity">Release quantity</label>
                                <input
                                    class="form-control"
                                    id="release_quantity"
                                    name="quantity"
                                    type="number"
                                    min="1"
                                    max="{{ $reservation->reserved_quantity }}"
                                    required
                                >
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="release_reason">Reason</label>
                                <input
                                    class="form-control"
                                    id="release_reason"
                                    name="reason"
                                    type="text"
                                    maxlength="500"
                                    required
                                >
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="cancel_order_demand">Demand after release</label>
                                <select class="form-select" id="cancel_order_demand" name="cancel_order_demand" required>
                                    <option value="0">Keep as outstanding demand</option>
                                    <option value="1">Cancel the released demand</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <button class="btn btn-outline-danger" type="submit">Release reserved stock</button>
                            </div>
                        </form>
                    @endif

                    @unless ($canConfirm || $canAllocate || $canRelease)
                        <p class="text-body-secondary mb-0">No reservation action is currently eligible.</p>
                    @endunless
                </div>
            </section>
        </div>
    </div>

    @if ($canPick || $canReturnPicked || $canPack || $canUnpack)
        <section class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white">
                <h2 class="h5 mb-0">Warehouse fulfillment actions</h2>
            </div>
            <div class="card-body">
                <div class="row g-4">
                    @if ($canPick)
                        <div class="col-12 col-md-6 col-xl-3">
                            <form method="post" action="{{ route('reservations.pick', $reservation) }}">
                                @csrf
                                <input type="hidden" name="pick_operation_key" value="{{ $pickOperationKey }}">
                                <label class="form-label" for="pick_quantity">Pick from reserved</label>
                                <input class="form-control mb-2" id="pick_quantity" name="quantity" type="number" min="1" max="{{ $reservation->reserved_quantity }}" required>
                                <button class="btn btn-primary btn-sm" type="submit">Pick inventory</button>
                            </form>
                        </div>
                    @endif

                    @if ($canReturnPicked)
                        <div class="col-12 col-md-6 col-xl-3">
                            <form method="post" action="{{ route('reservations.return-picked', $reservation) }}">
                                @csrf
                                <input type="hidden" name="return_operation_key" value="{{ $returnOperationKey }}">
                                <label class="form-label" for="return_quantity">Return picked to available</label>
                                <input class="form-control mb-2" id="return_quantity" name="quantity" type="number" min="1" max="{{ $reservation->picked_quantity }}" required>
                                <label class="form-label" for="return_reason">Reason</label>
                                <input class="form-control mb-2" id="return_reason" name="reason" type="text" maxlength="500" required>
                                <button class="btn btn-outline-primary btn-sm" type="submit">Return inventory</button>
                            </form>
                        </div>
                    @endif

                    @if ($canPack)
                        <div class="col-12 col-md-6 col-xl-3">
                            <form method="post" action="{{ route('reservations.pack', $reservation) }}">
                                @csrf
                                <input type="hidden" name="pack_operation_key" value="{{ $packOperationKey }}">
                                <label class="form-label" for="pack_quantity">Pack from picked</label>
                                <input class="form-control mb-2" id="pack_quantity" name="quantity" type="number" min="1" max="{{ $reservation->picked_quantity }}" required>
                                <button class="btn btn-primary btn-sm" type="submit">Pack inventory</button>
                            </form>
                        </div>
                    @endif

                    @if ($canUnpack)
                        <div class="col-12 col-md-6 col-xl-3">
                            <form method="post" action="{{ route('reservations.unpack', $reservation) }}">
                                @csrf
                                <input type="hidden" name="unpack_operation_key" value="{{ $unpackOperationKey }}">
                                <label class="form-label" for="unpack_quantity">Unpack to picked</label>
                                <input class="form-control mb-2" id="unpack_quantity" name="quantity" type="number" min="1" max="{{ $reservation->packed_quantity }}" required>
                                <label class="form-label" for="unpack_reason">Reason</label>
                                <input class="form-control mb-2" id="unpack_reason" name="reason" type="text" maxlength="500" required>
                                <button class="btn btn-outline-primary btn-sm" type="submit">Unpack inventory</button>
                            </form>
                        </div>
                    @endif
                </div>
            </div>
        </section>
    @endif

    <section class="card border-0 shadow-sm">
        <div class="card-header bg-white">
            <h2 class="h5 mb-0">Transition timeline</h2>
        </div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">Time</th>
                        <th scope="col">Source</th>
                        <th scope="col">Kind</th>
                        <th scope="col">State</th>
                        <th scope="col">Reserved</th>
                        <th scope="col">Picked</th>
                        <th scope="col">Packed</th>
                        <th scope="col">Shipped</th>
                        <th scope="col">Released</th>
                        <th scope="col">Reason</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($reservation->transitions as $transition)
                        <tr>
                            <td>{{ $transition->created_at->format('Y-m-d H:i') }}</td>
                            <td>{{ $transition->actor?->name ?? $transition->source }}</td>
                            <td>{{ $transition->before_kind->value }} → {{ $transition->after_kind->value }}</td>
                            <td>{{ $transition->before_status->value }} → {{ $transition->after_status->value }}</td>
                            <td>{{ $transition->before_reserved_quantity }} → {{ $transition->after_reserved_quantity }}</td>
                            <td>{{ $transition->before_picked_quantity }} → {{ $transition->after_picked_quantity }}</td>
                            <td>{{ $transition->before_packed_quantity }} → {{ $transition->after_packed_quantity }}</td>
                            <td>{{ $transition->before_shipped_quantity }} → {{ $transition->after_shipped_quantity }}</td>
                            <td>{{ $transition->before_released_quantity }} → {{ $transition->after_released_quantity }}</td>
                            <td>{{ $transition->reason }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="text-center text-body-secondary py-4">
                                No transition was recorded because this attempt allocated zero inventory.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</x-layouts.app>
