<x-layouts.public :title="$page->seo_title" :meta-description="$page->seo_description">
    <x-slot:breadcrumb>
        <x-breadcrumb :items="[['label' => 'Panduan Pendaftaran']]" />
    </x-slot:breadcrumb>

    @push('head')
        <x-open-graph :title="$page->seo_title" :description="$page->seo_description" :canonical="route('registration-guide')" />
    @endpush

    <x-page-header :title="$page->title" :subtitle="$page->subtitle" />

    @if (filled($page->body))
        @include('public.partials._page-body', ['page' => $page])
    @else
        <x-empty-state title="Konten sedang disiapkan" message="Isi halaman ini sedang disiapkan oleh admin." icon="bi-file-earmark-text" />
    @endif

    <nav class="d-flex flex-wrap gap-2 mt-5" aria-label="Tautan terkait">
        @if (Route::has('patient.appointments.create'))
            <a class="btn btn-primary" href="{{ route('patient.appointments.create') }}">Daftar Kunjungan</a>
        @endif
        @if (Route::has('register'))
            <a class="btn btn-outline-primary" href="{{ route('register') }}">Daftar Akun</a>
        @endif
        @if (Route::has('faq'))
            <a class="btn btn-outline-primary" href="{{ route('faq') }}">FAQ</a>
        @endif
    </nav>
</x-layouts.public>
