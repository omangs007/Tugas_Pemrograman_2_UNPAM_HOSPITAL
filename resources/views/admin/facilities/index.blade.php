@php
    $currentSort = is_string(request()->query('urut')) ? request()->query('urut') : '';
    $statusNow = is_string(request()->query('status')) ? request()->query('status') : '';
    $categoryNow = is_string(request()->query('kategori')) ? request()->query('kategori') : '';
    $sortUrl = fn (string $key): string => request()->fullUrlWithQuery(['urut' => $key, 'kembali' => null, 'page' => null]);
    // Tab pertama modul adalah Kamar dan Kapasitas (T5); sebelum route itu ada, induk jatuh ke daftar fasilitas.
    $rootUrl = \Illuminate\Support\Facades\Route::has('admin.room-categories.index') ? route('admin.room-categories.index') : route('admin.facilities.index');
    $breadcrumbItems = [
        ['label' => 'Fasilitas dan Kamar', 'url' => $rootUrl],
        ['label' => 'Fasilitas'],
    ];
@endphp
<x-layouts.admin title="Fasilitas">
    <x-slot:breadcrumb>
        <x-breadcrumb home="admin.dashboard" :items="$breadcrumbItems" />
    </x-slot:breadcrumb>

    <x-slot:actions>
        <a href="{{ route('admin.facilities.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg" aria-hidden="true"></i> Tambah Fasilitas
        </a>
    </x-slot:actions>

    <div class="mb-3">
        <x-module-tabs group="facilities" />
    </div>

    <x-data-table
        :rows="$facilities"
        :preserve="['urut']"
        search-placeholder="Cari nama fasilitas…"
        caption="Daftar fasilitas rumah sakit"
        empty-title="Belum ada fasilitas"
        empty-message="Tambahkan fasilitas medis, penunjang, atau umum yang tampil di halaman Fasilitas.">
        <x-slot:filters>
            <div class="col-12 col-md-3 col-lg-2">
                <label for="filter-status" class="form-label fw-semibold">Status</label>
                <select id="filter-status" name="status" class="form-select">
                    <option value="">Semua</option>
                    <option value="aktif" @selected($statusNow === 'aktif')>Aktif</option>
                    <option value="nonaktif" @selected($statusNow === 'nonaktif')>Nonaktif</option>
                </select>
            </div>
            <div class="col-12 col-md-3 col-lg-2">
                <label for="filter-kategori" class="form-label fw-semibold">Kategori</label>
                <select id="filter-kategori" name="kategori" class="form-select">
                    <option value="">Semua</option>
                    @foreach (\App\Enums\FacilityCategory::options() as $value => $label)
                        <option value="{{ $value }}" @selected($categoryNow === $value)>{{ $label }}</option>
                    @endforeach
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
            <th scope="col">Fasilitas</th>
            <th scope="col">Kategori</th>
            <th scope="col">Status</th>
            <th scope="col">Urutan</th>
            <th scope="col">Aksi</th>
        </x-slot:head>

        @foreach ($facilities as $facility)
            <tr>
                <td data-label="Fasilitas">
                    <div class="d-flex align-items-center gap-2">
                        <div class="admin-thumb">
                            <x-image type="facility" :media="$facility->firstMedia('cover')" variant="thumb" ratio="4x3" :decorative="true" />
                        </div>
                        <span class="fw-semibold">{{ $facility->name }}</span>
                    </div>
                </td>
                <td data-label="Kategori"><i class="bi {{ $facility->category->icon() }}" aria-hidden="true"></i> {{ $facility->category->label() }}</td>
                <td data-label="Status">
                    @if ($facility->is_active)
                        <span class="badge text-bg-success">Aktif</span>
                    @else
                        <span class="badge text-bg-secondary">Nonaktif</span>
                    @endif
                </td>
                <td data-label="Urutan">{{ $facility->sort_order }}</td>
                <td data-label="Aksi" class="table-actions">
                    <a href="{{ route('admin.facilities.edit', $facility) }}" class="btn btn-sm btn-outline-primary" aria-label="Ubah {{ $facility->name }}">Ubah</a>
                    <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modal-hapus-fasilitas" data-confirm-action="{{ route('admin.facilities.destroy', $facility) }}" data-confirm-message="Hapus {{ $facility->name }}? Gambarnya ikut terhapus. Tindakan ini tidak dapat dibatalkan. Bila hanya ingin menyembunyikannya, nonaktifkan saja lewat tombol Ubah." aria-label="Hapus {{ $facility->name }}">Hapus</button>
                </td>
            </tr>
        @endforeach
    </x-data-table>

    <x-confirm-modal
        id="modal-hapus-fasilitas"
        title="Hapus fasilitas?"
        message="Fasilitas beserta gambarnya akan dihapus permanen."
        confirm-label="Ya, hapus"
        variant="danger"
        method="DELETE" />
</x-layouts.admin>
