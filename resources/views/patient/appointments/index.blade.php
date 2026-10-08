@php
    $hasFilter = $filters !== [];
    $statusNow = $filters['status'] ?? '';
    $registerUrl = route('patient.appointments.create');
@endphp
<x-layouts.patient title="Riwayat Pendaftaran">
    <x-slot:breadcrumb>
        <x-breadcrumb home="patient.dashboard" :items="[['label' => 'Riwayat Pendaftaran']]" />
    </x-slot:breadcrumb>

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <h1 class="h3 mb-0">Riwayat Pendaftaran</h1>
        <a class="btn btn-primary" href="{{ $registerUrl }}"><i class="bi bi-calendar-plus" aria-hidden="true"></i> Daftar Kunjungan</a>
    </div>

    <x-data-table
        :rows="$appointments"
        :searchable="false"
        :preserve="[]"
        caption="Daftar pendaftaran kunjungan Anda"
        empty-title="Pendaftaran tidak ditemukan"
        empty-message="Pendaftaran kunjungan Anda akan tampil di sini.">
        <x-slot:filters>
            <div class="col-12 col-sm-6 col-lg-3">
                <label for="filter-status" class="form-label fw-semibold">Status</label>
                <select id="filter-status" name="status" class="form-select">
                    <option value="">Semua status</option>
                    @foreach ($statusOptions as $value => $label)
                        <option value="{{ $value }}" @selected($statusNow === (string) $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-sm-3 col-lg-2">
                <label for="filter-dari" class="form-label fw-semibold">Dari</label>
                <input type="date" id="filter-dari" name="dari" value="{{ $filters['dari'] ?? '' }}" class="form-control">
            </div>
            <div class="col-6 col-sm-3 col-lg-2">
                <label for="filter-sampai" class="form-label fw-semibold">Sampai</label>
                <input type="date" id="filter-sampai" name="sampai" value="{{ $filters['sampai'] ?? '' }}" class="form-control">
            </div>
            <div class="col-12 col-lg-auto">
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" id="filter-jadwal-berubah" name="jadwal_berubah" value="1" @checked(isset($filters['jadwal_berubah']))>
                    <label class="form-check-label" for="filter-jadwal-berubah">Hanya yang jadwalnya berubah</label>
                </div>
            </div>
        </x-slot:filters>

        @unless ($hasFilter)
            <x-slot:empty>
                <x-empty-state icon="bi-calendar-x" title="Belum ada pendaftaran" message="Pendaftaran kunjungan Anda akan tampil di sini.">
                    <a class="btn btn-primary" href="{{ $registerUrl }}">Daftar Kunjungan</a>
                </x-empty-state>
            </x-slot:empty>
        @endunless

        <x-slot:head>
            <th scope="col">Kode</th>
            <th scope="col">Tanggal</th>
            <th scope="col">Dokter</th>
            <th scope="col">Antrean</th>
            <th scope="col">Status</th>
        </x-slot:head>

        @foreach ($appointments as $appointment)
            <tr>
                <td data-label="Kode">
                    <a class="fw-semibold" href="{{ route('patient.appointments.show', $appointment->booking_code) }}" aria-label="Detail pendaftaran {{ $appointment->booking_code }}">{{ $appointment->booking_code }}</a>
                </td>
                <td data-label="Tanggal">
                    <time datetime="{{ $appointment->session_date->toDateString() }}">{{ $appointment->session_date->translatedFormat('j M y') }}</time>
                    <span class="d-block small text-body-secondary">{{ $appointment->practiceSession->time_range }}</span>
                </td>
                <td data-label="Dokter">
                    {{ $appointment->doctor->display_name }}
                    <span class="d-block small text-body-secondary">{{ $appointment->clinic->name }}</span>
                </td>
                <td data-label="Antrean">{{ $appointment->queue_label }}</td>
                <td data-label="Status">
                    <x-status-badge :status="$appointment->status" />
                    @if ($appointment->schedule_changed_at)
                        <span class="badge text-bg-warning ms-1"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i> Jadwal berubah</span>
                    @endif
                </td>
            </tr>
        @endforeach
    </x-data-table>
</x-layouts.patient>
