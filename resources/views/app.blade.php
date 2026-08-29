@routes
@vite(['resources/js/app.js'])

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <title>{{ config('app.name', 'An Nur Smart System') }}</title>

    {{-- Favicon --}}
    <link rel="icon" type="image/x-icon" href="/favicon.ico" />

    {{-- Vite: CSS + JS --}}
    @vite(['resources/js/app.js'])

    {{-- Inertia Head (untuk title per halaman) --}}
    @inertiaHead
</head>
<body class="antialiased">
    @inertia
</body>
</html>