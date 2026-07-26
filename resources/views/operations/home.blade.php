<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Warehouse operations</title>
</head>
<body>
    <main>
        <h1>Warehouse operations</h1>
        <p>Signed in as {{ auth()->user()->name }}.</p>

        <form method="post" action="{{ route('logout') }}">
            @csrf
            <button type="submit">Log out</button>
        </form>
    </main>
</body>
</html>
