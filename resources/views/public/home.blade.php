@inject('site', 'App\Services\Settings\SettingsService')
<x-layouts.public title="Beranda">
    <section class="py-4 py-lg-5 text-center">
        <h1 class="display-5 fw-bold text-primary">{{ $site->string('site.name', config('app.name')) }}</h1>
        <p class="lead mb-4">{{ $site->string('site.tagline', 'Layanan kesehatan yang ramah dan terpercaya') }}</p>

        <div class="d-flex flex-wrap justify-content-center gap-2">
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

        <p class="text-body-secondary mt-4 mb-0">Situs ini masih dalam tahap pembangunan. Halaman dokter, jadwal, dan pendaftaran kunjungan akan tersedia pada tahap berikutnya.</p>
    </section>
</x-layouts.public>
