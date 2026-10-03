<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>@yield('title') — {{ config('app.name') }}</title>
    {{-- Halaman galat tetap tampil walau aset belum di-build (mesin baru / mode pemeliharaan). --}}
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/scss/app.scss', 'resources/js/app.js'])
    @endif
</head>
<body class="bg-light">
    <a class="skip-link" href="#konten-utama">Lewati ke konten utama</a>

    <main id="konten-utama" tabindex="-1" class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6 text-center">
                <p class="display-4 fw-bold text-primary mb-2" aria-hidden="true">@yield('code')</p>
                <h1 class="h2 mb-3">@yield('heading')</h1>
                <p class="lead">@yield('message')</p>
                @yield('extra')
                <div class="d-flex flex-wrap justify-content-center gap-2 mt-4">
                    @yield('actions')
                </div>
                <p class="small text-body-secondary mt-5 mb-0">{{ config('app.name') }}</p>
            </div>
        </div>
    </main>
</body>
</html>
