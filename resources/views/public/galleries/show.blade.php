@php
    $cover = $photos->first();
@endphp
<x-layouts.public :title="$title" :meta-description="$metaDescription">
    <x-slot:breadcrumb>
        <x-breadcrumb :items="[['label' => 'Galeri', 'url' => route('galleries.index')], ['label' => $gallery->title]]" />
    </x-slot:breadcrumb>

    @push('head')
        <x-open-graph :title="$title" :description="$metaDescription" :image="$cover?->url ? url($cover->url) : null" :canonical="route('galleries.show', $gallery)" />
    @endpush

    <x-page-header :title="$gallery->title" :subtitle="$gallery->description">
        <p class="text-body-secondary mt-1 mb-0"><i class="bi bi-images" aria-hidden="true"></i> {{ $photos->count() }} foto</p>
    </x-page-header>

    @if ($photos->isEmpty())
        <x-empty-state title="Album ini belum memiliki foto" message="Foto akan tampil di sini setelah ditambahkan." icon="bi-images" />
    @else
        <section aria-label="Foto album {{ $gallery->title }}">
            <div class="gallery-grid">
                @foreach ($photos as $index => $photo)
                    @php
                        $alt = $photo->alt_text ?: 'Foto '.($index + 1).' album '.$gallery->title;
                    @endphp
                    <button type="button" class="gallery-thumb" data-bs-toggle="modal" data-bs-target="#galeri-modal" data-gallery-index="{{ $index }}" aria-label="Perbesar foto {{ $index + 1 }} dari {{ $photos->count() }}: {{ $alt }}">
                        <x-image type="gallery" :media="$photo" decorative variant="thumb" ratio="4x3" />
                    </button>
                @endforeach
            </div>
        </section>

        <div class="modal fade" id="galeri-modal" tabindex="-1" role="dialog" aria-labelledby="galeri-modal-judul" aria-hidden="true" data-gallery-modal>
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <div class="modal-title h5" id="galeri-modal-judul">Album {{ $gallery->title }}</div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup galeri"></button>
                    </div>
                    <div class="modal-body p-0">
                        <div id="galeri-carousel" class="carousel slide" tabindex="-1" data-bs-ride="false" aria-label="Foto album">
                            <div class="carousel-inner">
                                @foreach ($photos as $index => $photo)
                                    <div @class(['carousel-item', 'active' => $index === 0])>
                                        <x-image type="gallery" :media="$photo" :alt="$photo->alt_text ?: 'Foto '.($index + 1).' album '.$gallery->title" :lazy="false" class="d-block w-100" />
                                        @if (filled($photo->caption))
                                            <p class="bg-light px-3 py-2 mb-0">{{ $photo->caption }}</p>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                            @if ($photos->count() > 1)
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

    <p class="mt-5 mb-0">
        <a class="btn btn-outline-primary" href="{{ route('galleries.index') }}"><i class="bi bi-arrow-left" aria-hidden="true"></i> Kembali ke galeri</a>
    </p>
</x-layouts.public>
