<x-layouts.guest title="Administrator login">
    <section class="card border-0 shadow-sm">
        <div class="card-body p-4 p-md-5">
            <p class="text-uppercase small fw-semibold text-primary mb-2">Warehouse Inventory Engine</p>
            <h1 class="h3 mb-2">Administrator login</h1>
            <p class="text-body-secondary mb-4">Sign in to manage inventory and fulfillment operations.</p>

            <x-ui.messages />

            <form method="post" action="{{ route('login.store') }}" class="d-grid gap-3">
                @csrf

                <div>
                    <label class="form-label" for="email">Email</label>
                    <input
                        @class(['form-control', 'is-invalid' => $errors->has('email')])
                        id="email"
                        name="email"
                        type="email"
                        value="{{ old('email') }}"
                        required
                        autofocus
                        autocomplete="username"
                    >
                </div>

                <div>
                    <label class="form-label" for="password">Password</label>
                    <input
                        @class(['form-control', 'is-invalid' => $errors->has('password')])
                        id="password"
                        name="password"
                        type="password"
                        required
                        autocomplete="current-password"
                    >
                </div>

                <button class="btn btn-primary" type="submit">Log in</button>
            </form>
        </div>
    </section>
</x-layouts.guest>
