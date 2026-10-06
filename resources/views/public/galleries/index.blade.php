<x-layouts.public :title="$title" :meta-description="$page->seo_description" :noindex="$galleries->isEmpty()">
    <x-slot:breadcrumb>
        <x-breadcrumb :items="[['label' => 'Galeri']]" />
    </x-slot:breadcrumb>

    @push('head')
        <x-open-graph :title="$title" :description="$page->seo_description" :canonical="route('galleries.index')" />
    @endpush

    <x-page-header :title="$page->title" :subtitle="$page->subtitle" />

    @if ($galleries->isEmpty())
        @if ($galleries->total() > 0)
            <x-empty-state title="Halaman yang diminta tidak berisi data." message="Data pada halaman ini mungkin sudah berubah." icon="bi-exclamation-circle">
                <a class="btn btn-outline-primary" href="{{ $galleries->url(1) }}">Kembali ke halaman 1</a>
            </x-empty-state>
        @else
            <x-empty-state title="Belum ada album galeri" message="Album foto akan tampil di sini setelah diterbitkan." icon="bi-images" />
        @endif
    @else
        <div class="row g-4 row-cols-1 row-cols-sm-2 row-cols-lg-3">
            @foreach ($galleries as $gallery)
                @php
                    $cover = $gallery->coverMedia();
                    $count = $gallery->mediaIn('photos')->count();
                @endphp
                <div class="col">
                    <article class="card h-100 gallery-card">
                        <x-image type="gallery" :media="$cover" :alt="$cover?->alt_text ?: 'Sampul album '.$gallery->title" variant="thumb" ratio="4x3" />
                        <div class="card-body d-flex flex-column">
                            <h2 class="card-title h5 mb-1">
                                <a class="stretched-link" href="{{ route('galleries.show', $gallery) }}">{{ $gallery->title }}</a>
                            </h2>
                            <p class="small text-body-secondary mb-2"><i class="bi bi-images" aria-hidden="true"></i> {{ $count }} foto</p>
                            @if (filled($gallery->description))
                                <p class="card-text mb-0">{{ \Illuminate\Support\Str::limit($gallery->description, 100) }}</p>
                            @endif
                        </div>
                    </article>
                </div>
            @endforeach
        </div>

        <div class="mt-4">{{ $galleries->links() }}</div>
    @endif
</x-layouts.public>
