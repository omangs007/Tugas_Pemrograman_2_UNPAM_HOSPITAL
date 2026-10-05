<x-layouts.public title="Jadwal Dokter" meta-description="Jadwal praktik dokter UNPAM HOSPITAL per minggu dengan sisa kuota.">
    <x-slot:breadcrumb>
        <x-breadcrumb :items="[['label' => 'Jadwal Dokter']]" />
    </x-slot:breadcrumb>

    @push('head')
        <link rel="canonical" href="{{ route('schedules.index') }}">
    @endpush

    <x-page-header title="Jadwal Dokter" subtitle="Jadwal praktik per minggu" />

    @if ($notice)
        <div class="alert alert-warning" role="status" data-schedule-notice>{{ $notice }}</div>
    @endif

    <form method="GET" action="{{ route('schedules.index') }}" role="search" aria-label="Saring jadwal dokter" class="card card-body bg-light border-0 mb-3">
        <input type="hidden" name="minggu" value="{{ $weekStart->toDateString() }}">
        <div class="row g-3 align-items-start">
            <div class="col-12 col-md-5">
                <x-form.select name="poli" label="Poliklinik" :options="$clinicOptions" :selected="$filters['poli'] ?? ''" />
            </div>
            <div class="col-12 col-md-5">
                <x-form.select name="dokter" label="Dokter" :options="$doctorOptions" :selected="$filters['dokter'] ?? ''" />
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <x-loading-button icon="bi-funnel" loading-text="Menerapkan…">Terapkan</x-loading-button>
            <a class="btn btn-outline-primary" href="{{ route('schedules.index') }}">Reset</a>
        </div>
    </form>

    @php
        $carry = array_intersect_key($filters, array_flip(['poli', 'dokter']));
    @endphp
    <nav class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3" aria-label="Navigasi minggu" data-week-nav>
        @if ($previousWeek)
            <a class="btn btn-outline-primary" href="{{ route('schedules.index', $carry + ['minggu' => $previousWeek->toDateString()]) }}" rel="prev"><i class="bi bi-chevron-left" aria-hidden="true"></i> Minggu sebelumnya</a>
        @else
            <span class="btn btn-outline-secondary disabled" aria-disabled="true"><i class="bi bi-chevron-left" aria-hidden="true"></i> Minggu sebelumnya</span>
        @endif
        <p class="fw-semibold mb-0" aria-live="polite">Minggu {{ $weekLabel }}</p>
        @if ($nextWeek)
            <a class="btn btn-outline-primary" href="{{ route('schedules.index', $carry + ['minggu' => $nextWeek->toDateString()]) }}" rel="next">Minggu berikutnya <i class="bi bi-chevron-right" aria-hidden="true"></i></a>
        @else
            <span class="btn btn-outline-secondary disabled" aria-disabled="true">Minggu berikutnya <i class="bi bi-chevron-right" aria-hidden="true"></i></span>
        @endif
    </nav>

    @if ($items->isEmpty())
        <x-empty-state title="Tidak ada jadwal praktik pada pekan ini" message="Coba pilih minggu lain atau ubah filter poliklinik dan dokter." icon="bi-calendar-x">
            @if ($nextWeek)
                <a class="btn btn-outline-primary" href="{{ route('schedules.index', $carry + ['minggu' => $nextWeek->toDateString()]) }}">Lihat minggu berikutnya</a>
            @endif
            <a class="btn btn-outline-primary" href="{{ route('schedules.index') }}">Reset</a>
        </x-empty-state>
    @else
        @include('public.schedules._week-grid')

        <div class="d-md-none" data-week-stack>
            @foreach ($days as $day)
                @php $dayItems = $byDate->get($day->toDateString()); @endphp
                @if ($dayItems !== null)
                    <section class="mb-4" aria-labelledby="hari-{{ $day->toDateString() }}">
                        <h2 class="h5 mb-2" id="hari-{{ $day->toDateString() }}">{{ $day->translatedFormat('l, j F Y') }}</h2>
                        <x-schedule-table :sessions="$dayItems" :caption="'Jadwal praktik '.$day->translatedFormat('l, j F Y')" />
                    </section>
                @endif
            @endforeach
        </div>
    @endif

    @include('public.partials._availability-legend')
</x-layouts.public>
