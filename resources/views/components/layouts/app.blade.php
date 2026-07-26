@props(['title'])

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} · Warehouse Inventory Engine</title>
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css"
        rel="stylesheet"
        integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB"
        crossorigin="anonymous"
    >
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-body-tertiary">
    <a class="visually-hidden-focusable position-absolute top-0 start-0 p-2 bg-warning text-dark" href="#main-content">
        Skip to content
    </a>

    <nav class="navbar navbar-expand-lg navbar-dark bg-dark app-navbar" aria-label="Primary navigation">
        <div class="container">
            <a class="navbar-brand fw-semibold" href="{{ route('operations.home') }}">Warehouse Engine</a>
            <button
                class="navbar-toggler"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#primary-navigation"
                aria-controls="primary-navigation"
                aria-expanded="false"
                aria-label="Toggle navigation"
            >
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="primary-navigation">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a
                            @class(['nav-link', 'active' => request()->routeIs('operations.home')])
                            href="{{ route('operations.home') }}"
                            @if (request()->routeIs('operations.home')) aria-current="page" @endif
                        >Operations</a>
                    </li>
                    <li class="nav-item">
                        <a
                            @class(['nav-link', 'active' => request()->routeIs('inventory.receipts.*')])
                            href="{{ route('inventory.receipts.create') }}"
                            @if (request()->routeIs('inventory.receipts.*')) aria-current="page" @endif
                        >Receive stock</a>
                    </li>
                </ul>
                <div class="d-flex align-items-center gap-3 text-white">
                    <span class="small">{{ auth()->user()->name }}</span>
                    <form method="post" action="{{ route('logout') }}" data-confirm="Sign out of the administrator interface?">
                        @csrf
                        <button class="btn btn-outline-light btn-sm" type="submit">Log out</button>
                    </form>
                </div>
            </div>
        </div>
    </nav>

    <main id="main-content" class="container py-4">
        <x-ui.messages />
        {{ $slot }}
    </main>

    <script
        src="https://code.jquery.com/jquery-3.7.1.min.js"
        integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo="
        crossorigin="anonymous"
    ></script>
    <script
        src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI"
        crossorigin="anonymous"
    ></script>
</body>
</html>
