@inject('site', 'App\Services\Settings\SettingsService')
@php
    $cover = $room->firstMedia('cover');
    $coverAlt = $cover?->alt_text ?: $room->name;
    $amenities = collect($room->amenities ?? [])->filter(fn ($item): bool => is_string($item) && trim($item) !== '')->values();
    $whatsappUrl = $site->whatsappUrl();
    $contactUrl = Route::has('contact') ? route('contact') : null;
@endphp
<x-layouts.public :title="$room->seo_title" :meta-description="$metaDescription">
    <x-slot:breadcrumb>
        <x-breadcrumb :items="[['label' => 'Kamar Rawat Inap', 'url' => route('rooms.index')], ['label' => $room->name]]" />
    </x-slot:breadcrumb>

    @push('head')
        <x-open-graph :title="$room->seo_title" :description="$metaDescription" :image="$cover?->url ? url($cover->url) : null" :canonical="route('rooms.show', $room)" />
    @endpush

    <x-page-header :title="$room->name" :subtitle="$room->short_description" />

    @include('public.rooms._notice')

    <div class="row g-4 g-lg-5">
        <div class="col-lg-7">
            <x-image type="room" :media="$cover" :alt="$coverAlt" ratio="4x3" :lazy="false" />

            @if ($gallery->isNotEmpty())
                <section class="mt-4" aria-labelledby="galeri-judul">
                    <h2 id="galeri-judul" class="h4 mb-3">Galeri</h2>
                    <div class="gallery-grid">
                        @foreach ($gallery as $index => $photo)
                            <button type="button" class="gallery-thumb" data-bs-toggle="modal" data-bs-target="#galeri-modal" data-gallery-index="{{ $index }}" aria-label="Perbesar foto {{ $index + 1 }} dari {{ $gallery->count() }}: {{ $photo->alt_text }}">
                                <x-image type="room" :media="$photo" decorative variant="thumb" ratio="4x3" />
                            </button>
                        @endforeach
                    </div>
                </section>

                <div class="modal fade" id="galeri-modal" tabindex="-1" role="dialog" aria-labelledby="galeri-modal-judul" aria-hidden="true" data-gallery-modal>
                    <div class="modal-dialog modal-lg modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header">
                                <div class="modal-title h5" id="galeri-modal-judul">Galeri {{ $room->name }}</div>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup galeri"></button>
                            </div>
                            <div class="modal-body p-0">
                                <div id="galeri-carousel" class="carousel slide" tabindex="-1" data-bs-ride="false" aria-label="Foto galeri">
                                    <div class="carousel-inner">
                                        @foreach ($gallery as $index => $photo)
                                            <div @class(['carousel-item', 'active' => $index === 0])>
                                                <x-image type="room" :media="$photo" :lazy="false" class="d-block w-100" />
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

            @if (filled($room->description))
                <div class="mt-4">
                    <div class="rich-text room-body">{!! $room->description !!}</div>
                </div>
            @endif

            @if ($amenities->isNotEmpty())
                <section class="mt-5" aria-labelledby="fasilitas-kamar-judul">
                    <h2 id="fasilitas-kamar-judul" class="h4 mb-3">Fasilitas Kamar</h2>
                    <ul class="amenity-list">
                        @foreach ($amenities as $amenity)
                            <li><i class="bi bi-check2" aria-hidden="true"></i> {{ $amenity }}</li>
                        @endforeach
                    </ul>
                </section>
            @endif

            <p class="mt-5 mb-0">
                <a class="btn btn-outline-primary" href="{{ route('rooms.index') }}"><i class="bi bi-arrow-left" aria-hidden="true"></i> Kembali ke daftar kamar</a>
            </p>
        </div>

        <aside class="col-lg-5" aria-label="Ringkasan kamar">
            <div class="card room-summary-panel">
                <div class="card-body">
                    <h2 class="h5 mb-3">Ringkasan</h2>
                    <dl class="mb-3">
                        <dt>Kapasitas</dt>
                        <dd>{{ $room->bed_capacity }} tempat tidur</dd>
                        <dt>Tersedia</dt>
                        <dd class="mb-0">{{ $room->occupancy_label }} @include('public.rooms._availability-badge', ['room' => $room])</dd>
                    </dl>

                    <p class="mb-1">Tarif contoh: {{ $room->sample_rate_label }}</p>
                    @if (filled($room->rate_note))
                        <p class="small text-body-secondary mb-2">{{ $room->rate_note }}</p>
                    @endif

                    <p class="small text-body-secondary mt-3 mb-2">
                        @if ($room->availability_updated_at !== null)
                            <time datetime="{{ $room->availability_updated_at->toIso8601String() }}">{{ $room->availability_updated_label }}</time>
                        @else
                            <span>{{ $room->availability_updated_label }}</span>
                        @endif
                    </p>
                    @if ($room->is_availability_stale)
                        <p class="small text-warning-emphasis mb-3">Data mungkin tidak terkini. Hubungi rumah sakit untuk memastikan ketersediaan.</p>
                    @endif

                    @if ($contactUrl !== null)
                        <a class="btn btn-primary w-100 mb-2" href="{{ $contactUrl }}">Tanya Ketersediaan</a>
                    @endif
                    @if ($whatsappUrl !== null)
                        <a class="btn btn-outline-primary w-100" href="{{ $whatsappUrl }}" target="_blank" rel="noopener noreferrer"><i class="bi bi-whatsapp" aria-hidden="true"></i> Chat via WhatsApp (membuka WhatsApp)</a>
                    @endif
                </div>
            </div>
        </aside>
    </div>
</x-layouts.public>
