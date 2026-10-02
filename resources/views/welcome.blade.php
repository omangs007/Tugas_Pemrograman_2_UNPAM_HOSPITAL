<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }}</title>
    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
</head>
<body class="bg-light">
    <main class="container py-5">
        <h1 class="h3 text-primary">
            <i class="bi bi-hospital" aria-hidden="true"></i>
            {{ config('app.name') }}
        </h1>
        <p class="mb-0">Skeleton aplikasi berjalan. Halaman situs dibangun mulai fase 1 (lihat blueprint bagian 14).</p>
    </main>
</body>
</html>
