<x-layouts.patient title="Dashboard Pasien">
    <h1 class="h2 mb-1">Halo, {{ $profile->full_name }}</h1>
    <p class="text-body-secondary mb-4">No. pasien: {{ $profile->patient_number ?? '-' }}</p>

    <div class="row g-4">
        <section class="col-lg-7" aria-labelledby="kunjungan-judul">
            <h2 class="h4 mb-3" id="kunjungan-judul">Kunjungan Mendatang</h2>
            <x-empty-state icon="bi-calendar-x" title="Belum ada pendaftaran" message="Pendaftaran kunjungan Anda akan tampil di sini.">
                @if (Route::has('patient.appointments.create'))
                    <a class="btn btn-primary" href="{{ route('patient.appointments.create') }}">Daftar Kunjungan</a>
                @else
                    <a class="btn btn-outline-primary" href="{{ route('schedules.index') }}">Lihat Jadwal Dokter</a>
                @endif
            </x-empty-state>
        </section>

        <section class="col-lg-5" aria-labelledby="profil-judul">
            <div class="card">
                <div class="card-body">
                    <h2 class="h5 card-title mb-3" id="profil-judul">Profil Anda</h2>
                    <dl class="mb-0">
                        <dt class="small text-body-secondary">Email</dt>
                        <dd>{{ $profile->email ?? '-' }}</dd>
                        <dt class="small text-body-secondary">Nomor telepon</dt>
                        <dd class="mb-0">{{ $profile->phone ?? '-' }}</dd>
                    </dl>
                </div>
            </div>
        </section>
    </div>
</x-layouts.patient>
