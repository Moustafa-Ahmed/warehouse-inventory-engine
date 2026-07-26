<x-layouts.app title="Operational health">
    <header class="mb-4">
        <div class="d-flex align-items-center gap-2 mb-2">
            <x-ui.badge variant="primary">Administrator</x-ui.badge>
            <span class="text-body-secondary small">Authenticated operational interface</span>
        </div>
        <h1 class="h2 mb-1">Operational health</h1>
        <p class="text-body-secondary mb-0">
            Work requiring attention, followed by the latest inventory evidence.
        </p>
    </header>

    <section class="row g-3 mb-4" aria-label="Operational totals">
        <div class="col-6 col-lg-4 col-xl-2">
            <a class="card h-100 border-0 shadow-sm text-decoration-none" href="{{ route('reports.reservations') }}">
                <div class="card-body">
                    <div class="fs-3 fw-semibold text-body">{{ $partialAllocations->total() }}</div>
                    <div class="small text-body-secondary">Partial allocations</div>
                </div>
            </a>
        </div>
        <div class="col-6 col-lg-4 col-xl-2">
            <a
                class="card h-100 border-0 shadow-sm text-decoration-none"
                href="{{ route('reports.reservations', ['kind' => 'temporary', 'expires_before' => now()->addDay()->toDateString()]) }}"
            >
                <div class="card-body">
                    <div class="fs-3 fw-semibold text-body">{{ $expiringReservations->total() }}</div>
                    <div class="small text-body-secondary">Expiring reservations</div>
                </div>
            </a>
        </div>
        <div class="col-6 col-lg-4 col-xl-2">
            <a class="card h-100 border-0 shadow-sm text-decoration-none" href="{{ route('shipments.index') }}">
                <div class="card-body">
                    <div class="fs-3 fw-semibold text-body">{{ $pendingShipments->total() }}</div>
                    <div class="small text-body-secondary">Pending handoff</div>
                </div>
            </a>
        </div>
        <div class="col-6 col-lg-4 col-xl-2">
            <a class="card h-100 border-0 shadow-sm text-decoration-none" href="{{ route('shipments.index') }}">
                <div class="card-body">
                    <div class="fs-3 fw-semibold text-body">{{ $providerSubmissions->total() }}</div>
                    <div class="small text-body-secondary">Provider attention</div>
                </div>
            </a>
        </div>
        <div class="col-6 col-lg-4 col-xl-2">
            <a class="card h-100 border-0 shadow-sm text-decoration-none" href="{{ route('provider-webhook-receipts.index') }}">
                <div class="card-body">
                    <div class="fs-3 fw-semibold text-body">{{ $pendingWebhookReceipts->total() }}</div>
                    <div class="small text-body-secondary">Pending webhooks</div>
                </div>
            </a>
        </div>
        <div class="col-6 col-lg-4 col-xl-2">
            <a class="card h-100 border-0 shadow-sm text-decoration-none" href="{{ route('reports.movements') }}">
                <div class="card-body">
                    <div class="fs-3 fw-semibold text-body">{{ $recentMovements->total() }}</div>
                    <div class="small text-body-secondary">Recorded movements</div>
                </div>
            </a>
        </div>
    </section>

    <div class="row g-4 mb-4">
        <div class="col-12 col-xl-6">
            <section class="card h-100 border-0 shadow-sm">
                <div class="card-header bg-body border-0 pt-3">
                    <h2 class="h5 mb-0">Reservation attention</h2>
                </div>
                <div class="card-body pt-2">
                    <h3 class="h6 text-body-secondary">Partial allocations</h3>
                    <div class="list-group list-group-flush mb-3">
                        @forelse ($partialAllocations as $reservation)
                            <a
                                class="list-group-item list-group-item-action px-0 d-flex justify-content-between gap-3"
                                href="{{ route('reservations.show', $reservation) }}"
                            >
                                <span>
                                    <strong>{{ $reservation->orderItem->order->order_number }}</strong>
                                    <span class="d-block small text-body-secondary">
                                        {{ $reservation->orderItem->product->sku }} at {{ $reservation->warehouse->code }}
                                    </span>
                                </span>
                                <span class="text-end text-nowrap">
                                    {{ $reservation->outstanding_quantity }} outstanding
                                </span>
                            </a>
                        @empty
                            <p class="small text-body-secondary">No partial allocations need attention.</p>
                        @endforelse
                    </div>

                    <h3 class="h6 text-body-secondary">Expiring reservations</h3>
                    <div class="list-group list-group-flush">
                        @forelse ($expiringReservations as $reservation)
                            <a
                                class="list-group-item list-group-item-action px-0 d-flex justify-content-between gap-3"
                                href="{{ route('reservations.show', $reservation) }}"
                            >
                                <span>
                                    <strong>{{ $reservation->orderItem->order->order_number }}</strong>
                                    <span class="d-block small text-body-secondary">
                                        {{ $reservation->orderItem->product->sku }} at {{ $reservation->warehouse->code }}
                                    </span>
                                </span>
                                <span class="text-end text-nowrap">
                                    {{ $reservation->expires_at->diffForHumans() }}
                                </span>
                            </a>
                        @empty
                            <p class="small text-body-secondary">No temporary reservations expire within a day.</p>
                        @endforelse
                    </div>
                </div>
            </section>
        </div>

        <div class="col-12 col-xl-6">
            <section class="card h-100 border-0 shadow-sm">
                <div class="card-header bg-body border-0 pt-3">
                    <h2 class="h5 mb-0">Shipping attention</h2>
                </div>
                <div class="card-body pt-2">
                    <h3 class="h6 text-body-secondary">Shipments pending carrier handoff</h3>
                    <div class="list-group list-group-flush mb-3">
                        @forelse ($pendingShipments as $shipment)
                            <a
                                class="list-group-item list-group-item-action px-0 d-flex justify-content-between gap-3"
                                href="{{ route('shipments.show', $shipment) }}"
                            >
                                <span>
                                    <strong>{{ $shipment->order->order_number }}</strong>
                                    <span class="d-block small text-body-secondary">{{ $shipment->warehouse->code }}</span>
                                </span>
                                <span>{{ $shipment->items_count }} item(s)</span>
                            </a>
                        @empty
                            <p class="small text-body-secondary">No shipments are waiting for handoff.</p>
                        @endforelse
                    </div>

                    <h3 class="h6 text-body-secondary">Provider submissions</h3>
                    <div class="list-group list-group-flush mb-3">
                        @forelse ($providerSubmissions as $submission)
                            <a
                                class="list-group-item list-group-item-action px-0 d-flex justify-content-between gap-3"
                                href="{{ route('shipments.show', $submission->shipment) }}"
                            >
                                <span>
                                    <strong>{{ $submission->shipment->order->order_number }}</strong>
                                    <span class="d-block small text-body-secondary">
                                        Shipment {{ $submission->shipment_id }}
                                    </span>
                                </span>
                                <x-ui.badge :variant="$submission->status->value === 'unknown' ? 'warning' : 'danger'">
                                    {{ str_replace('_', ' ', ucfirst($submission->status->value)) }}
                                </x-ui.badge>
                            </a>
                        @empty
                            <p class="small text-body-secondary">No unknown or permanently failed submissions.</p>
                        @endforelse
                    </div>

                    <h3 class="h6 text-body-secondary">Pending provider webhooks</h3>
                    <div class="list-group list-group-flush">
                        @forelse ($pendingWebhookReceipts as $receipt)
                            <a
                                class="list-group-item list-group-item-action px-0 d-flex justify-content-between gap-3"
                                href="{{ route('provider-webhook-receipts.show', $receipt) }}"
                            >
                                <span>
                                    <strong>{{ $receipt->external_event_id }}</strong>
                                    <span class="d-block small text-body-secondary">{{ $receipt->event_type->value }}</span>
                                </span>
                                <span class="small text-body-secondary">{{ $receipt->occurred_at->diffForHumans() }}</span>
                            </a>
                        @empty
                            <p class="small text-body-secondary">No provider webhook receipts are pending.</p>
                        @endforelse
                    </div>
                </div>
            </section>
        </div>
    </div>

    <section class="card border-0 shadow-sm">
        <div class="card-header bg-body border-0 pt-3 d-flex justify-content-between align-items-center gap-3">
            <h2 class="h5 mb-0">Recent movements</h2>
            <a class="small" href="{{ route('reports.movements') }}">View movement report</a>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">Product</th>
                        <th scope="col">Path</th>
                        <th scope="col" class="text-end">Quantity</th>
                        <th scope="col">Reference</th>
                        <th scope="col">Recorded</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($recentMovements as $movement)
                        <tr>
                            <td><strong>{{ $movement->product->sku }}</strong></td>
                            <td>
                                {{ $movement->sourceWarehouse?->code ?? 'External' }}
                                / {{ $movement->source_bucket?->value ?? 'source' }}
                                →
                                {{ $movement->destinationWarehouse?->code ?? 'External' }}
                                / {{ $movement->destination_bucket?->value ?? 'destination' }}
                            </td>
                            <td class="text-end">{{ $movement->quantity }}</td>
                            <td>{{ str_replace('_', ' ', $movement->business_reference_type) }}</td>
                            <td>{{ $movement->created_at->format('Y-m-d H:i') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-body-secondary py-5">
                                No inventory movements have been recorded.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</x-layouts.app>
