@routes
@vite(['resources/js/app.js'])

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <title>{{ config('app.name', 'An Nur Smart System') }}</title>

    {{-- Favicon (logo An-Nur, sinkron dgn PWA guru) --}}
    <link rel="icon" type="image/png" href="/guru-icon-192.png" />
    <link rel="apple-touch-icon" href="/guru-icon-192.png" />

    {{-- Vite: CSS + JS --}}
    @vite(['resources/js/app.js'])

    {{-- Inertia Head (untuk title per halaman) --}}
    @inertiaHead
</head>
<body class="antialiased">
    @inertia
</body>
</html>