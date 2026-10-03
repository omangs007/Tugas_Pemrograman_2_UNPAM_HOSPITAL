@props(['title', 'metaDescription' => null, 'noindex' => false])
@php
    $siteName = $site->string('site.name', config('app.name'));
    $tagline = $site->string('site.tagline');
    $description = filled($metaDescription)
        ? $metaDescription
        : $site->string('seo.default_meta_description', 'Informasi layanan, dokter, jadwal, dan pendaftaran kunjungan rawat jalan di '.$siteName.'.');
    $account = auth()->user();
    $isStaff = $account instanceof \App\Models\User && $account->isStaff();
    $isPatient = $account instanceof \App\Models\User && $account->isPatient();
    $menu = \App\Support\NavigationMenu::items('public', $account);
    $quickLinks = \App\Support\NavigationMenu::items('footer', $account);

    $logoUrl = $site->logoUrl();
    $logoFallback = asset('images/placeholders/logo.svg');
    $address = $site->string('contact.address');
    $phone = $site->string('contact.phone');
    $emergency = $site->string('contact.emergency_phone');
    $email = $site->string('contact.email');
    $hours = $site->string('contact.operational_hours');
    $whatsappUrl = $site->whatsappUrl();
    $telHref = fn (string $number): string => 'tel:'.preg_replace('/[^0-9+]/', '', $number);

    // Tombol aksi header. Tautan ke route fase berikutnya hanya tampil bila route-nya sudah ada (F1-R2).
    // 'header' = true ⇒ juga ditampilkan di bilah ringkas mobile.
    $actions = [];
    if ($isStaff) {
        if (Route::has('admin.dashboard')) {
            $actions[] = ['label' => 'Buka Panel Admin', 'url' => route('admin.dashboard'), 'class' => 'btn btn-primary', 'header' => true];
        }
    } else {
        if (Route::has('patient.appointments.create')) {
            $actions[] = ['label' => 'Daftar Kunjungan', 'url' => route('patient.appointments.create'), 'class' => 'btn btn-primary', 'header' => false];
        }
        if ($isPatient && Route::has('patient.dashboard')) {
            $actions[] = ['label' => 'Akun Saya', 'url' => route('patient.dashboard'), 'class' => 'btn btn-outline-primary', 'header' => true];
        } elseif (! $isPatient && Route::has('login')) {
            $actions[] = ['label' => 'Masuk', 'url' => route('login'), 'class' => 'btn btn-outline-primary', 'header' => true];
        }
    }
    $logoutUrl = null;
    if ($isStaff && Route::has('admin.logout')) {
        $logoutUrl = route('admin.logout');
    } elseif ($isPatient && Route::has('logout')) {
        $logoutUrl = route('logout');
    }
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} — {{ $siteName }}</title>
    <meta name="description" content="{{ $description }}">
    @if ($noindex)
        <meta name="robots" content="noindex,nofollow">
    @endif
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="bg-white">
    <a class="skip-link" href="#konten-utama">Lewati ke konten utama</a>

    <header>
        {{-- Baris atas (desktop): identitas, nomor IGD, tombol aksi --}}
        <div class="bg-white border-bottom d-none d-lg-block">
            <div class="container py-3 d-flex align-items-center justify-content-between gap-3">
                <a class="d-inline-flex align-items-center gap-3 text-decoration-none" href="{{ route('home') }}">
                    <img src="{{ $logoUrl }}" data-fallback="{{ $logoFallback }}" alt="" width="48" height="48">
                    <span>
                        <span class="d-block h5 mb-0 fw-bold text-primary">{{ $siteName }}</span>
                        @if ($tagline !== '')
                            <span class="d-block small text-body-secondary">{{ $tagline }}</span>
                        @endif
                    </span>
                </a>
                <div class="d-flex align-items-center gap-3">
                    @if ($emergency !== '')
                        <a class="text-decoration-none fw-semibold" href="{{ $telHref($emergency) }}"><i class="bi bi-telephone-fill" aria-hidden="true"></i> IGD: {{ $emergency }}</a>
                    @endif
                    @foreach ($actions as $action)
                        <a class="{{ $action['class'] }}" href="{{ $action['url'] }}">{{ $action['label'] }}</a>
                    @endforeach
                    @if ($logoutUrl)
                        <form method="POST" action="{{ $logoutUrl }}">
                            @csrf
                            <button type="submit" class="btn btn-outline-secondary">Keluar</button>
                        </form>
                    @endif
                </div>
            </div>
        </div>

        {{-- Bar menu: di bawah lg menjadi offcanvas kanan --}}
        <nav class="navbar navbar-expand-lg navbar-dark bg-primary sticky-top" aria-label="Menu utama">
            <div class="container">
                <button class="navbar-toggler" type="button" data-bs-toggle="offcanvas" data-bs-target="#menu-utama" aria-controls="menu-utama" aria-label="Buka menu">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <a class="navbar-brand d-lg-none d-inline-flex align-items-center gap-2 me-auto ms-2" href="{{ route('home') }}">
                    <img src="{{ $logoUrl }}" data-fallback="{{ $logoFallback }}" alt="" width="32" height="32">
                    <span>{{ $siteName }}</span>
                </a>
                <div class="d-lg-none d-flex gap-2">
                    @foreach ($actions as $action)
                        @if ($action['header'])
                            <a class="btn btn-outline-light btn-sm" href="{{ $action['url'] }}">{{ $action['label'] }}</a>
                        @endif
                    @endforeach
                </div>

                <div class="offcanvas offcanvas-end" tabindex="-1" id="menu-utama" aria-labelledby="menu-utama-judul">
                    <div class="offcanvas-header">
                        <div class="offcanvas-title h5" id="menu-utama-judul">Menu</div>
                        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Tutup menu"></button>
                    </div>
                    <div class="offcanvas-body">
                        <ul class="navbar-nav me-auto mb-3 mb-lg-0">
                            <li class="nav-item d-lg-none">
                                <a class="nav-link" href="{{ route('home') }}">Beranda</a>
                            </li>
                            @foreach ($menu as $entry)
                                @if (isset($entry['children']))
                                    <li class="nav-item dropdown">
                                        <button @class(['nav-link dropdown-toggle border-0 bg-transparent', 'active fw-bold' => $entry['active']]) type="button" data-bs-toggle="dropdown" aria-expanded="false">{{ $entry['label'] }}</button>
                                        <ul class="dropdown-menu">
                                            @foreach ($entry['children'] as $child)
                                                <li>
                                                    <a @class(['dropdown-item', 'active' => $child['active']]) href="{{ $child['url'] }}" @if ($child['active']) aria-current="page" @endif>{{ $child['label'] }}</a>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </li>
                                @else
                                    <li class="nav-item">
                                        <a @class(['nav-link', 'active fw-bold' => $entry['active']]) href="{{ $entry['url'] }}" @if ($entry['active']) aria-current="page" @endif>{{ $entry['label'] }}</a>
                                    </li>
                                @endif
                            @endforeach
                        </ul>

                        <div class="d-lg-none d-grid gap-2">
                            @foreach ($actions as $action)
                                <a class="{{ $action['class'] }}" href="{{ $action['url'] }}">{{ $action['label'] }}</a>
                            @endforeach
                            @if ($logoutUrl)
                                <form method="POST" action="{{ $logoutUrl }}" class="d-grid">
                                    @csrf
                                    <button type="submit" class="btn btn-outline-secondary">Keluar</button>
                                </form>
                            @endif
                            @if ($emergency !== '')
                                <a class="text-decoration-none fw-semibold" href="{{ $telHref($emergency) }}"><i class="bi bi-telephone-fill" aria-hidden="true"></i> IGD: {{ $emergency }}</a>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </nav>
    </header>

    @if (isset($breadcrumb) && trim((string) $breadcrumb) !== '')
        <div class="container pt-3">{{ $breadcrumb }}</div>
    @endif

    <main id="konten-utama" tabindex="-1" class="container py-4 py-lg-5">
        <x-flash-alert />
        {{ $slot }}
    </main>

    <footer class="bg-light border-top mt-5">
        <div class="container py-5">
            <div class="row g-4">
                <div class="col-md-6 col-lg-4">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <img src="{{ $logoUrl }}" data-fallback="{{ $logoFallback }}" alt="" width="40" height="40" loading="lazy">
                        <span class="h5 mb-0 fw-bold text-primary">{{ $siteName }}</span>
                    </div>
                    @if ($tagline !== '')
                        <p class="mb-2">{{ $tagline }}</p>
                    @endif
                    <address class="mb-0">{{ $address !== '' ? $address : 'Alamat belum diisi.' }}</address>
                </div>

                <div class="col-md-6 col-lg-4">
                    <h2 class="h6 text-uppercase fw-bold">Kontak</h2>
                    <ul class="list-unstyled mb-0">
                        @if ($phone !== '')
                            <li>Telepon: <a href="{{ $telHref($phone) }}">{{ $phone }}</a></li>
                        @endif
                        @if ($emergency !== '')
                            <li>IGD: <a href="{{ $telHref($emergency) }}">{{ $emergency }}</a></li>
                        @endif
                        @if ($whatsappUrl)
                            <li>WhatsApp: <a href="{{ $whatsappUrl }}" target="_blank" rel="noopener noreferrer">Hubungi via WhatsApp (dibuka di aplikasi WhatsApp)</a></li>
                        @endif
                        @if ($email !== '')
                            <li>Email: <a href="mailto:{{ $email }}">{{ $email }}</a></li>
                        @endif
                        @if ($hours !== '')
                            <li>Jam operasional: {{ $hours }}</li>
                        @endif
                    </ul>
                </div>

                @if ($quickLinks !== [])
                    <div class="col-lg-4">
                        <h2 class="h6 text-uppercase fw-bold">Tautan cepat</h2>
                        <ul class="list-unstyled mb-0">
                            @foreach ($quickLinks as $link)
                                <li><a href="{{ $link['url'] }}">{{ $link['label'] }}</a></li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        </div>
        <div class="border-top">
            <div class="container py-3 small">
                <p class="mb-1">
                    © {{ now()->year }} {{ $siteName }}
                    @if (Route::has('privacy'))
                        · <a href="{{ route('privacy') }}">Kebijakan Privasi</a>
                    @endif
                </p>
                <p class="mb-0 text-body-secondary">Situs prototipe: nama dokter, foto, tarif, kontak, dan layanan adalah data contoh.</p>
            </div>
        </div>
    </footer>

    @stack('scripts')
</body>
</html>
