@php
    $cover = $facility->firstMedia('cover');
    $coverAlt = $cover?->alt_text ?: $facility->name;
@endphp
<x-layouts.public :title="$facility->seo_title" :meta-description="$metaDescription">
    <x-slot:breadcrumb>
        <x-breadcrumb :items="[['label' => 'Fasilitas', 'url' => route('facilities.index')], ['label' => $facility->name]]" />
    </x-slot:breadcrumb>

    @push('head')
        <x-open-graph :title="$facility->seo_title" :description="$metaDescription" :image="$cover?->url ? url($cover->url) : null" :canonical="route('facilities.show', $facility)" />
    @endpush

    <x-page-header :title="$facility->name" :subtitle="$facility->category->label()" />

    <div class="row">
        <div class="col-lg-9">
            <x-image type="facility" :media="$cover" :alt="$coverAlt" ratio="16x9" :lazy="false" />

            @if (filled($facility->short_description))
                <p class="lead mt-4">{{ $facility->short_description }}</p>
            @endif

            @if (filled($facility->description))
                <div class="rich-text facility-body">{!! $facility->description !!}</div>
            @endif

            @if ($gallery->isNotEmpty())
                <section class="mt-5" aria-labelledby="galeri-judul">
                    <h2 id="galeri-judul" class="h4 mb-3">Galeri</h2>
                    <div class="gallery-grid">
                        @foreach ($gallery as $index => $photo)
                            <button type="button" class="gallery-thumb" data-bs-toggle="modal" data-bs-target="#galeri-modal" data-gallery-index="{{ $index }}" aria-label="Perbesar foto {{ $index + 1 }} dari {{ $gallery->count() }}: {{ $photo->alt_text }}">
                                <x-image type="facility" :media="$photo" decorative variant="thumb" ratio="4x3" />
                            </button>
                        @endforeach
                    </div>
                </section>

                <div class="modal fade" id="galeri-modal" tabindex="-1" role="dialog" aria-labelledby="galeri-modal-judul" aria-hidden="true" data-gallery-modal>
                    <div class="modal-dialog modal-lg modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header">
                                <div class="modal-title h5" id="galeri-modal-judul">Galeri {{ $facility->name }}</div>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup galeri"></button>
                            </div>
                            <div class="modal-body p-0">
                                <div id="galeri-carousel" class="carousel slide" tabindex="-1" data-bs-ride="false" aria-label="Foto galeri">
                                    <div class="carousel-inner">
                                        @foreach ($gallery as $index => $photo)
                                            <div @class(['carousel-item', 'active' => $index === 0])>
                                                <x-image type="facility" :media="$photo" :lazy="false" class="d-block w-100" />
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

            <p class="d-flex flex-wrap gap-2 mt-5 mb-0">
                <a class="btn btn-outline-primary" href="{{ route('facilities.index') }}"><i class="bi bi-arrow-left" aria-hidden="true"></i> Kembali ke daftar fasilitas</a>
                @if (Route::has('rooms.index'))
                    <a class="btn btn-outline-primary" href="{{ route('rooms.index') }}">Lihat Kamar Rawat Inap</a>
                @endif
            </p>
        </div>
    </div>
</x-layouts.public>
