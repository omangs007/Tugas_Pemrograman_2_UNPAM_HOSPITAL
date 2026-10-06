@php
    $currentSort = is_string(request()->query('urut')) ? request()->query('urut') : '';
    $statusNow = is_string(request()->query('status')) ? request()->query('status') : '';
    $categoryNow = is_string(request()->query('kategori')) ? request()->query('kategori') : '';
    $sortUrl = fn (string $key): string => request()->fullUrlWithQuery(['urut' => $key, 'kembali' => null, 'page' => null]);
    $breadcrumbItems = [
        ['label' => 'Konten', 'url' => route('admin.pages.index')],
        ['label' => 'FAQ'],
    ];
@endphp
<x-layouts.admin title="FAQ">
    <x-slot:breadcrumb>
        <x-breadcrumb home="admin.dashboard" :items="$breadcrumbItems" />
    </x-slot:breadcrumb>

    <x-slot:actions>
        <a href="{{ route('admin.faqs.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg" aria-hidden="true"></i> Tambah FAQ
        </a>
    </x-slot:actions>

    <div class="mb-3">
        <x-module-tabs group="content" />
    </div>

    <x-data-table
        :rows="$faqs"
        :preserve="['urut']"
        search-placeholder="Cari pertanyaan…"
        caption="Daftar FAQ"
        empty-title="Belum ada FAQ"
        empty-message="Tambahkan pertanyaan yang sering diajukan untuk halaman FAQ.">
        <x-slot:filters>
            <div class="col-12 col-md-3 col-lg-2">
                <label for="filter-kategori" class="form-label fw-semibold">Kategori</label>
                <select id="filter-kategori" name="kategori" class="form-select">
                    <option value="">Semua</option>
                    @foreach (\App\Models\Faq::CATEGORY_LABELS as $value => $label)
                        <option value="{{ $value }}" @selected($categoryNow === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
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
                @foreach (['urutan' => 'Urutan tampil', 'pertanyaan' => 'Pertanyaan'] as $key => $label)
                    <a href="{{ $sortUrl($key) }}" class="btn btn-sm {{ $currentSort === $key ? 'btn-primary' : 'btn-outline-secondary' }}"@if ($currentSort === $key) aria-current="true"@endif>{{ $label }}</a>
                @endforeach
            </nav>
        </x-slot:actions>

        <x-slot:head>
            <th scope="col">Kategori</th>
            <th scope="col">Pertanyaan</th>
            <th scope="col">Urutan</th>
            <th scope="col">Terbit</th>
            <th scope="col">Aksi</th>
        </x-slot:head>

        @foreach ($faqs as $faq)
            <tr>
                <td data-label="Kategori">{{ $faq->category_label }}</td>
                <td data-label="Pertanyaan" class="fw-semibold">{{ \Illuminate\Support\Str::limit($faq->question, 110) }}</td>
                <td data-label="Urutan">{{ $faq->sort_order }}</td>
                <td data-label="Terbit">
                    @if ($faq->is_published)
                        <span class="badge text-bg-success">Terbit</span>
                    @else
                        <span class="badge text-bg-secondary">Draf</span>
                    @endif
                </td>
                <td data-label="Aksi" class="table-actions">
                    <a href="{{ route('admin.faqs.edit', $faq) }}" class="btn btn-sm btn-outline-primary" aria-label="Ubah FAQ {{ \Illuminate\Support\Str::limit($faq->question, 60) }}">Ubah</a>
                    <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modal-hapus-faq" data-confirm-action="{{ route('admin.faqs.destroy', $faq) }}" data-confirm-message="Hapus FAQ &quot;{{ \Illuminate\Support\Str::limit($faq->question, 80) }}&quot;? Tindakan ini tidak dapat dibatalkan." aria-label="Hapus FAQ {{ \Illuminate\Support\Str::limit($faq->question, 60) }}">Hapus</button>
                </td>
            </tr>
        @endforeach
    </x-data-table>

    <x-confirm-modal
        id="modal-hapus-faq"
        title="Hapus FAQ?"
        message="FAQ akan dihapus permanen."
        confirm-label="Ya, hapus"
        variant="danger"
        method="DELETE" />
</x-layouts.admin>
