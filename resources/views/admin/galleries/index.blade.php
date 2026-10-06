@php
    $currentSort = is_string(request()->query('urut')) ? request()->query('urut') : '';
    $statusNow = is_string(request()->query('status')) ? request()->query('status') : '';
    $sortUrl = fn (string $key): string => request()->fullUrlWithQuery(['urut' => $key, 'kembali' => null, 'page' => null]);
    $breadcrumbItems = [
        ['label' => 'Konten', 'url' => route('admin.pages.index')],
        ['label' => 'Galeri'],
    ];
@endphp
<x-layouts.admin title="Galeri">
    <x-slot:breadcrumb>
        <x-breadcrumb home="admin.dashboard" :items="$breadcrumbItems" />
    </x-slot:breadcrumb>

    <x-slot:actions>
        <a href="{{ route('admin.galleries.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg" aria-hidden="true"></i> Tambah Album
        </a>
    </x-slot:actions>

    <div class="mb-3">
        <x-module-tabs group="content" />
    </div>

    <x-data-table
        :rows="$galleries"
        :preserve="['urut']"
        search-placeholder="Cari judul album…"
        caption="Daftar album galeri"
        empty-title="Belum ada album galeri"
        empty-message="Tambahkan album foto yang tampil di halaman Galeri.">
        <x-slot:filters>
            <div class="col-12 col-md-3 col-lg-2">
                <label for="filter-status" class="form-label fw-semibold">Status</label>
                <select id="filter-status" name="status" class="form-select">
                    <option value="">Semua</option>
                    <option value="terbit" @selected($statusNow === 'terbit')>Terbit</option>
                    <option value="draf" @selected($statusNow === 'draf')>Draf</option>
                </select>
            </div>
        </x-slot:filters>

        <x-slot:actions>
            <nav aria-label="Urutkan daftar" class="d-flex flex-wrap align-items-center gap-2">
                <span class="text-secondary">Urutkan:</span>
                @foreach (['urutan' => 'Urutan tampil', 'judul' => 'Judul'] as $key => $label)
                    <a href="{{ $sortUrl($key) }}" class="btn btn-sm {{ $currentSort === $key ? 'btn-primary' : 'btn-outline-secondary' }}"@if ($currentSort === $key) aria-current="true"@endif>{{ $label }}</a>
                @endforeach
            </nav>
        </x-slot:actions>

        <x-slot:head>
            <th scope="col">Album</th>
            <th scope="col">Jumlah foto</th>
            <th scope="col">Terbit</th>
            <th scope="col">Urutan</th>
            <th scope="col">Aksi</th>
        </x-slot:head>

        @foreach ($galleries as $gallery)
            <tr>
                <td data-label="Album">
                    <div class="d-flex align-items-center gap-2">
                        <div class="admin-thumb">
                            <x-image type="gallery" :media="$gallery->coverMedia()" variant="thumb" ratio="4x3" :decorative="true" />
                        </div>
                        <span class="fw-semibold">{{ $gallery->title }}</span>
                    </div>
                </td>
                <td data-label="Jumlah foto">{{ $gallery->photos_count }}</td>
                <td data-label="Terbit">
                    @if ($gallery->is_published)
                        <span class="badge text-bg-success">Terbit</span>
                    @else
                        <span class="badge text-bg-secondary">Draf</span>
                    @endif
                </td>
                <td data-label="Urutan">{{ $gallery->sort_order }}</td>
                <td data-label="Aksi" class="table-actions">
                    <a href="{{ route('admin.galleries.edit', $gallery) }}" class="btn btn-sm btn-outline-primary" aria-label="Ubah {{ $gallery->title }}">Ubah</a>
                    <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modal-hapus-album" data-confirm-action="{{ route('admin.galleries.destroy', $gallery) }}" data-confirm-message="Hapus album {{ $gallery->title }}? Seluruh fotonya ikut terhapus. Tindakan ini tidak dapat dibatalkan." aria-label="Hapus {{ $gallery->title }}">Hapus</button>
                </td>
            </tr>
        @endforeach
    </x-data-table>

    <x-confirm-modal
        id="modal-hapus-album"
        title="Hapus album?"
        message="Album dan seluruh fotonya akan dihapus permanen."
        confirm-label="Ya, hapus"
        variant="danger"
        method="DELETE" />
</x-layouts.admin>
