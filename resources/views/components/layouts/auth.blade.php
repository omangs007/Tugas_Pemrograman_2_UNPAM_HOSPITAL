@props(['title', 'subtitle' => null])
@php
    $siteName = $site->string('site.name', config('app.name'));
    $logoUrl = $site->logoUrl();
    $logoFallback = asset('images/placeholders/logo.svg');
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>{{ $title }} — {{ $siteName }}</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
</head>
<body class="bg-light">
    <a class="skip-link" href="#konten-utama">Lewati ke konten utama</a>

    <div class="container py-4 py-md-5">
        <div class="auth-card mx-auto">
            <div class="text-center mb-4">
                <a class="d-inline-flex align-items-center gap-2 text-decoration-none" href="{{ route('home') }}">
                    <img src="{{ $logoUrl }}" data-fallback="{{ $logoFallback }}" alt="" width="48" height="48">
                    <span class="h5 mb-0 fw-bold text-primary">{{ $siteName }}</span>
                </a>
            </div>

            <main id="konten-utama" tabindex="-1" class="card shadow-sm border-0">
                <div class="card-body p-4">
                    <h1 class="h3 mb-1">{{ $title }}</h1>
                    @if (filled($subtitle))
                        <p class="text-body-secondary">{{ $subtitle }}</p>
                    @endif

                    <x-flash-alert />
                    <x-form.errors-summary />

                    {{ $slot }}
                </div>
            </main>

            @isset($footer)
                <div class="mt-3 text-center small">{{ $footer }}</div>
            @endisset

            <p class="mt-3 text-center small mb-0">
                <a href="{{ route('home') }}"><i class="bi bi-arrow-left" aria-hidden="true"></i> Kembali ke beranda</a>
            </p>
        </div>
    </div>
</body>
</html>
