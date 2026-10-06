@php
    $currentSort = is_string(request()->query('urut')) ? request()->query('urut') : '';
    $statusNow = is_string(request()->query('status')) ? request()->query('status') : '';
    $sortUrl = fn (string $key): string => request()->fullUrlWithQuery(['urut' => $key, 'kembali' => null, 'page' => null]);
    $breadcrumbItems = [
        ['label' => 'Konten', 'url' => route('admin.pages.index')],
        ['label' => 'Mitra Asuransi'],
    ];
@endphp
<x-layouts.admin title="Mitra Asuransi">
    <x-slot:breadcrumb>
        <x-breadcrumb home="admin.dashboard" :items="$breadcrumbItems" />
    </x-slot:breadcrumb>

    <x-slot:actions>
        <a href="{{ route('admin.insurance-partners.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg" aria-hidden="true"></i> Tambah Mitra
        </a>
    </x-slot:actions>

    <div class="mb-3">
        <x-module-tabs group="content" />
    </div>

    <x-data-table
        :rows="$partners"
        :preserve="['urut']"
        search-placeholder="Cari nama mitra…"
        caption="Daftar mitra asuransi dan perusahaan"
        empty-title="Belum ada mitra asuransi"
        empty-message="Tambahkan mitra contoh yang tampil di halaman BPJS dan Asuransi.">
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
            <th scope="col">Logo</th>
            <th scope="col">Nama</th>
            <th scope="col">Jenis</th>
            <th scope="col">Status</th>
            <th scope="col">Urutan</th>
            <th scope="col">Aksi</th>
        </x-slot:head>

        @foreach ($partners as $partner)
            <tr>
                <td data-label="Logo">
                    <div class="admin-thumb">
                        <x-image type="logo" :media="$partner->firstMedia('logo')" variant="thumb" :decorative="true" />
                    </div>
                </td>
                <td data-label="Nama" class="fw-semibold">{{ $partner->name }}</td>
                <td data-label="Jenis">{{ $partner->type_label }}</td>
                <td data-label="Status">
                    @if ($partner->is_active)
                        <span class="badge text-bg-success">Aktif</span>
                    @else
                        <span class="badge text-bg-secondary">Nonaktif</span>
                    @endif
                </td>
                <td data-label="Urutan">{{ $partner->sort_order }}</td>
                <td data-label="Aksi" class="table-actions">
                    <a href="{{ route('admin.insurance-partners.edit', $partner) }}" class="btn btn-sm btn-outline-primary" aria-label="Ubah {{ $partner->name }}">Ubah</a>
                    <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modal-hapus-mitra" data-confirm-action="{{ route('admin.insurance-partners.destroy', $partner) }}" data-confirm-message="Hapus {{ $partner->name }}? Logonya ikut terhapus. Mitra yang masih dipakai pendaftaran tidak dapat dihapus; nonaktifkan saja. Tindakan ini tidak dapat dibatalkan." aria-label="Hapus {{ $partner->name }}">Hapus</button>
                </td>
            </tr>
        @endforeach
    </x-data-table>

    <x-confirm-modal
        id="modal-hapus-mitra"
        title="Hapus mitra?"
        message="Mitra akan dihapus permanen."
        confirm-label="Ya, hapus"
        variant="danger"
        method="DELETE" />
</x-layouts.admin>
