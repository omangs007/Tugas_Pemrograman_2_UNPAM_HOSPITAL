@inject('site', 'App\Services\Settings\SettingsService')
@php
    $cover = $clinic->firstMedia('cover');
    $gallery = $clinic->mediaIn('gallery')->values();
    $coverAlt = $cover?->alt_text ?: 'Foto '.$clinic->name;
    $phone = $site->string('contact.phone') ?: '(021) 0000-0000';
    $registerUrl = $clinic->is_bookable && Route::has('patient.appointments.create')
        ? route('patient.appointments.create', ['poli' => $clinic->slug])
        : null;
    $scheduleUrl = $clinic->is_bookable && Route::has('schedules.index')
        ? route('schedules.index', ['poli' => $clinic->slug])
        : null;
@endphp
<x-layouts.public :title="$clinic->seo_title" :meta-description="$metaDescription">
    <x-slot:breadcrumb>
        <x-breadcrumb :items="[['label' => 'Layanan', 'url' => route('clinics.index')], ['label' => $clinic->name]]" />
    </x-slot:breadcrumb>

    @push('head')
        <meta property="og:type" content="website">
        <meta property="og:title" content="{{ $clinic->seo_title }}">
        <meta property="og:description" content="{{ $metaDescription }}">
        @if ($cover?->url)
            <meta property="og:image" content="{{ url($cover->url) }}">
        @endif
    @endpush

    <div class="row g-4 g-lg-5">
        <div class="col-lg-8">
            <x-image type="clinic" :media="$cover" :alt="$coverAlt" ratio="16x9" :lazy="false" />

            <header class="mt-4">
                <h1 class="h2 mb-2">{{ $clinic->name }}</h1>
                <p class="text-body-secondary mb-2"><i class="bi bi-geo-alt" aria-hidden="true"></i> {{ $clinic->location }}</p>
                @if ($clinic->is_bookable)
                    <p class="mb-2"><span class="badge text-bg-primary">Kode antrean: {{ $clinic->code }}</span></p>
                @endif
            </header>

            <p class="lead">{{ $clinic->short_description }}</p>

            @if (filled($clinic->description))
                <div class="rich-text clinic-description">{!! $clinic->description !!}</div>
            @endif

            @if ($gallery->isNotEmpty())
                <section class="mt-5" aria-labelledby="galeri-judul">
                    <h2 id="galeri-judul" class="h4 mb-3">Galeri</h2>
                    <div class="gallery-grid">
                        @foreach ($gallery as $index => $photo)
                            <button type="button" class="gallery-thumb" data-bs-toggle="modal" data-bs-target="#galeri-modal" data-gallery-index="{{ $index }}" aria-label="Perbesar foto {{ $index + 1 }} dari {{ $gallery->count() }}: {{ $photo->alt_text }}">
                                <x-image type="clinic" :media="$photo" decorative variant="thumb" ratio="4x3" />
                            </button>
                        @endforeach
                    </div>
                </section>

                <div class="modal fade" id="galeri-modal" tabindex="-1" role="dialog" aria-labelledby="galeri-modal-judul" aria-hidden="true" data-gallery-modal>
                    <div class="modal-dialog modal-lg modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header">
                                <div class="modal-title h5" id="galeri-modal-judul">Galeri {{ $clinic->name }}</div>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup galeri"></button>
                            </div>
                            <div class="modal-body p-0">
                                <div id="galeri-carousel" class="carousel slide" tabindex="-1" data-bs-ride="false" aria-label="Foto galeri">
                                    <div class="carousel-inner">
                                        @foreach ($gallery as $index => $photo)
                                            <div @class(['carousel-item', 'active' => $index === 0])>
                                                <x-image type="clinic" :media="$photo" :lazy="false" class="d-block w-100" />
                                            </div>
                                        @endforeach
                                    </div>
                                    @if ($gallery->count() > 1)
                                        <button class="carousel-control-prev" type="button" data-bs-target="#galeri-carousel" data-bs-slide="prev">
                                            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                                            <span class="visually-hidden">Foto sebelumnya</span>
                                        </button>
                                        <button class="carousel-control-next" type="button" data-bs-target="#galeri-carousel" data-bs-slide="next">
                                            <span class="carousel-control-next-icon" aria-hidden="true"></span>
                                            <span class="visually-hidden">Foto berikutnya</span>
                                        </button>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            @if ($doctors->isNotEmpty() || $clinic->is_bookable)
                <section class="mt-5" aria-labelledby="dokter-judul">
                    <h2 id="dokter-judul" class="h4 mb-3">Dokter di poliklinik ini</h2>
                    @if ($doctors->isEmpty())
                        <x-empty-state title="Belum ada dokter terjadwal" icon="bi-person-badge" />
                    @else
                        <div class="row g-3">
                            @foreach ($doctors as $doctor)
                                <div class="col-12 col-sm-6">
                                    <x-doctor-card :doctor="$doctor" :heading-level="3" />
                                </div>
                            @endforeach
                        </div>
                    @endif
                </section>
            @endif
        </div>

        <aside class="col-lg-4" aria-label="Informasi layanan">
            <div class="card clinic-side-panel">
                <div class="card-body">
                    <h2 class="h5 mb-3">Informasi layanan</h2>
                    <dl class="mb-3">
                        <dt>Lokasi</dt>
                        <dd>{{ $clinic->location }}</dd>
                        <dt>Jenis layanan</dt>
                        <dd>{{ $clinic->type->label() }}</dd>
                        @if ($clinic->is_bookable)
                            <dt>Pendaftaran</dt>
                            <dd class="mb-0">Dapat didaftar online</dd>
                        @endif
                    </dl>

                    @if ($clinic->is_bookable)
                        @if ($registerUrl)
                            <a class="btn btn-primary w-100 mb-2" href="{{ $registerUrl }}">Daftar Kunjungan</a>
                        @endif
                        @if ($scheduleUrl)
                            <a class="btn btn-outline-primary w-100" href="{{ $scheduleUrl }}">Jadwal Dokter</a>
                        @endif
                    @else
                        <p class="mb-2">Layanan ini tidak dapat didaftar online. Hubungi kami di {{ $phone }}.</p>
                        @if (Route::has('contact'))
                            <a class="btn btn-outline-primary w-100" href="{{ route('contact') }}">Hubungi Kami</a>
                        @endif
                    @endif
                </div>
            </div>
        </aside>
    </div>
</x-layouts.public>
