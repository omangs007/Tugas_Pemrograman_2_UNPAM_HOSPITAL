<x-layouts.public :title="$page->seo_title" :meta-description="$page->seo_description">
    <x-slot:breadcrumb>
        <x-breadcrumb :items="[['label' => 'BPJS dan Asuransi']]" />
    </x-slot:breadcrumb>

    @push('head')
        <x-open-graph :title="$page->seo_title" :description="$page->seo_description" :canonical="route('insurance')" />
    @endpush

    <x-page-header :title="$page->title" :subtitle="$page->subtitle" />

    @if (filled($page->body))
        @include('public.partials._page-body', ['page' => $page])
    @else
        <x-empty-state title="Konten sedang disiapkan" message="Isi halaman ini sedang disiapkan oleh admin." icon="bi-file-earmark-text" />
    @endif

    <div class="alert alert-warning mt-4" role="note">
        Informasi ini contoh dan harus diverifikasi serta disesuaikan oleh admin dengan ketentuan resmi sebelum digunakan. Rumah sakit ini tidak menyatakan kerja sama resmi dengan pihak mana pun.
    </div>

    @if ($partners->isNotEmpty())
        <section class="mt-5" aria-labelledby="mitra-judul">
            <h2 class="h3" id="mitra-judul">Mitra Asuransi dan Perusahaan (Contoh)</h2>
            <p class="text-body-secondary">Data mitra di bawah ini adalah contoh dan bukan klaim kerja sama.</p>

            <div class="row g-4 row-cols-1 row-cols-sm-2 row-cols-lg-3">
                @foreach ($partners as $partner)
                    @php
                        $logo = $partner->firstMedia('logo');
                        $website = (string) $partner->website_url;
                    @endphp
                    <div class="col">
                        <article class="card h-100">
                            <div class="card-body d-flex flex-column">
                                <div class="bg-light rounded p-3 mb-3 text-center">
                                    <x-image type="logo" :media="$logo" :alt="$logo?->alt_text ?: 'Logo '.$partner->name" variant="thumb" class="mw-100" />
                                </div>
                                <h3 class="h5 card-title mb-2">{{ $partner->name }}</h3>
                                <p class="mb-2">
                                    <span class="badge text-bg-warning">Contoh</span>
                                    <span class="badge text-bg-light border">{{ $partner->type_label }}</span>
                                </p>
                                @if (filled($partner->description))
                                    <p class="card-text">{{ $partner->description }}</p>
                                @endif
                                @if (str_starts_with($website, 'https://'))
                                    <p class="mt-auto mb-0">
                                        <a href="{{ $website }}" target="_blank" rel="noopener noreferrer">Kunjungi situs<span class="visually-hidden"> (dibuka di tab baru)</span></a>
                                    </p>
                                @endif
                            </div>
                        </article>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    <nav class="d-flex flex-wrap gap-2 mt-5" aria-label="Tautan terkait">
        <a class="btn btn-outline-primary" href="{{ route('registration-guide') }}">Panduan Pendaftaran</a>
        @if (Route::has('contact'))
            <a class="btn btn-outline-primary" href="{{ route('contact') }}">Kontak</a>
        @endif
    </nav>
</x-layouts.public>
