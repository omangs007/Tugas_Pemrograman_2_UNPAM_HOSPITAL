@php
    $activeCount = count($filters);
    $hasFilters = $activeCount > 0;
@endphp
<x-layouts.public title="Cari Dokter" meta-description="Cari dokter UNPAM HOSPITAL berdasarkan nama, spesialisasi, poliklinik, dan tanggal." :noindex="$doctors->isEmpty()">
    <x-slot:breadcrumb>
        <x-breadcrumb :items="[['label' => 'Dokter']]" />
    </x-slot:breadcrumb>

    @push('head')
        <link rel="canonical" href="{{ route('doctors.index') }}">
    @endpush

    <x-page-header title="Cari Dokter" subtitle="Cari berdasarkan nama, spesialisasi, poliklinik, atau tanggal praktik" />

    <form method="GET" action="{{ route('doctors.index') }}" role="search" aria-label="Cari dokter" class="card card-body bg-light border-0 mb-4" id="form-cari-dokter">
        <button class="btn btn-outline-primary d-md-none w-100" type="button" data-bs-toggle="collapse" data-bs-target="#filter-dokter" aria-expanded="{{ $hasFilters ? 'true' : 'false' }}" aria-controls="filter-dokter">
            <i class="bi bi-funnel" aria-hidden="true"></i> Filter ({{ $activeCount }} aktif)
        </button>
        <div id="filter-dokter" @class(['collapse', 'd-md-block', 'show' => $hasFilters, 'mt-3 mt-md-0' => true])>
            <div class="row g-3 align-items-start">
                <div class="col-12 col-md-6 col-xl-3">
                    <x-form.input name="q" label="Nama dokter" :value="$filters['q'] ?? null" maxlength="100" autocomplete="off" placeholder="Nama dokter atau spesialisasi" />
                </div>
                <div class="col-12 col-md-6 col-xl-3">
                    <x-form.select name="spesialisasi" label="Spesialisasi" :options="$specialtyOptions" :selected="$filters['spesialisasi'] ?? ''" />
                </div>
                <div class="col-12 col-md-6 col-xl-3">
                    <x-form.select name="poli" label="Poliklinik" :options="$clinicOptions" :selected="$filters['poli'] ?? ''" />
                </div>
                <div class="col-12 col-md-6 col-xl-3">
                    <x-form.input name="tanggal" label="Tanggal praktik" type="date" :value="$filters['tanggal'] ?? null" :help="'Dari hari ini sampai '.$windowDays.' hari ke depan.'" :min="$today->toDateString()" :max="$today->addDays($windowDays)->toDateString()" />
                </div>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <x-loading-button icon="bi-search" loading-text="Mencari…">Cari Dokter</x-loading-button>
                <a class="btn btn-outline-secondary" href="{{ route('doctors.index') }}">Reset filter</a>
            </div>
        </div>
    </form>

    @if ($doctors->isEmpty())
        @if ($doctors->total() > 0)
            <x-empty-state title="Halaman yang diminta tidak berisi data." message="Data pada halaman ini mungkin sudah berubah." icon="bi-exclamation-circle">
                <a class="btn btn-outline-primary" href="{{ $doctors->withQueryString()->url(1) }}">Kembali ke halaman 1</a>
            </x-empty-state>
        @elseif ($hasFilters)
            <x-empty-state title="Tidak ada dokter yang cocok" message="Coba ubah kata kunci atau pilihan filter Anda." icon="bi-search">
                <a class="btn btn-outline-primary" href="{{ route('doctors.index') }}">Reset filter</a>
            </x-empty-state>
        @else
            <x-empty-state title="Belum ada dokter yang tampil" message="Daftar dokter akan tampil di sini setelah data tersedia." icon="bi-person-badge" />
        @endif
    @else
        <p class="text-secondary" aria-live="polite">Menampilkan {{ $doctors->count() }} dari {{ $doctors->total() }} dokter</p>

        <div class="row g-3 row-cols-1 row-cols-lg-2 row-cols-xl-3">
            @foreach ($doctors as $doctor)
                <div class="col">
                    <x-doctor-card :doctor="$doctor" :heading-level="2" :show-clinic="true" :next-session="$nextSessions[$doctor->id] ?? null" />
                </div>
            @endforeach
        </div>

        <div class="mt-4">{{ $doctors->withQueryString()->links() }}</div>
    @endif
</x-layouts.public>
