@php
    $currentSort = (is_string(request()->query('urut')) ? request()->query('urut') : '');
    $statusNow = (is_string(request()->query('status')) ? request()->query('status') : '');
    $sortUrl = fn (string $key): string => request()->fullUrlWithQuery(['urut' => $key, 'kembali' => null, 'page' => null]);
    $breadcrumbItems = [
        ['label' => 'Jadwal Praktik', 'url' => route('admin.schedules.index')],
        ['label' => 'Templat Mingguan'],
    ];
@endphp
<x-layouts.admin title="Jadwal Praktik">
    <x-slot:breadcrumb>
        <x-breadcrumb home="admin.dashboard" :items="$breadcrumbItems" />
    </x-slot:breadcrumb>

    <x-slot:actions>
        @can('create', \App\Models\DoctorSchedule::class)
            <a href="{{ route('admin.schedules.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-lg" aria-hidden="true"></i> Tambah Templat
            </a>
        @endcan
    </x-slot:actions>

    <div class="mb-3">
        <x-module-tabs group="schedules" />
    </div>

    @include('admin.schedules._review-banner')

    <x-data-table
        :rows="$schedules"
        :searchable="false"
        :preserve="['urut']"
        caption="Daftar templat jadwal mingguan"
        empty-title="Belum ada templat jadwal"
        empty-message="Tambahkan templat mingguan agar sesi praktik dibangkitkan otomatis.">
        <x-slot:filters>
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
                <label for="filter-hari" class="form-label fw-semibold">Hari</label>
                <select id="filter-hari" name="hari" class="form-select">
                    <option value="">Semua</option>
                    @foreach ($dayOptions as $number => $name)
                        <option value="{{ $number }}" @selected((is_string(request()->query('hari')) ? request()->query('hari') : '') === (string) $number)>{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <label for="filter-status" class="form-label fw-semibold">Status</label>
                <select id="filter-status" name="status" class="form-select">
                    <option value="">Semua</option>
                    <option value="aktif" @selected($statusNow === 'aktif')>Aktif</option>
                    <option value="nonaktif" @selected($statusNow === 'nonaktif')>Nonaktif</option>
                </select>
            </div>
        </x-slot:filters>

        <x-slot:actions>
            <nav aria-label="Urutkan daftar" class="d-flex flex-wrap align-items-center gap-2">
                <span class="text-secondary">Urutkan:</span>
                @foreach (['dokter' => 'Dokter', 'hari' => 'Hari'] as $key => $label)
                    <a href="{{ $sortUrl($key) }}" class="btn btn-sm {{ $currentSort === $key ? 'btn-primary' : 'btn-outline-secondary' }}"@if ($currentSort === $key) aria-current="true"@endif>{{ $label }}</a>
                @endforeach
            </nav>
        </x-slot:actions>

        <x-slot:head>
            <th scope="col">Dokter</th>
            <th scope="col">Poliklinik</th>
            <th scope="col">Hari</th>
            <th scope="col">Jam</th>
            <th scope="col">Kuota</th>
            <th scope="col">Berlaku</th>
            <th scope="col">Status</th>
            <th scope="col">Aksi</th>
        </x-slot:head>

        @foreach ($schedules as $schedule)
            <tr>
                <td data-label="Dokter"><span class="fw-semibold">{{ $schedule->doctor->display_name }}</span></td>
                <td data-label="Poliklinik">{{ $schedule->clinic->name }}</td>
                <td data-label="Hari">{{ $schedule->day_name }}</td>
                <td data-label="Jam">{{ $schedule->time_range }}</td>
                <td data-label="Kuota">{{ $schedule->quota }}</td>
                <td data-label="Berlaku">
                    {{ $schedule->valid_from->translatedFormat('j M Y') }}
                    –
                    {{ $schedule->valid_until === null ? 'terbuka' : $schedule->valid_until->translatedFormat('j M Y') }}
                </td>
                <td data-label="Status">
                    @if ($schedule->is_active)
                        <span class="badge text-bg-success">Aktif</span>
                    @else
                        <span class="badge text-bg-secondary">Nonaktif</span>
                    @endif
                </td>
                <td data-label="Aksi" class="table-actions">
                    @can('update', $schedule)
                        <a href="{{ route('admin.schedules.edit', $schedule) }}" class="btn btn-sm btn-outline-primary" aria-label="Ubah templat {{ $schedule->doctor->display_name }} hari {{ $schedule->day_name }} {{ $schedule->time_range }}">Ubah</a>
                    @endcan
                    @can('delete', $schedule)
                        <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modal-hapus-templat" data-confirm-action="{{ route('admin.schedules.destroy', $schedule) }}" data-confirm-message="Hapus templat {{ $schedule->doctor->display_name }}, {{ $schedule->day_name }} {{ $schedule->time_range }}? Sesi mendatang tanpa pendaftaran dari templat ini ikut dihapus. Templat yang sudah memiliki sesi dengan pendaftaran tidak dapat dihapus, hanya dinonaktifkan." aria-label="Hapus templat {{ $schedule->doctor->display_name }} hari {{ $schedule->day_name }} {{ $schedule->time_range }}">Hapus</button>
                    @endcan
                </td>
            </tr>
        @endforeach
    </x-data-table>

    @can('create', \App\Models\DoctorSchedule::class)
        <x-confirm-modal
            id="modal-hapus-templat"
            title="Hapus templat jadwal?"
            message="Templat jadwal akan dihapus."
            confirm-label="Ya, hapus"
            variant="danger"
            method="DELETE" />
    @endcan
</x-layouts.admin>
