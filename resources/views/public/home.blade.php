@inject('site', 'App\Services\Settings\SettingsService')
@php
    $ogDescription = filled($metaDescription) ? $metaDescription : $site->string('seo.default_meta_description');
@endphp
<x-layouts.public :title="$title" :meta-description="$metaDescription" :full-title="true">
    @push('head')
        <x-open-graph :title="$title" :description="$ogDescription !== '' ? $ogDescription : null" :image="$ogImage" type="website" :canonical="route('home')" />
    @endpush

    <section class="home-hero" aria-labelledby="judul-beranda">
        <div class="text-center">
            <h1 id="judul-beranda" class="display-5 fw-bold text-primary">{{ $page->title }}</h1>
            @if ($subtitle !== '')
                <p class="lead mb-4">{{ $subtitle }}</p>
            @endif

            <div class="home-hero-actions">
                @if (Route::has('doctors.index'))
                    <a class="btn btn-primary btn-lg" href="{{ route('doctors.index') }}">Cari Dokter</a>
                @endif
                @if (Route::has('schedules.index'))
                    <a class="btn btn-outline-primary btn-lg" href="{{ route('schedules.index') }}">Jadwal Dokter</a>
                @endif
                @if (Route::has('patient.appointments.create'))
                    <a class="btn btn-primary btn-lg" href="{{ route('patient.appointments.create') }}">Daftar Kunjungan</a>
                @endif
            </div>
        </div>

        @if ($banners->isNotEmpty())
            @include('public.partials._banner-carousel', ['banners' => $banners])
        @endif
    </section>

    @include('public.partials._quick-search')
    @include('public.partials._featured-clinics')
    @include('public.partials._featured-doctors')
    @include('public.partials._latest-articles')
    @include('public.partials._help-cta')
</x-layouts.public>
