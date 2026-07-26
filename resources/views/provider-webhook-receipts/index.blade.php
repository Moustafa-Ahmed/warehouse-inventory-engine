<x-layouts.app title="Provider webhook receipts">
    <header class="mb-4">
        <h1 class="h3 mb-1">Provider webhook receipts</h1>
        <p class="text-body-secondary mb-0">Persisted inbound callback identities and safe processing context.</p>
    </header>

    <section class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">Receipt</th>
                        <th scope="col">Provider</th>
                        <th scope="col">External event</th>
                        <th scope="col">Event type</th>
                        <th scope="col">State</th>
                        <th scope="col">Occurred</th>
                        <th scope="col">Processed</th>
                        <th scope="col"><span class="visually-hidden">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($receipts as $receipt)
                        <tr>
                            <td>{{ $receipt->id }}</td>
                            <td>{{ $receipt->provider }}</td>
                            <td>{{ $receipt->external_event_id }}</td>
                            <td>{{ $receipt->event_type->value }}</td>
                            <td>{{ str_replace('_', ' ', ucfirst($receipt->status->value)) }}</td>
                            <td>{{ $receipt->occurred_at->format('Y-m-d H:i') }}</td>
                            <td>{{ $receipt->processed_at?->format('Y-m-d H:i') ?? 'Pending' }}</td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-outline-primary" href="{{ route('provider-webhook-receipts.show', $receipt) }}">View</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-body-secondary py-5">No provider webhooks have been received.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
    <div class="mt-4">{{ $receipts->links() }}</div>
</x-layouts.app>
