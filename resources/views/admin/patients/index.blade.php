@php
    $canCreate = (bool) auth()->user()?->can('create', \App\Models\PatientProfile::class);
    $hasQuery = collect(request()->query())
        ->except(['page', 'per_page'])
        ->filter(fn ($value) => is_array($value) ? $value !== [] : trim((string) $value) !== '')
        ->isNotEmpty();
@endphp
<x-layouts.admin title="Pasien">
    <x-slot:breadcrumb>
        <x-breadcrumb home="admin.dashboard" :items="[['label' => 'Pasien']]" />
    </x-slot:breadcrumb>

    <x-slot:actions>
        @if ($canCreate)
            <a href="{{ route('admin.patients.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-lg" aria-hidden="true"></i> Tambah Pasien Walk-in
            </a>
        @endif
    </x-slot:actions>

    <x-data-table
        :rows="$patients"
        :preserve="[]"
        search-placeholder="Cari nama, no. pasien, telepon, atau tanggal lahir (dd/mm/yyyy)…"
        caption="Daftar pasien"
        empty-title="Belum ada pasien"
        empty-message="Profil pasien yang mendaftar online atau dibuat di loket akan tampil di sini.">
        <x-slot:filters>
            <div class="col-6 col-md-3 col-lg-2">
                <label for="filter-kanal" class="form-label fw-semibold">Kanal</label>
                <select id="filter-kanal" name="kanal" class="form-select">
                    <option value="">Semua</option>
                    @foreach (\App\Enums\BookingChannel::options() as $value => $label)
                        <option value="{{ $value }}" @selected($kanalFilter === (string) $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <label for="filter-status" class="form-label fw-semibold">Status</label>
                <select id="filter-status" name="status" class="form-select">
                    <option value="">Semua</option>
                    @foreach (['aktif' => 'Aktif', 'nonaktif' => 'Nonaktif'] as $value => $label)
                        <option value="{{ $value }}" @selected($statusFilter === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </x-slot:filters>

        <x-slot:empty>
            <x-empty-state
                :title="$hasQuery ? 'Pasien tidak ditemukan' : 'Belum ada pasien'"
                :message="$hasQuery ? 'Tidak ada pasien yang cocok dengan pencarian atau filter Anda.' : 'Profil pasien yang mendaftar online atau dibuat di loket akan tampil di sini.'"
                :icon="$hasQuery ? 'bi-search' : 'bi-people'">
                @if ($hasQuery)
                    <a href="{{ route('admin.patients.index') }}" class="btn btn-outline-primary">Hapus filter</a>
                @endif
                @if ($canCreate)
                    <a href="{{ route('admin.patients.create') }}" class="btn btn-primary">Buat profil baru</a>
                @endif
            </x-empty-state>
        </x-slot:empty>

        <x-slot:head>
            <th scope="col">No. pasien</th>
            <th scope="col">Nama</th>
            <th scope="col">Telepon</th>
            <th scope="col">Tanggal lahir</th>
            <th scope="col">Kanal</th>
            <th scope="col">Akun terhubung</th>
            <th scope="col">Status</th>
            <th scope="col">Aksi</th>
        </x-slot:head>

        @foreach ($patients as $patient)
            <tr>
                <td data-label="No. pasien">{{ $patient->patient_number ?? '—' }}</td>
                <td data-label="Nama"><a href="{{ route('admin.patients.show', $patient) }}" class="fw-semibold text-decoration-none">{{ $patient->full_name }}</a></td>
                <td data-label="Telepon">{{ $patient->phone }}</td>
                <td data-label="Tanggal lahir"><time datetime="{{ $patient->birth_date->toDateString() }}">{{ $patient->birth_date->format('d/m/Y') }}</time></td>
                <td data-label="Kanal"><x-status-badge :status="$patient->registration_channel" /></td>
                <td data-label="Akun terhubung">{{ $patient->user_id !== null ? 'Ya' : 'Tidak' }}</td>
                <td data-label="Status">
                    @if ($patient->is_active)
                        <span class="badge text-bg-success"><i class="bi bi-check-circle" aria-hidden="true"></i> Aktif</span>
                    @else
                        <span class="badge text-bg-secondary"><i class="bi bi-slash-circle" aria-hidden="true"></i> Nonaktif</span>
                    @endif
                </td>
                <td data-label="Aksi" class="table-actions">
                    <a href="{{ route('admin.patients.show', $patient) }}" class="btn btn-sm btn-outline-secondary" aria-label="Lihat {{ $patient->full_name }}">Lihat</a>
                    @can('update', $patient)
                        <a href="{{ route('admin.patients.edit', $patient) }}" class="btn btn-sm btn-outline-primary" aria-label="Ubah {{ $patient->full_name }}">Ubah</a>
                    @endcan
                </td>
            </tr>
        @endforeach
    </x-data-table>
</x-layouts.admin>
