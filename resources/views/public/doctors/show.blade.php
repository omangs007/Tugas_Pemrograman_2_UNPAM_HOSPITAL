@php
    $photo = $doctor->firstMedia('photo');
    $primary = $doctor->primary_specialty;
    $specialties = $doctor->specialties->sortByDesc(fn ($specialty): int => (int) $specialty->pivot->is_primary)->values();
@endphp
<x-layouts.public :title="$title" :meta-description="$metaDescription">
    <x-slot:breadcrumb>
        <x-breadcrumb :items="[['label' => 'Dokter', 'url' => route('doctors.index')], ['label' => $doctor->display_name]]" />
    </x-slot:breadcrumb>

    @push('head')
        <link rel="canonical" href="{{ route('doctors.show', $doctor) }}">
        <meta property="og:type" content="profile">
        <meta property="og:title" content="{{ $title }}">
        <meta property="og:description" content="{{ $metaDescription }}">
        @if ($photo?->url)
            <meta property="og:image" content="{{ url($photo->url) }}">
        @endif
    @endpush

    <div class="row g-4 g-lg-5">
        <div class="col-12 col-md-4">
            <x-image type="doctor" :media="$photo" :alt="'Foto '.$doctor->display_name" ratio="1x1" :lazy="false" />
        </div>
        <div class="col-12 col-md-8">
            <h1 class="h2 mb-2">{{ $doctor->display_name }}</h1>
            @if ($specialties->isNotEmpty())
                <p class="mb-2">
                    @foreach ($specialties as $specialty)
                        @if ($primary && $specialty->id === $primary->id)
                            <span class="badge text-bg-primary">{{ $specialty->name }} (utama)</span>
                        @else
                            <span class="badge text-bg-light border">{{ $specialty->name }}</span>
                        @endif
                    @endforeach
                </p>
            @endif
            <p class="text-body-secondary mb-3">
                <i class="bi bi-hospital" aria-hidden="true"></i>
                Poliklinik: <a href="{{ route('clinics.show', $doctor->clinic) }}">{{ $doctor->clinic->name }}</a>, {{ $doctor->clinic->location }}
            </p>

            @if (filled($doctor->short_bio))
                <h2 class="h5">Profil singkat</h2>
                <p>{{ $doctor->short_bio }}</p>
            @endif

            @if (filled($doctor->bio))
                <h2 class="h5">Biodata</h2>
                <div class="doctor-bio">{{ $doctor->bio }}</div>
            @endif
        </div>
    </div>

    <section id="jadwal" class="mt-5" aria-labelledby="jadwal-judul">
        <h2 id="jadwal-judul" class="h4 mb-3">Jadwal praktik 14 hari ke depan</h2>

        @if ($sessions->isEmpty())
            <x-empty-state title="Belum ada jadwal praktik dua minggu ke depan" message="Jadwal dokter ini belum tersedia. Silakan cek kembali nanti." icon="bi-calendar-x">
                @if (Route::has('contact'))
                    <a class="btn btn-outline-primary" href="{{ route('contact') }}">Hubungi Kami</a>
                @endif
            </x-empty-state>
        @else
            <x-schedule-table :sessions="$sessions" :show-doctor="false" :caption="'Jadwal praktik '.$doctor->display_name.' 14 hari ke depan'" />
            <p class="small text-body-secondary mt-2 mb-0">Jadwal dapat berubah. Sumber data sama dengan formulir pendaftaran.</p>
            @include('public.partials._availability-legend')
        @endif

        <p class="mt-4 mb-0"><a class="btn btn-outline-primary" href="{{ route('schedules.index') }}">Lihat jadwal mingguan semua dokter</a></p>
    </section>
</x-layouts.public>
