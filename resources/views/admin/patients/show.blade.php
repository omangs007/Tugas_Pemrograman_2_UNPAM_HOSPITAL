@php
    // Tautan kode booking baru aktif bila route detail pendaftaran ada (T8) dan pengguna berizin appointments.view.
    $canOpenAppointments = \Illuminate\Support\Facades\Route::has('admin.appointments.show')
        && (bool) auth()->user()?->can('viewAny', \App\Models\Appointment::class);
@endphp
<x-layouts.admin :title="$patient->full_name">
    <x-slot:breadcrumb>
        <x-breadcrumb home="admin.dashboard" :items="[
            ['label' => 'Pasien', 'url' => route('admin.patients.index')],
            ['label' => $patient->full_name.' ('.($patient->patient_number ?? '-').')'],
        ]" />
    </x-slot:breadcrumb>

    <x-slot:actions>
        <a href="{{ route('admin.patients.index') }}" class="btn btn-outline-secondary">Kembali ke daftar</a>
    </x-slot:actions>

    <div class="row g-4">
        <div class="col-12 col-lg-5">
            <section class="card mb-4" aria-labelledby="judul-data-pasien">
                <div class="card-header fw-semibold"><h2 class="h6 mb-0" id="judul-data-pasien">Data pasien</h2></div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-5">No. pasien</dt>
                        <dd class="col-sm-7">{{ $patient->patient_number ?? '—' }}</dd>
                        <dt class="col-sm-5">Nama lengkap</dt>
                        <dd class="col-sm-7">{{ $patient->full_name }}</dd>
                        <dt class="col-sm-5">Jenis kelamin</dt>
                        <dd class="col-sm-7">{{ $patient->gender->label() }}</dd>
                        <dt class="col-sm-5">Tanggal lahir</dt>
                        <dd class="col-sm-7">{{ $patient->birth_date->format('d/m/Y') }}</dd>
                        <dt class="col-sm-5">Telepon</dt>
                        <dd class="col-sm-7">{{ $patient->phone }}</dd>
                        <dt class="col-sm-5">Email kontak</dt>
                        <dd class="col-sm-7">{{ $patient->email ?? '—' }}</dd>
                        <dt class="col-sm-5">Alamat</dt>
                        <dd class="col-sm-7">{{ $patient->address }}</dd>
                        <dt class="col-sm-5">Nomor BPJS</dt>
                        <dd class="col-sm-7">{{ $patient->masked_bpjs ?? 'Tidak ada nomor BPJS tersimpan' }}</dd>
                        <dt class="col-sm-5">Kanal registrasi</dt>
                        <dd class="col-sm-7"><x-status-badge :status="$patient->registration_channel" /></dd>
                        <dt class="col-sm-5">Status</dt>
                        <dd class="col-sm-7">
                            @if ($patient->is_active)
                                <span class="badge text-bg-success"><i class="bi bi-check-circle" aria-hidden="true"></i> Aktif</span>
                            @else
                                <span class="badge text-bg-secondary"><i class="bi bi-slash-circle" aria-hidden="true"></i> Nonaktif</span>
                            @endif
                        </dd>
                        <dt class="col-sm-5">Persetujuan privasi</dt>
                        <dd class="col-sm-7">{{ $patient->consent_at->translatedFormat('j F Y H.i') }} WIB, versi {{ $patient->consent_version }}</dd>
                        @if ($patient->creator !== null)
                            <dt class="col-sm-5">Dibuat oleh</dt>
                            <dd class="col-sm-7">{{ $patient->creator->name }}</dd>
                        @endif
                        <dt class="col-sm-5">Dibuat</dt>
                        <dd class="col-sm-7">{{ $patient->created_at->translatedFormat('j F Y H.i') }} WIB</dd>
                    </dl>
                </div>
            </section>

            <section class="card" aria-labelledby="judul-akun-pasien">
                <div class="card-header fw-semibold"><h2 class="h6 mb-0" id="judul-akun-pasien">Akun online</h2></div>
                <div class="card-body">
                    @if ($patient->user !== null)
                        <p class="mb-1">Terhubung ke akun <strong>{{ $patient->user->email }}</strong>.</p>
                        <p class="small text-secondary mb-0">Status akun: {{ $patient->user->is_active ? 'aktif' : 'nonaktif' }}.</p>
                    @else
                        <p class="mb-1">Tanpa akun</p>
                        <p class="small text-secondary mb-0">Profil walk-in ini belum terhubung ke akun online.</p>
                    @endif
                </div>
            </section>
        </div>

        <div class="col-12 col-lg-7">
            <section class="card" aria-labelledby="judul-riwayat-pasien">
                <div class="card-header fw-semibold"><h2 class="h6 mb-0" id="judul-riwayat-pasien">Riwayat pendaftaran terbaru</h2></div>
                @if ($appointments->isEmpty() && $appointments->total() === 0)
                    <div class="card-body">
                        <x-empty-state icon="bi-calendar-x" title="Belum ada pendaftaran" message="Pasien ini belum pernah mendaftar kunjungan." />
                    </div>
                @elseif ($appointments->isEmpty())
                    <div class="card-body">
                        <x-empty-state icon="bi-exclamation-circle" title="Halaman riwayat yang diminta tidak berisi data." message="Data pada halaman ini mungkin sudah berubah.">
                            <a href="{{ $appointments->withQueryString()->url(1) }}" class="btn btn-outline-primary">Kembali ke halaman 1</a>
                        </x-empty-state>
                    </div>
                @else
                    <p class="px-3 pt-3 mb-2 text-secondary" aria-live="polite">Menampilkan {{ $appointments->firstItem() }}–{{ $appointments->lastItem() }} dari {{ $appointments->total() }} pendaftaran</p>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle table-cards bg-white mb-0">
                            <caption class="visually-hidden">Riwayat pendaftaran pasien</caption>
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">Kode</th>
                                    <th scope="col">Tanggal</th>
                                    <th scope="col">Poliklinik</th>
                                    <th scope="col">Dokter</th>
                                    <th scope="col">Antrean</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Kanal</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($appointments as $appointment)
                                    <tr>
                                        <td data-label="Kode">
                                            @if ($canOpenAppointments)
                                                <a href="{{ route('admin.appointments.show', ['appointment' => $appointment->booking_code]) }}" class="fw-semibold" aria-label="Detail pendaftaran {{ $appointment->booking_code }}">{{ $appointment->booking_code }}</a>
                                            @else
                                                <span class="fw-semibold">{{ $appointment->booking_code }}</span>
                                            @endif
                                        </td>
                                        <td data-label="Tanggal">
                                            <time datetime="{{ $appointment->session_date->toDateString() }}">{{ $appointment->session_date->format('d/m/Y') }}</time>
                                            <span class="d-block small text-body-secondary">{{ $appointment->practiceSession->time_range }}</span>
                                        </td>
                                        <td data-label="Poliklinik">{{ $appointment->clinic->name }}</td>
                                        <td data-label="Dokter">{{ $appointment->doctor->display_name }}</td>
                                        <td data-label="Antrean">{{ $appointment->queue_label }}</td>
                                        <td data-label="Status"><x-status-badge :status="$appointment->status" /></td>
                                        <td data-label="Kanal"><x-status-badge :status="$appointment->channel" /></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if ($appointments->hasPages())
                        <div class="card-footer">{{ $appointments->withQueryString()->links() }}</div>
                    @endif
                @endif
            </section>
        </div>
    </div>
</x-layouts.admin>
