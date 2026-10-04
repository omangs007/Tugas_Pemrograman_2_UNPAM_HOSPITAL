@php
    $currentSort = (string) request()->query('urut', '');
    $statusNow = (string) request()->query('status', '');
    $sortUrl = fn (string $key): string => request()->fullUrlWithQuery(['urut' => $key, 'kembali' => null, 'page' => null]);
    $breadcrumbItems = [
        ['label' => 'Poliklinik dan Spesialisasi', 'url' => route('admin.clinics.index')],
        ['label' => 'Poliklinik'],
    ];
@endphp
<x-layouts.admin title="Poliklinik dan Spesialisasi">
    <x-slot:breadcrumb>
        <x-breadcrumb home="admin.dashboard" :items="$breadcrumbItems" />
    </x-slot:breadcrumb>

    <x-slot:actions>
        <a href="{{ route('admin.clinics.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg" aria-hidden="true"></i> Tambah Poliklinik
        </a>
    </x-slot:actions>

    <div class="mb-3">
        <x-module-tabs group="clinics" />
    </div>

    <x-data-table
        :rows="$clinics"
        :preserve="['urut']"
        search-placeholder="Cari nama atau kode…"
        caption="Daftar poliklinik dan layanan"
        empty-title="Belum ada poliklinik"
        empty-message="Tambahkan poliklinik atau layanan penunjang yang tampil di halaman Layanan.">
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
            <th scope="col">Poliklinik</th>
            <th scope="col">Kode</th>
            <th scope="col">Jenis</th>
            <th scope="col">Dapat didaftar</th>
            <th scope="col">Spesialisasi</th>
            <th scope="col">Lokasi</th>
            <th scope="col">Dokter</th>
            <th scope="col">Status</th>
            <th scope="col">Urutan</th>
            <th scope="col">Aksi</th>
        </x-slot:head>

        @foreach ($clinics as $clinic)
            <tr>
                <td data-label="Poliklinik">
                    <div class="d-flex align-items-center gap-2">
                        <div class="admin-thumb">
                            <x-image type="clinic" :media="$clinic->firstMedia('cover')" variant="thumb" ratio="4x3" :decorative="true" />
                        </div>
                        <span class="fw-semibold">{{ $clinic->name }}</span>
                    </div>
                </td>
                <td data-label="Kode"><code>{{ $clinic->code }}</code></td>
                <td data-label="Jenis">{{ $clinic->type->label() }}</td>
                <td data-label="Dapat didaftar">{{ $clinic->is_bookable ? 'Ya' : 'Tidak' }}</td>
                <td data-label="Spesialisasi">{{ $clinic->specialty?->name ?? '—' }}</td>
                <td data-label="Lokasi">{{ $clinic->location }}</td>
                <td data-label="Dokter">{{ $clinic->doctors_count }}</td>
                <td data-label="Status">
                    @if ($clinic->is_active)
                        <span class="badge text-bg-success">Aktif</span>
                    @else
                        <span class="badge text-bg-secondary">Nonaktif</span>
                    @endif
                </td>
                <td data-label="Urutan">{{ $clinic->sort_order }}</td>
                <td data-label="Aksi" class="table-actions">
                    <a href="{{ route('admin.clinics.edit', $clinic) }}" class="btn btn-sm btn-outline-primary" aria-label="Ubah {{ $clinic->name }}">Ubah</a>
                    @if ($clinic->doctors_count > 0)
                        <a href="{{ route('admin.clinics.edit', $clinic) }}" class="btn btn-sm btn-outline-secondary" aria-label="Nonaktifkan {{ $clinic->name }} (masih dirujuk)">Nonaktifkan</a>
                        <span class="small text-secondary align-self-center">Masih dirujuk oleh {{ $clinic->doctors_count }} dokter.</span>
                    @else
                        <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modal-hapus-poliklinik" data-confirm-action="{{ route('admin.clinics.destroy', $clinic) }}" data-confirm-message="Hapus {{ $clinic->name }}? Foto sampul dan galerinya ikut terhapus. Tindakan ini tidak dapat dibatalkan." aria-label="Hapus {{ $clinic->name }}">Hapus</button>
                    @endif
                </td>
            </tr>
        @endforeach
    </x-data-table>

    <x-confirm-modal
        id="modal-hapus-poliklinik"
        title="Hapus poliklinik?"
        message="Poliklinik akan dihapus permanen."
        confirm-label="Ya, hapus"
        variant="danger"
        method="DELETE" />
</x-layouts.admin>
