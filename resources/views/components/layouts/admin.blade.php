@props(['title'])
@php
    $user = auth()->user();
    $siteName = $site->string('site.name', config('app.name'));
    $logoUrl = $site->logoUrl();
    $logoFallback = asset('images/placeholders/logo.svg');

    // Sidebar diratakan: judul grup menjadi baris tersendiri agar markup tautan tidak digandakan.
    $rows = [];
    foreach (\App\Support\NavigationMenu::items('admin', $user) as $entry) {
        if (isset($entry['group'])) {
            $rows[] = ['heading' => $entry['group']];
            foreach ($entry['items'] as $item) {
                $rows[] = $item;
            }
        } else {
            $rows[] = $entry;
        }
    }
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>{{ $title }} — {{ $siteName }}</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    @vite(['resources/js/admin.js'])
    @stack('head')
</head>
<body class="bg-light">
    <a class="skip-link" href="#konten-utama">Lewati ke konten utama</a>

    <div class="d-lg-flex min-vh-100">
        {{-- Sidebar: tetap di ≥ lg, offcanvas kiri di bawahnya --}}
        <aside class="offcanvas-lg offcanvas-start admin-sidebar" tabindex="-1" id="admin-sidebar" aria-labelledby="admin-sidebar-judul">
            <div class="offcanvas-header">
                <div class="d-flex align-items-center gap-2 text-white fw-bold" id="admin-sidebar-judul">
                    <img src="{{ $logoUrl }}" data-fallback="{{ $logoFallback }}" alt="" width="32" height="32">
                    <span>UNPAM Admin</span>
                </div>
                <button type="button" class="btn-close btn-close-white d-lg-none" data-bs-dismiss="offcanvas" data-bs-target="#admin-sidebar" aria-label="Tutup menu admin"></button>
            </div>
            <div class="offcanvas-body d-block p-2 overflow-auto">
                <nav aria-label="Menu admin">
                    <ul class="nav flex-column gap-1">
                        @foreach ($rows as $row)
                            @if (isset($row['heading']))
                                <li class="px-3 pt-3 pb-1 small text-uppercase fw-semibold text-white">{{ $row['heading'] }}</li>
                            @else
                                <li class="nav-item">
                                    <a @class([
                                        'nav-link d-flex align-items-center gap-2 rounded',
                                        'text-white' => ! $row['active'],
                                        'active bg-light text-primary fw-semibold' => $row['active'],
                                    ]) href="{{ $row['url'] }}" @if ($row['active']) aria-current="page" @endif>
                                        @if (! empty($row['icon']))
                                            <i class="bi {{ $row['icon'] }}" aria-hidden="true"></i>
                                        @endif
                                        <span>{{ $row['label'] }}</span>
                                    </a>
                                </li>
                            @endif
                        @endforeach
                    </ul>
                </nav>
            </div>
        </aside>

        <div class="flex-grow-1 d-flex flex-column">
            {{-- Topbar --}}
            <header class="bg-white border-bottom shadow-sm sticky-top">
                <div class="d-flex align-items-center gap-3 px-3 px-lg-4 py-2">
                    <button class="btn btn-outline-primary d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#admin-sidebar" aria-controls="admin-sidebar" aria-label="Buka menu admin">
                        <i class="bi bi-list" aria-hidden="true"></i>
                    </button>

                    <div class="flex-grow-1">{{ $breadcrumb ?? '' }}</div>

                    <a class="btn btn-link text-decoration-none d-none d-sm-inline-flex align-items-center gap-1" href="{{ route('home') }}" target="_blank" rel="noopener">
                        <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i> Lihat Situs
                    </a>

                    <div class="dropdown">
                        <button class="btn btn-outline-primary dropdown-toggle d-inline-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle" aria-hidden="true"></i>
                            <span class="d-none d-sm-inline">{{ $user->name }}</span>
                            <span class="visually-hidden d-sm-none">Menu pengguna {{ $user->name }}</span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li>
                                <div class="dropdown-item-text">
                                    <span class="d-block fw-semibold">{{ $user->name }}</span>
                                    <span class="d-block small text-body-secondary">{{ $user->roleLabel }}</span>
                                </div>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form method="POST" action="{{ route('admin.logout') }}">
                                    @csrf
                                    <button type="submit" class="dropdown-item">
                                        <i class="bi bi-box-arrow-right" aria-hidden="true"></i> Keluar
                                    </button>
                                </form>
                            </li>
                        </ul>
                    </div>
                </div>
            </header>

            <main id="konten-utama" tabindex="-1" class="flex-grow-1 px-3 px-lg-4 py-4">
                @isset($actions)
                    <x-page-header :title="$title">
                        <x-slot:actions>{{ $actions }}</x-slot:actions>
                    </x-page-header>
                @else
                    <x-page-header :title="$title" />
                @endisset

                <x-flash-alert />

                {{ $slot }}
            </main>

            <footer class="border-top bg-white px-3 px-lg-4 py-3 small text-body-secondary">
                {{ $siteName }} Admin — prototipe
            </footer>
        </div>
    </div>
</body>
</html>
