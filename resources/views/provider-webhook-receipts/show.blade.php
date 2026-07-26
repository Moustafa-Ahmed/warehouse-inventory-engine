<x-layouts.app :title="'Provider webhook receipt '.$receipt->id">
    <header class="mb-4">
        <a class="small" href="{{ route('provider-webhook-receipts.index') }}">← Provider webhook receipts</a>
        <h1 class="h3 mt-2 mb-1">Provider webhook receipt {{ $receipt->id }}</h1>
        <p class="text-body-secondary mb-0">Raw callback bodies and authentication material are intentionally not rendered.</p>
    </header>

    <section class="card border-0 shadow-sm">
        <div class="card-body">
            <dl class="row mb-0">
                <dt class="col-sm-4">Provider</dt>
                <dd class="col-sm-8">{{ $receipt->provider }}</dd>
                <dt class="col-sm-4">External event ID</dt>
                <dd class="col-sm-8">{{ $receipt->external_event_id }}</dd>
                <dt class="col-sm-4">Event type</dt>
                <dd class="col-sm-8">{{ $receipt->event_type->value }}</dd>
                <dt class="col-sm-4">Processing state</dt>
                <dd class="col-sm-8">{{ str_replace('_', ' ', ucfirst($receipt->status->value)) }}</dd>
                <dt class="col-sm-4">Occurred at</dt>
                <dd class="col-sm-8">{{ $receipt->occurred_at->format('Y-m-d H:i:s') }}</dd>
                <dt class="col-sm-4">Processed at</dt>
                <dd class="col-sm-8">{{ $receipt->processed_at?->format('Y-m-d H:i:s') ?? 'Not processed' }}</dd>
                <dt class="col-sm-4">Safe failure context</dt>
                <dd class="col-sm-8">{{ $receipt->failure_reason ?? 'None' }}</dd>
            </dl>
        </div>
    </section>
</x-layouts.app>
