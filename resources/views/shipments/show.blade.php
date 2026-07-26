<x-layouts.app :title="'Shipment '.$shipment->id">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <header>
            <a class="small" href="{{ route('shipments.index') }}">← Shipments</a>
            <h1 class="h3 mt-2 mb-1">Shipment {{ $shipment->id }}</h1>
            <p class="text-body-secondary mb-0">
                Order <a href="{{ route('orders.show', $shipment->order) }}">{{ $shipment->order->order_number }}</a>
                · {{ $shipment->warehouse->code }}
            </p>
        </header>
        <x-ui.badge variant="{{ $shipment->status->value === 'shipped' ? 'success' : 'warning' }}">
            {{ str_replace('_', ' ', ucfirst($shipment->status->value)) }}
        </x-ui.badge>
    </div>

    <section class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white d-flex flex-wrap justify-content-between gap-3">
            <h2 class="h5 mb-0">Composed items</h2>
            @if ($shipment->status->value === 'pending_handoff')
                <form method="post" action="{{ route('shipments.submit', $shipment) }}">
                    @csrf
                    <button class="btn btn-primary btn-sm" type="submit">Queue provider submission</button>
                </form>
            @endif
        </div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">Shipment item</th>
                        <th scope="col">Reservation</th>
                        <th scope="col">Product</th>
                        <th scope="col" class="text-end">Quantity</th>
                        <th scope="col" class="text-end">Delivered</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($shipment->items as $item)
                        <tr>
                            <td>{{ $item->id }}</td>
                            <td><a href="{{ route('reservations.show', $item->reservation) }}">{{ $item->reservation_id }}</a></td>
                            <td>{{ $item->reservation->orderItem->product->sku }}</td>
                            <td class="text-end">{{ $item->quantity }}</td>
                            <td class="text-end">{{ $item->delivered_quantity }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    @if ($mockControlsAvailable)
        <section class="card border-primary-subtle shadow-sm mb-4">
            <div class="card-header bg-primary-subtle">
                <h2 class="h5 mb-0">Local mock-provider controls</h2>
            </div>
            <div class="card-body">
                <form method="post" action="{{ route('shipments.mock-provider-scenario.store', $shipment) }}" class="row g-3 align-items-end">
                    @csrf
                    <div class="col-12 col-md-8">
                        <label class="form-label" for="scenario">Next submission outcome</label>
                        <select class="form-select" id="scenario" name="scenario" required>
                            @foreach ($scenarios as $scenario)
                                <option
                                    value="{{ $scenario->value }}"
                                    @selected($scenarioOverride?->scenario === $scenario)
                                >
                                    {{ str_replace('_', ' ', ucfirst($scenario->value)) }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-md-4">
                        <button class="btn btn-outline-primary w-100" type="submit">Set next outcome</button>
                    </div>
                </form>
                <p class="small text-body-secondary mt-2 mb-0">
                    This selector affects provider state on the next submission. It never changes shipment or inventory state directly.
                </p>
            </div>
        </section>
    @endif

    <section class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white">
            <h2 class="h5 mb-0">Provider submissions</h2>
        </div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">Submission</th>
                        <th scope="col">Stable request identity</th>
                        <th scope="col">Outcome</th>
                        <th scope="col">External shipment</th>
                        <th scope="col">Attempted</th>
                        <th scope="col">Safe context</th>
                        <th scope="col"><span class="visually-hidden">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($shipment->providerSubmissions as $submission)
                        <tr>
                            <td>{{ $submission->id }}</td>
                            <td><code>{{ \Illuminate\Support\Str::mask($submission->provider_request_key, '•', 4, -4) }}</code></td>
                            <td>{{ str_replace('_', ' ', ucfirst($submission->status->value)) }}</td>
                            <td>{{ $submission->external_shipment_id ?? '—' }}</td>
                            <td>{{ $submission->last_attempted_at?->format('Y-m-d H:i') ?? 'Not yet' }}</td>
                            <td>{{ $submission->failure_reason ?? '—' }}</td>
                            <td>
                                @if ($submission->status->value === 'unknown')
                                    <form method="post" action="{{ route('shipments.provider-submissions.reconcile', [$shipment, $submission]) }}">
                                        @csrf
                                        <button class="btn btn-sm btn-outline-primary" type="submit">Reconcile</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-body-secondary py-4">
                                No provider submission has been prepared.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @foreach ($mockProviderShipments as $mockShipment)
        <section class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white d-flex flex-wrap justify-content-between gap-2">
                <div>
                    <h2 class="h5 mb-0">Mock-provider shipment {{ $mockShipment->external_shipment_id }}</h2>
                    <span class="small text-body-secondary">
                        {{ str_replace('_', ' ', ucfirst($mockShipment->scenario->value)) }}
                        · {{ $mockShipment->scenario_was_forced ? 'forced' : 'weighted selection' }}
                    </span>
                </div>
                <x-ui.badge variant="secondary">
                    {{ str_replace('_', ' ', ucfirst($mockShipment->status->value)) }}
                </x-ui.badge>
            </div>
            @if ($mockControlsAvailable)
                <div class="card-body border-bottom d-flex flex-wrap gap-2">
                    <form method="post" action="{{ route('shipments.mock-provider.handoff', [$shipment, $mockShipment]) }}">
                        @csrf
                        <button class="btn btn-sm btn-primary" type="submit">Send shipment confirmation</button>
                    </form>
                    <form method="post" action="{{ route('shipments.mock-provider.delivery', [$shipment, $mockShipment]) }}">
                        @csrf
                        <button class="btn btn-sm btn-outline-primary" type="submit">Send delivery confirmation</button>
                    </form>
                    <form method="post" action="{{ route('shipments.mock-provider.out-of-order-delivery', [$shipment, $mockShipment]) }}">
                        @csrf
                        <button class="btn btn-sm btn-outline-warning" type="submit">Send out-of-order delivery</button>
                    </form>
                </div>
            @endif
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col">Outbound webhook</th>
                            <th scope="col">Event</th>
                            <th scope="col">Delivery state</th>
                            <th scope="col" class="text-end">Attempts</th>
                            <th scope="col">Next delivery</th>
                            <th scope="col">HTTP</th>
                            <th scope="col">Safe context</th>
                            <th scope="col"><span class="visually-hidden">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($mockShipment->webhooks as $webhook)
                            <tr>
                                <td>{{ $webhook->external_event_id }}</td>
                                <td>{{ $webhook->event_type->value }}</td>
                                <td>{{ str_replace('_', ' ', ucfirst($webhook->status->value)) }}</td>
                                <td class="text-end">{{ $webhook->attempt_count }}</td>
                                <td>{{ $webhook->next_delivery_at->format('Y-m-d H:i') }}</td>
                                <td>{{ $webhook->last_response_status_code ?? '—' }}</td>
                                <td>{{ $webhook->failure_reason ?? '—' }}</td>
                                <td>
                                    @if ($mockControlsAvailable && $webhook->status->value !== 'delivering')
                                        <form method="post" action="{{ route('mock-provider.webhooks.replay', [$mockShipment, $webhook]) }}">
                                            @csrf
                                            <button class="btn btn-sm btn-outline-primary" type="submit">Replay exact webhook</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-body-secondary py-4">No outbound webhooks exist.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endforeach

    <section class="card border-0 shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h2 class="h5 mb-0">Received provider webhooks</h2>
            <a href="{{ route('provider-webhook-receipts.index') }}">View all</a>
        </div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">Receipt</th>
                        <th scope="col">Event ID</th>
                        <th scope="col">Event</th>
                        <th scope="col">Processing state</th>
                        <th scope="col">Safe context</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($providerWebhookReceipts as $receipt)
                        <tr>
                            <td><a href="{{ route('provider-webhook-receipts.show', $receipt) }}">{{ $receipt->id }}</a></td>
                            <td>{{ $receipt->external_event_id }}</td>
                            <td>{{ $receipt->event_type->value }}</td>
                            <td>{{ str_replace('_', ' ', ucfirst($receipt->status->value)) }}</td>
                            <td>{{ $receipt->failure_reason ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-body-secondary py-4">No related webhook receipt exists yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</x-layouts.app>
