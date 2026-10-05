@php
    $jenisNow = (is_string(request()->query('jenis')) ? request()->query('jenis') : '');
    $breadcrumbItems = [
        ['label' => 'Jadwal Praktik', 'url' => route('admin.schedules.index')],
        ['label' => 'Pengecualian'],
    ];
@endphp
<x-layouts.admin title="Jadwal Praktik">
    <x-slot:breadcrumb>
        <x-breadcrumb home="admin.dashboard" :items="$breadcrumbItems" />
    </x-slot:breadcrumb>

    <x-slot:actions>
        @can('create', \App\Models\ScheduleException::class)
            <a href="{{ route('admin.exceptions.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-lg" aria-hidden="true"></i> Tambah Pengecualian
            </a>
        @endcan
    </x-slot:actions>

    <div class="mb-3">
        <x-module-tabs group="schedules" />
    </div>

    @include('admin.schedules._review-banner')

    <x-data-table
        :rows="$exceptions"
        :preserve="[]"
        search-placeholder="Cari alasan…"
        caption="Daftar pengecualian jadwal (cuti dan libur)"
        empty-title="Belum ada cuti atau libur terdaftar"
        empty-message="Tambahkan cuti dokter, libur rumah sakit, atau libur nasional agar sesi pada tanggal tersebut ditutup.">
        <x-slot:filters>
            <div class="col-6 col-md-3 col-lg-2">
                <label for="filter-status" class="form-label fw-semibold">Waktu</label>
                <select id="filter-status" name="status" class="form-select">
                    @foreach (['mendatang' => 'Mendatang', 'lampau' => 'Sudah lewat', 'semua' => 'Semua'] as $value => $label)
                        <option value="{{ $value }}" @selected($statusFilter === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-3 col-lg-3">
                <label for="filter-jenis" class="form-label fw-semibold">Jenis</label>
                <select id="filter-jenis" name="jenis" class="form-select">
                    <option value="">Semua</option>
                    @foreach ($typeOptions as $value => $label)
                        <option value="{{ $value }}" @selected($jenisNow === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </x-slot:filters>

        <x-slot:head>
            <th scope="col">Jenis</th>
            <th scope="col">Dokter</th>
            <th scope="col">Tanggal</th>
            <th scope="col">Alasan</th>
            <th scope="col">Aksi</th>
        </x-slot:head>

        @foreach ($exceptions as $exception)
            @php
                $range = $exception->start_date->isSameDay($exception->end_date)
                    ? $exception->start_date->translatedFormat('j M Y')
                    : $exception->start_date->translatedFormat('j M Y').' – '.$exception->end_date->translatedFormat('j M Y');
                $who = $exception->doctor?->display_name ?? 'Semua dokter';
            @endphp
            <tr>
                <td data-label="Jenis"><x-status-badge :status="$exception->type" /></td>
                <td data-label="Dokter">{{ $who }}</td>
                <td data-label="Tanggal">{{ $range }}</td>
                <td data-label="Alasan">{{ $exception->reason }}</td>
                <td data-label="Aksi" class="table-actions">
                    @can('update', $exception)
                        <a href="{{ route('admin.exceptions.edit', $exception) }}" class="btn btn-sm btn-outline-primary" aria-label="Ubah pengecualian {{ $exception->type->label() }} {{ $range }}">Ubah</a>
                    @endcan
                    @can('delete', $exception)
                        <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modal-hapus-pengecualian" data-confirm-action="{{ route('admin.exceptions.destroy', $exception) }}" data-confirm-message="Hapus pengecualian {{ $exception->type->label() }} ({{ $who }}, {{ $range }})? Sesi yang dibatalkan olehnya dan belum lewat akan dibuka kembali." aria-label="Hapus pengecualian {{ $exception->type->label() }} {{ $range }}">Hapus</button>
                    @endcan
                </td>
            </tr>
        @endforeach
    </x-data-table>

    @can('create', \App\Models\ScheduleException::class)
        <x-confirm-modal
            id="modal-hapus-pengecualian"
            title="Hapus pengecualian?"
            message="Pengecualian akan dicabut."
            confirm-label="Ya, hapus"
            variant="danger"
            method="DELETE" />
    @endcan
</x-layouts.admin>
