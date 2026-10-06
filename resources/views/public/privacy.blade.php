@inject('site', 'App\Services\Settings\SettingsService')
<x-layouts.public :title="$page->seo_title" :meta-description="$page->seo_description">
    <x-slot:breadcrumb>
        <x-breadcrumb :items="[['label' => 'Kebijakan Privasi']]" />
    </x-slot:breadcrumb>

    @push('head')
        <x-open-graph :title="$page->seo_title" :description="$page->seo_description" :canonical="route('privacy')" />
    @endpush

    <x-page-header :title="$page->title" :subtitle="$page->subtitle" />

    @if (filled($page->body))
        @include('public.partials._page-body', ['page' => $page])
    @else
        <x-empty-state title="Konten sedang disiapkan" message="Isi halaman ini sedang disiapkan oleh admin." icon="bi-file-earmark-text" />
    @endif

    <p class="text-body-secondary mt-4 mb-0">Versi kebijakan: {{ $site->string('privacy.consent_version', '1.0') ?: '1.0' }}</p>
</x-layouts.public>
