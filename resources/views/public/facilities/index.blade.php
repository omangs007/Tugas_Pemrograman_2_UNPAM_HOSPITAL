<x-layouts.public :title="$page->seo_title" :meta-description="$page->seo_description">
    <x-slot:breadcrumb>
        <x-breadcrumb :items="[['label' => 'Fasilitas']]" />
    </x-slot:breadcrumb>

    @push('head')
        <x-open-graph :title="$page->seo_title" :description="$page->seo_description" :canonical="route('facilities.index')" />
    @endpush

    <x-page-header :title="$page->title" :subtitle="$page->subtitle" />

    @if ($groups->isEmpty())
        <x-empty-state title="Belum ada fasilitas" message="Informasi fasilitas akan tampil di sini setelah ditambahkan." icon="bi-building" />
    @else
        @foreach ($groups as [$category, $items])
            <section class="mb-5" aria-labelledby="fasilitas-{{ $category->value }}">
                <h2 class="h4 mb-3" id="fasilitas-{{ $category->value }}"><i class="bi {{ $category->icon() }}" aria-hidden="true"></i> {{ $category->label() }}</h2>
                <div class="row g-4 row-cols-1 row-cols-sm-2 row-cols-lg-3">
                    @foreach ($items as $facility)
                        <div class="col">
                            @include('public.facilities._card', ['facility' => $facility, 'headingLevel' => 3])
                        </div>
                    @endforeach
                </div>
            </section>
        @endforeach

        @if (Route::has('rooms.index') || Route::has('contact'))
            <p class="d-flex flex-wrap gap-2 mb-0">
                @if (Route::has('rooms.index'))
                    <a class="btn btn-outline-primary" href="{{ route('rooms.index') }}">Lihat Kamar Rawat Inap</a>
                @endif
                @if (Route::has('contact'))
                    <a class="btn btn-outline-primary" href="{{ route('contact') }}">Kontak</a>
                @endif
            </p>
        @endif
    @endif
</x-layouts.public>
