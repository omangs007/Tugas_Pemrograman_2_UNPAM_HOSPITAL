@php
    $currentSort = is_string(request()->query('urut')) ? request()->query('urut') : '';
    $statusNow = is_string(request()->query('status')) ? request()->query('status') : '';
    $sortUrl = fn (string $key): string => request()->fullUrlWithQuery(['urut' => $key, 'kembali' => null, 'page' => null]);
    $breadcrumbItems = [
        [
            'label' => 'Poliklinik dan Spesialisasi',
            'url' => \Illuminate\Support\Facades\Route::has('admin.clinics.index') ? route('admin.clinics.index') : null,
        ],
        ['label' => 'Spesialisasi'],
    ];
@endphp
<x-layouts.admin title="Poliklinik dan Spesialisasi">
    <x-slot:breadcrumb>
        <x-breadcrumb home="admin.dashboard" :items="$breadcrumbItems" />
    </x-slot:breadcrumb>

    <x-slot:actions>
        <a href="{{ route('admin.specialties.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg" aria-hidden="true"></i> Tambah Spesialisasi
        </a>
    </x-slot:actions>

    <div class="mb-3">
        <x-module-tabs group="clinics" />
    </div>

    <x-data-table
        :rows="$specialties"
        :preserve="['urut']"
        search-placeholder="Cari nama spesialisasi…"
        caption="Daftar spesialisasi"
        empty-title="Belum ada spesialisasi"
        empty-message="Tambahkan spesialisasi agar dapat dipilih pada data dokter dan poliklinik.">
        <x-slot:filters>
            <div class="col-12 col-md-3 col-lg-2">
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
                @foreach (['nama' => 'Nama', 'urutan' => 'Urutan tampil'] as $key => $label)
                    <a href="{{ $sortUrl($key) }}" class="btn btn-sm {{ $currentSort === $key ? 'btn-primary' : 'btn-outline-secondary' }}"@if ($currentSort === $key) aria-current="true"@endif>{{ $label }}</a>
                @endforeach
            </nav>
        </x-slot:actions>

        <x-slot:head>
            <th scope="col">Nama</th>
            <th scope="col">Dokter</th>
            <th scope="col">Poliklinik</th>
            <th scope="col">Status</th>
            <th scope="col">Urutan</th>
            <th scope="col">Aksi</th>
        </x-slot:head>

        @foreach ($specialties as $specialty)
            @php($inUse = $specialty->doctors_count + $specialty->clinics_count > 0)
            <tr>
                <td data-label="Nama">
                    <span class="fw-semibold">{{ $specialty->name }}</span>
                    @if ($specialty->description)
                        <div class="small text-secondary">{{ \Illuminate\Support\Str::limit($specialty->description, 90) }}</div>
                    @endif
                </td>
                <td data-label="Dokter">{{ $specialty->doctors_count }}</td>
                <td data-label="Poliklinik">{{ $specialty->clinics_count }}</td>
                <td data-label="Status">
                    @if ($specialty->is_active)
                        <span class="badge text-bg-success">Aktif</span>
                    @else
                        <span class="badge text-bg-secondary">Nonaktif</span>
                    @endif
                </td>
                <td data-label="Urutan">{{ $specialty->sort_order }}</td>
                <td data-label="Aksi" class="table-actions">
                    <a href="{{ route('admin.specialties.edit', $specialty) }}" class="btn btn-sm btn-outline-primary" aria-label="Ubah spesialisasi {{ $specialty->name }}">Ubah</a>
                    @if ($inUse)
                        <a href="{{ route('admin.specialties.edit', $specialty) }}" class="btn btn-sm btn-outline-secondary" aria-label="Nonaktifkan spesialisasi {{ $specialty->name }} (masih dipakai)">Nonaktifkan</a>
                        <span class="small text-secondary align-self-center">Masih dipakai oleh {{ $specialty->doctors_count }} dokter dan {{ $specialty->clinics_count }} poliklinik.</span>
                    @else
                        <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modal-hapus-spesialisasi" data-confirm-action="{{ route('admin.specialties.destroy', $specialty) }}" data-confirm-message="Hapus spesialisasi {{ $specialty->name }}? Tindakan ini tidak dapat dibatalkan." aria-label="Hapus spesialisasi {{ $specialty->name }}">Hapus</button>
                    @endif
                </td>
            </tr>
        @endforeach
    </x-data-table>

    <x-confirm-modal
        id="modal-hapus-spesialisasi"
        title="Hapus spesialisasi?"
        message="Spesialisasi akan dihapus permanen."
        confirm-label="Ya, hapus"
        variant="danger"
        method="DELETE" />
</x-layouts.admin>
