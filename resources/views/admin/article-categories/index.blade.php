@php
    $breadcrumbItems = [
        ['label' => 'Konten', 'url' => route('admin.pages.index')],
        ['label' => 'Kategori Artikel'],
    ];
@endphp
<x-layouts.admin title="Kategori Artikel">
    <x-slot:breadcrumb>
        <x-breadcrumb home="admin.dashboard" :items="$breadcrumbItems" />
    </x-slot:breadcrumb>

    <x-slot:actions>
        <a href="{{ route('admin.article-categories.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg" aria-hidden="true"></i> Tambah Kategori
        </a>
    </x-slot:actions>

    <div class="mb-3">
        <x-module-tabs group="content" />
    </div>

    <x-data-table
        :rows="$categories"
        :preserve="[]"
        search-placeholder="Cari nama kategori…"
        caption="Daftar kategori artikel"
        empty-title="Belum ada kategori artikel"
        empty-message="Tambahkan kategori untuk mengelompokkan artikel kesehatan di halaman Artikel.">
        <x-slot:head>
            <th scope="col">Nama</th>
            <th scope="col">Jumlah artikel</th>
            <th scope="col">Urutan</th>
            <th scope="col">Aksi</th>
        </x-slot:head>

        @foreach ($categories as $category)
            <tr>
                <td data-label="Nama">
                    <span class="fw-semibold">{{ $category->name }}</span>
                    @if (filled($category->description))
                        <div class="small text-secondary">{{ $category->description }}</div>
                    @endif
                </td>
                <td data-label="Jumlah artikel">{{ $category->articles_count }}</td>
                <td data-label="Urutan">{{ $category->sort_order }}</td>
                <td data-label="Aksi" class="table-actions">
                    <a href="{{ route('admin.article-categories.edit', $category) }}" class="btn btn-sm btn-outline-primary" aria-label="Ubah {{ $category->name }}">Ubah</a>
                    <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modal-hapus-kategori-artikel" data-confirm-action="{{ route('admin.article-categories.destroy', $category) }}" data-confirm-message="Hapus kategori {{ $category->name }}? Artikel di kategori ini akan menjadi tanpa kategori. Tindakan ini tidak dapat dibatalkan." aria-label="Hapus {{ $category->name }}">Hapus</button>
                </td>
            </tr>
        @endforeach
    </x-data-table>

    <x-confirm-modal
        id="modal-hapus-kategori-artikel"
        title="Hapus kategori artikel?"
        message="Kategori akan dihapus permanen."
        confirm-label="Ya, hapus"
        variant="danger"
        method="DELETE" />
</x-layouts.admin>
