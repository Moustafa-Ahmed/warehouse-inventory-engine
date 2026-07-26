@php($editing = $warehouse !== null)

<x-layouts.app :title="$editing ? 'Edit warehouse' : 'Add warehouse'">
    <div class="row justify-content-center">
        <div class="col-12 col-lg-8 col-xl-6">
            <section class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <a class="small" href="{{ route('warehouses.index') }}">← Warehouse catalog</a>
                    <h1 class="h3 mt-2">{{ $editing ? 'Edit warehouse' : 'Add warehouse' }}</h1>
                    <p class="text-body-secondary">
                        Inactive warehouses remain in history but cannot receive new operational work.
                    </p>

                    <form
                        method="post"
                        action="{{ $editing ? route('warehouses.update', $warehouse) : route('warehouses.store') }}"
                        class="d-grid gap-3"
                    >
                        @csrf
                        @if ($editing) @method('patch') @endif

                        <div>
                            <label class="form-label" for="code">Warehouse code</label>
                            <input
                                @class(['form-control', 'is-invalid' => $errors->has('code')])
                                id="code"
                                name="code"
                                type="text"
                                maxlength="255"
                                value="{{ old('code', $warehouse?->code) }}"
                                required
                            >
                            @error('code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div>
                            <label class="form-label" for="name">Name</label>
                            <input
                                @class(['form-control', 'is-invalid' => $errors->has('name')])
                                id="name"
                                name="name"
                                type="text"
                                maxlength="255"
                                value="{{ old('name', $warehouse?->name) }}"
                                required
                            >
                            @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="form-check">
                            <input
                                class="form-check-input"
                                id="is_active"
                                name="is_active"
                                type="checkbox"
                                value="1"
                                @checked((bool) old('is_active', $warehouse?->is_active ?? true))
                            >
                            <label class="form-check-label" for="is_active">Active for new workflows</label>
                        </div>

                        <div>
                            <button class="btn btn-primary" type="submit">
                                {{ $editing ? 'Save warehouse' : 'Create warehouse' }}
                            </button>
                        </div>
                    </form>
                </div>
            </section>
        </div>
    </div>
</x-layouts.app>
