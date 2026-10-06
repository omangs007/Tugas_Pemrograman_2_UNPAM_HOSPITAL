<x-layouts.public :title="$title" :meta-description="$page->seo_description" :noindex="$articles->isEmpty()">
    <x-slot:breadcrumb>
        <x-breadcrumb :items="[['label' => 'Artikel']]" />
    </x-slot:breadcrumb>

    @push('head')
        <x-open-graph :title="$title" :description="$page->seo_description" :canonical="route('articles.index')" />
    @endpush

    <x-page-header :title="$page->title" :subtitle="$page->subtitle" />

    <form method="GET" action="{{ route('articles.index') }}" role="search" aria-label="Cari artikel" class="card card-body bg-light border-0 mb-4">
        <div class="row g-3 align-items-start">
            <div class="col-12 col-md-6">
                <x-form.input name="q" label="Cari artikel" :value="$term" maxlength="100" autocomplete="off" placeholder="Kata kunci judul atau ringkasan" />
            </div>
            <div class="col-12 col-md-6">
                <x-form.select name="kategori" label="Kategori" :options="$categoryOptions" :selected="$category?->slug ?? ''" />
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <x-loading-button icon="bi-search" loading-text="Mencari…">Cari</x-loading-button>
            <a class="btn btn-outline-primary" href="{{ route('articles.index') }}">Reset</a>
        </div>
    </form>

    @if ($articles->isEmpty())
        @if ($articles->total() > 0)
            <x-empty-state title="Halaman yang diminta tidak berisi data." message="Data pada halaman ini mungkin sudah berubah." icon="bi-exclamation-circle">
                <a class="btn btn-outline-primary" href="{{ $articles->url(1) }}">Kembali ke halaman 1</a>
            </x-empty-state>
        @else
            <x-empty-state title="Artikel tidak ditemukan" message="Coba ubah kata kunci atau pilih kategori lain." icon="bi-search">
                <a class="btn btn-outline-primary" href="{{ route('articles.index') }}">Reset filter</a>
            </x-empty-state>
        @endif
    @else
        <p class="text-secondary" aria-live="polite">Menampilkan {{ $articles->count() }} dari {{ $articles->total() }} artikel</p>

        <div class="row g-4 row-cols-1 row-cols-md-2 row-cols-lg-3">
            @foreach ($articles as $article)
                <div class="col">
                    @include('public.partials._article-card', ['article' => $article, 'headingLevel' => 2])
                </div>
            @endforeach
        </div>

        <div class="mt-4">{{ $articles->links() }}</div>
    @endif
</x-layouts.public>
