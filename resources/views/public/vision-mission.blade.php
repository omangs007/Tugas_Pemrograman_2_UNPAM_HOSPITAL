<x-layouts.public :title="$page->seo_title" :meta-description="$page->seo_description">
    <x-slot:breadcrumb>
        <x-breadcrumb :items="[['label' => 'Visi dan Misi']]" />
    </x-slot:breadcrumb>

    @push('head')
        <x-open-graph :title="$page->seo_title" :description="$page->seo_description" :canonical="route('vision-mission')" />
    @endpush

    <x-page-header :title="$page->title" :subtitle="$page->subtitle" />

    @if (filled($page->body))
        @include('public.partials._page-body', ['page' => $page])
    @else
        <x-empty-state title="Konten sedang disiapkan" message="Isi halaman ini sedang disiapkan oleh admin." icon="bi-file-earmark-text" />
    @endif

    <nav class="d-flex flex-wrap gap-2 mt-5" aria-label="Tautan terkait">
        <a class="btn btn-outline-primary" href="{{ route('about') }}">Profil Rumah Sakit</a>
    </nav>
</x-layouts.public>
