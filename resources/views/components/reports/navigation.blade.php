<nav class="nav nav-pills flex-column flex-sm-row mb-4" aria-label="Operational reports">
    <a @class(['nav-link', 'active' => request()->routeIs('reports.inventory')]) href="{{ route('reports.inventory') }}">
        Inventory
    </a>
    <a @class(['nav-link', 'active' => request()->routeIs('reports.reservations')]) href="{{ route('reports.reservations') }}">
        Reservations
    </a>
    <a @class(['nav-link', 'active' => request()->routeIs('reports.consumed-orders')]) href="{{ route('reports.consumed-orders') }}">
        Consumed orders
    </a>
    <a @class(['nav-link', 'active' => request()->routeIs('reports.movements')]) href="{{ route('reports.movements') }}">
        Movements
    </a>
</nav>
