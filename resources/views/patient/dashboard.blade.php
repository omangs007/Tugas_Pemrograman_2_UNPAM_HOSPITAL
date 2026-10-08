<x-layouts.patient title="Dashboard Pasien">
    <h1 class="h2 mb-1">Halo, {{ $profile->full_name }}</h1>
    <p class="text-body-secondary mb-4">No. pasien: {{ $profile->patient_number ?? '-' }}</p>

    @if ($changedCount > 0)
        <div class="alert alert-warning d-flex flex-wrap align-items-center justify-content-between gap-2" role="status">
            <span><i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i> Jadwal {{ $changedCount }} pendaftaran Anda berubah.</span>
            <a class="btn btn-sm btn-warning" href="{{ route('patient.appointments.index', ['jadwal_berubah' => 1]) }}">Lihat pendaftaran</a>
        </div>
    @endif

    @if ($notices !== [])
        <section class="mb-4" aria-labelledby="pemberitahuan-judul">
            <h2 class="h4 mb-3" id="pemberitahuan-judul">Pemberitahuan</h2>
            @foreach ($notices as $notice)
                <div @class(['alert d-flex flex-column flex-md-row gap-2 justify-content-between align-items-md-center', 'alert-warning' => $notice['is_schedule_change'], 'alert-info' => ! $notice['is_schedule_change']]) role="status">
                    <div>
                        <p class="fw-semibold mb-0"><i @class(['bi', 'bi-exclamation-triangle-fill' => $notice['is_schedule_change'], 'bi-bell-fill' => ! $notice['is_schedule_change']]) aria-hidden="true"></i> {{ $notice['title'] }}</p>
                        @if ($notice['body'] !== '')
                            <p class="mb-0">{{ $notice['body'] }}</p>
                        @endif
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <form method="POST" action="{{ route('patient.notifications.read', ['id' => $notice['id']]) }}">
                            @csrf
                            <input type="hidden" name="tujuan" value="detail">
                            <button type="submit" class="btn btn-sm btn-outline-primary" aria-label="Lihat detail: {{ $notice['title'] }}">Lihat detail</button>
                        </form>
                        <form method="POST" action="{{ route('patient.notifications.read', ['id' => $notice['id']]) }}">
                            @csrf
                            <input type="hidden" name="tujuan" value="dashboard">
                            <button type="submit" class="btn btn-sm btn-outline-secondary" aria-label="Tandai sudah dibaca: {{ $notice['title'] }}">Tandai sudah dibaca</button>
                        </form>
                    </div>
                </div>
            @endforeach
        </section>
    @endif

    <div class="row g-4">
        <section class="col-lg-7" aria-labelledby="kunjungan-judul">
            <h2 class="h4 mb-3" id="kunjungan-judul">Kunjungan Mendatang</h2>
            @if ($upcoming->isEmpty())
                <x-empty-state icon="bi-calendar-x" title="Belum ada pendaftaran" message="Pendaftaran kunjungan Anda akan tampil di sini.">
                    <a class="btn btn-primary" href="{{ route('patient.appointments.create') }}">Daftar Kunjungan</a>
                </x-empty-state>
            @else
                <ul class="list-unstyled d-grid gap-3 mb-3">
                    @foreach ($upcoming as $appointment)
                        <li class="card">
                            <div class="card-body">
                                <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
                                    <div>
                                        <a class="fw-semibold" href="{{ route('patient.appointments.show', ['appointment' => $appointment->booking_code]) }}" aria-label="Detail pendaftaran {{ $appointment->booking_code }}">{{ $appointment->booking_code }}</a>
                                        <div class="small text-body-secondary">Antrean: {{ $appointment->queue_label }}</div>
                                    </div>
                                    <x-status-badge :status="$appointment->status" />
                                </div>
                                <p class="mb-1">{{ $appointment->doctor->display_name }}</p>
                                <p class="mb-1 text-body-secondary">{{ $appointment->clinic->name }}</p>
                                <p class="mb-1"><i class="bi bi-calendar-event" aria-hidden="true"></i> <time datetime="{{ $appointment->session_date->toDateString() }}">{{ $appointment->session_date->translatedFormat('l, j F Y') }}</time>, {{ $appointment->practiceSession->time_range }}</p>
                                @if ($appointment->schedule_changed_at)
                                    <p class="small mb-0 text-warning-emphasis"><i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i> Jadwal berubah pada {{ $appointment->schedule_changed_at->translatedFormat('j M Y') }}.</p>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
                <a href="{{ route('patient.appointments.index') }}">Lihat semua riwayat</a>
            @endif
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
                    <a class="btn btn-outline-primary btn-sm mt-3" href="{{ route('patient.profile.edit') }}">Ubah Profil dan Akun</a>
                </div>
            </div>
        </section>
    </div>
</x-layouts.patient>
