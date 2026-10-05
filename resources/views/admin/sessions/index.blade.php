@php
    $statusNow = is_string(request()->query('status')) ? request()->query('status') : '';
    $breadcrumbItems = [
        ['label' => 'Jadwal Praktik', 'url' => route('admin.schedules.index')],
        ['label' => 'Sesi per Tanggal'],
    ];
@endphp
<x-layouts.admin title="Jadwal Praktik">
    <x-slot:breadcrumb>
        <x-breadcrumb home="admin.dashboard" :items="$breadcrumbItems" />
    </x-slot:breadcrumb>

    <x-slot:actions>
        @can('create', \App\Models\PracticeSession::class)
            <a href="{{ route('admin.sessions.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-lg" aria-hidden="true"></i> Tambah Sesi
            </a>
        @endcan
    </x-slot:actions>

    <div class="mb-3">
        <x-module-tabs group="schedules" />
    </div>

    @include('admin.schedules._review-banner')

    <p class="small text-secondary" data-sessions-note>
        Kolom Terisi hanya menampilkan jumlah pendaftaran, tanpa data pasien. Sesi dari templat tidak dapat dihapus: tutup atau batalkan sesi.
        Hanya sesi tambahan tanpa pendaftaran yang dapat dihapus.
    </p>

    <x-data-table
        :rows="$sessions"
        :searchable="false"
        :preserve="[]"
        caption="Daftar sesi praktik per tanggal"
        empty-title="Tidak ada sesi pada rentang ini"
        empty-message="Ubah rentang tanggal atau filter, atau tambahkan sesi tambahan.">
        <x-slot:filters>
            <div class="col-6 col-md-3 col-lg-2">
                <label for="filter-dari" class="form-label fw-semibold">Dari</label>
                <input type="date" id="filter-dari" name="dari" value="{{ $range[0]->toDateString() }}" class="form-control">
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <label for="filter-sampai" class="form-label fw-semibold">Sampai</label>
                <input type="date" id="filter-sampai" name="sampai" value="{{ $range[1]->toDateString() }}" class="form-control">
            </div>
            <div class="col-12 col-md-4 col-lg-3">
                <label for="filter-dokter" class="form-label fw-semibold">Dokter</label>
                <select id="filter-dokter" name="dokter" class="form-select">
                    <option value="">Semua</option>
                    @foreach ($doctorOptions as $slug => $name)
                        <option value="{{ $slug }}" @selected(request()->query('dokter') === $slug)>{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <label for="filter-poli" class="form-label fw-semibold">Poliklinik</label>
                <select id="filter-poli" name="poli" class="form-select">
                    <option value="">Semua</option>
                    @foreach ($clinicOptions as $slug => $name)
                        <option value="{{ $slug }}" @selected(request()->query('poli') === $slug)>{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <label for="filter-status" class="form-label fw-semibold">Status</label>
                <select id="filter-status" name="status" class="form-select">
                    <option value="">Semua</option>
                    @foreach ($statusOptions as $value => $label)
                        <option value="{{ $value }}" @selected($statusNow === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </x-slot:filters>

        <x-slot:head>
            <th scope="col">Tanggal</th>
            <th scope="col">Jam</th>
            <th scope="col">Dokter</th>
            <th scope="col">Poliklinik</th>
            <th scope="col">Terisi</th>
            <th scope="col">Status</th>
            <th scope="col">Aksi</th>
        </x-slot:head>

        @foreach ($sessions as $session)
            @php
                $ended = $session->isEnded();
                $day = $session->session_date->translatedFormat('l, j M Y');
                $label = $session->doctor->display_name.', '.$day.' '.$session->time_range;
                $deletable = $session->isManual() && (int) $session->appointments_count === 0;
            @endphp
            <tr>
                <td data-label="Tanggal">{{ $day }}</td>
                <td data-label="Jam">{{ $session->time_range }}</td>
                <td data-label="Dokter">{{ $session->doctor->display_name }}</td>
                <td data-label="Poliklinik">{{ $session->clinic->name }}</td>
                <td data-label="Terisi">{{ $session->booked_count }}/{{ $session->quota }}</td>
                <td data-label="Status">
                    <x-status-badge :status="$session->status" />
                    @if ($session->isManual())
                        <span class="badge text-bg-light border">Sesi tambahan</span>
                    @endif
                    @if ($session->status_reason)
                        <div class="small text-secondary">{{ $session->status_reason }}</div>
                    @endif
                </td>
                <td data-label="Aksi" class="table-actions">
                    @if ($ended)
                        <span class="small text-secondary">Sudah lewat</span>
                    @else
                        @can('update', $session)
                            <a href="{{ route('admin.sessions.edit', $session) }}" class="btn btn-sm btn-outline-primary" aria-label="Ubah sesi {{ $label }}">Ubah</a>
                        @endcan
                        @can('changeStatus', $session)
                            @if ($session->status === \App\Enums\SessionStatus::Open)
                                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modal-sesi-tutup" data-confirm-action="{{ route('admin.sessions.status', $session) }}" data-confirm-message="Tutup sesi {{ $label }}? Pendaftaran baru ditolak; pendaftaran yang sudah ada tetap berlaku." aria-label="Tutup sesi {{ $label }}">Tutup</button>
                            @elseif ($session->status === \App\Enums\SessionStatus::Closed)
                                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modal-sesi-buka" data-confirm-action="{{ route('admin.sessions.status', $session) }}" data-confirm-message="Buka kembali sesi {{ $label }} untuk pendaftaran?" aria-label="Buka sesi {{ $label }}">Buka</button>
                            @elseif ($session->status === \App\Enums\SessionStatus::Cancelled && $session->schedule_exception_id === null)
                                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modal-sesi-buka" data-confirm-action="{{ route('admin.sessions.status', $session) }}" data-confirm-message="Buka kembali sesi {{ $label }}?" aria-label="Buka kembali sesi {{ $label }}">Buka kembali</button>
                            @endif
                            @if ($session->status === \App\Enums\SessionStatus::Open || $session->status === \App\Enums\SessionStatus::Closed)
                                <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modal-sesi-batalkan" data-confirm-action="{{ route('admin.sessions.status', $session) }}" data-confirm-message="Batalkan sesi {{ $label }}?" aria-label="Batalkan sesi {{ $label }}">Batalkan</button>
                            @endif
                            @if ($session->status === \App\Enums\SessionStatus::NeedsReview)
                                <span class="small text-secondary">Menunggu petugas pendaftaran</span>
                            @endif
                        @endcan
                        @can('delete', $session)
                            @if ($deletable)
                                <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modal-sesi-hapus" data-confirm-action="{{ route('admin.sessions.destroy', $session) }}" data-confirm-message="Hapus sesi tambahan {{ $label }}? Tindakan ini tidak dapat dibatalkan." aria-label="Hapus sesi {{ $label }}">Hapus</button>
                            @endif
                        @endcan
                    @endif
                </td>
            </tr>
        @endforeach
    </x-data-table>

    @include('admin.sessions._status-modals')
</x-layouts.admin>
