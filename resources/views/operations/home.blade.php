<x-layouts.app title="Operations">
    <header class="page-heading mb-4">
        <div class="d-flex align-items-center gap-2 mb-2">
            <x-ui.badge variant="primary">Administrator</x-ui.badge>
            <span class="text-body-secondary small">Authenticated operational interface</span>
        </div>
        <h1 class="display-6 fw-semibold">Warehouse operations</h1>
        <p class="lead text-body-secondary">
            Use the operational workflows to change inventory through the same locked, idempotent services used by jobs and commands.
        </p>
    </header>

    <div class="row g-4">
        <div class="col-12 col-md-6 col-xl-4">
            <section class="card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <h2 class="h5 card-title">Inventory balances</h2>
                    <p class="card-text text-body-secondary">
                        Inspect warehouse inventory buckets, recent movements, adjustments, and transfers.
                    </p>
                    <a class="btn btn-primary" href="{{ route('inventory.balances.index') }}">View inventory</a>
                </div>
            </section>
        </div>

        <div class="col-12 col-md-6 col-xl-4">
            <section class="card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <h2 class="h5 card-title">Receive stock</h2>
                    <p class="card-text text-body-secondary">
                        Record an external stock receipt and inspect its stored operation result.
                    </p>
                    <a class="btn btn-primary" href="{{ route('inventory.receipts.create') }}">Open receipt form</a>
                </div>
            </section>
        </div>

        <div class="col-12 col-md-6 col-xl-4">
            <section class="card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <h2 class="h5 card-title">Orders and reservations</h2>
                    <p class="card-text text-body-secondary">
                        Create demand, inspect explicit progress quantities, and allocate warehouse inventory.
                    </p>
                    <a class="btn btn-primary" href="{{ route('orders.index') }}">View orders</a>
                </div>
            </section>
        </div>

        <div class="col-12 col-md-6 col-xl-4">
            <section class="card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <h2 class="h5 card-title">Shipments and provider webhooks</h2>
                    <p class="card-text text-body-secondary">
                        Compose packed shipments, inspect provider outcomes, and trace signed callbacks.
                    </p>
                    <a class="btn btn-primary" href="{{ route('shipments.index') }}">View shipments</a>
                </div>
            </section>
        </div>

        <div class="col-12 col-md-6 col-xl-4">
            <section class="card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <h2 class="h5 card-title">Operational reports</h2>
                    <p class="card-text text-body-secondary">
                        Answer current stock, open reservation, consumed order, and movement-history questions.
                    </p>
                    <a class="btn btn-primary" href="{{ route('reports.inventory') }}">Open reports</a>
                </div>
            </section>
        </div>
    </div>
</x-layouts.app>
