@if (session('status'))
    <div class="alert alert-success" role="status">
        {{ session('status') }}
    </div>
@endif

@if ($errors->any())
    <div
        @class([
            'alert',
            'alert-warning' => session('message_type') === 'conflict',
            'alert-danger' => session('message_type') !== 'conflict',
        ])
        role="alert"
        data-message-type="{{ session('message_type', 'validation') }}"
    >
        <h2 class="h6">The request could not be completed</h2>
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
