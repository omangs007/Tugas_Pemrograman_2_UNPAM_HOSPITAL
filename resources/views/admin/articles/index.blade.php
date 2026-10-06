@php
    $currentSort = is_string(request()->query('urut')) ? request()->query('urut') : '';
    $statusNow = is_string(request()->query('status')) ? request()->query('status') : '';
    $categoryNow = is_string(request()->query('kategori')) ? request()->query('kategori') : '';
    $sortUrl = fn (string $key): string => request()->fullUrlWithQuery(['urut' => $key, 'kembali' => null, 'page' => null]);
    $breadcrumbItems = [
        ['label' => 'Konten', 'url' => route('admin.pages.index')],
        ['label' => 'Artikel'],
    ];
@endphp
<x-layouts.admin title="Artikel">
    <x-slot:breadcrumb>
        <x-breadcrumb home="admin.dashboard" :items="$breadcrumbItems" />
    </x-slot:breadcrumb>

    <x-slot:actions>
        <a href="{{ route('admin.articles.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg" aria-hidden="true"></i> Tambah Artikel
        </a>
    </x-slot:actions>

    <div class="mb-3">
        <x-module-tabs group="content" />
    </div>

    <x-data-table
        :rows="$articles"
        :preserve="['urut']"
        search-placeholder="Cari judul artikel…"
        caption="Daftar artikel kesehatan (terbit, terjadwal, dan draf)"
        empty-title="Belum ada artikel"
        empty-message="Tulis artikel kesehatan pertama agar tampil di halaman Artikel dan beranda setelah diterbitkan.">
        <x-slot:filters>
            <div class="col-6 col-md-3 col-lg-2">
                <label for="filter-status" class="form-label fw-semibold">Status</label>
                <select id="filter-status" name="status" class="form-select">
                    <option value="">Semua</option>
                    <option value="draf" @selected($statusNow === 'draf')>Draf</option>
                    <option value="terbit" @selected($statusNow === 'terbit')>Terbit</option>
                </select>
            </div>
            <div class="col-6 col-md-4 col-lg-3">
                <label for="filter-kategori" class="form-label fw-semibold">Kategori</label>
                <select id="filter-kategori" name="kategori" class="form-select">
                    <option value="">Semua</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->slug }}" @selected($categoryNow === $category->slug)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
        </x-slot:filters>

        <x-slot:actions>
            <nav aria-label="Urutkan daftar" class="d-flex flex-wrap align-items-center gap-2">
                <span class="text-secondary">Urutkan:</span>
                @foreach (['terbaru' => 'Terbaru', 'judul' => 'Judul'] as $key => $label)
                    <a href="{{ $sortUrl($key) }}" class="btn btn-sm {{ $currentSort === $key ? 'btn-primary' : 'btn-outline-secondary' }}"@if ($currentSort === $key) aria-current="true"@endif>{{ $label }}</a>
                @endforeach
            </nav>
        </x-slot:actions>

        <x-slot:head>
            <th scope="col">Judul</th>
            <th scope="col">Kategori</th>
            <th scope="col">Status</th>
            <th scope="col">Tanggal terbit</th>
            <th scope="col">Penulis</th>
            <th scope="col">Aksi</th>
        </x-slot:head>

        @foreach ($articles as $article)
            <tr>
                <td data-label="Judul"><span class="fw-semibold">{{ $article->title }}</span></td>
                <td data-label="Kategori">{{ $article->category?->name ?? 'Tanpa kategori' }}</td>
                <td data-label="Status">
                    <x-status-badge :status="$article->status" />
                    @if ($article->isScheduled())
                        <span class="badge text-bg-info ms-1">Terjadwal</span>
                    @endif
                </td>
                <td data-label="Tanggal terbit">{{ $article->published_at?->translatedFormat('d M Y H.i') ?? '—' }}</td>
                <td data-label="Penulis">{{ $article->author?->name ?? '—' }}</td>
                <td data-label="Aksi" class="table-actions">
                    <a href="{{ route('admin.articles.edit', $article) }}" class="btn btn-sm btn-outline-primary" aria-label="Ubah {{ $article->title }}">Ubah</a>
                    <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modal-hapus-artikel" data-confirm-action="{{ route('admin.articles.destroy', $article) }}" data-confirm-message="Hapus artikel {{ $article->title }}? Artikel tidak akan tampil lagi di situs dan di daftar ini." aria-label="Hapus {{ $article->title }}">Hapus</button>
                </td>
            </tr>
        @endforeach
    </x-data-table>

    <x-confirm-modal
        id="modal-hapus-artikel"
        title="Hapus artikel?"
        message="Artikel akan dihapus."
        confirm-label="Ya, hapus"
        variant="danger"
        method="DELETE" />
</x-layouts.admin>
