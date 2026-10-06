@php
    $currentSort = is_string(request()->query('urut')) ? request()->query('urut') : '';
    $statusNow = is_string(request()->query('status')) ? request()->query('status') : '';
    $sortUrl = fn (string $key): string => request()->fullUrlWithQuery(['urut' => $key, 'kembali' => null, 'page' => null]);
    $breadcrumbItems = [
        ['label' => 'Konten', 'url' => route('admin.pages.index')],
        ['label' => 'Banner'],
    ];
    $formatAt = fn (?\Carbon\CarbonInterface $at): ?string => $at?->translatedFormat('d M Y H.i');
    $periodLabel = function (\App\Models\Banner $banner) use ($formatAt): string {
        $start = $formatAt($banner->starts_at);
        $end = $formatAt($banner->ends_at);

        return match (true) {
            $start !== null && $end !== null => $start.' – '.$end,
            $start !== null => 'Mulai '.$start,
            $end !== null => 'Hingga '.$end,
            default => 'Tanpa batas',
        };
    };
    $badgeColors = ['Tayang' => 'success', 'Terjadwal' => 'info', 'Berakhir' => 'secondary', 'Nonaktif' => 'secondary'];
@endphp
<x-layouts.admin title="Banner">
    <x-slot:breadcrumb>
        <x-breadcrumb home="admin.dashboard" :items="$breadcrumbItems" />
    </x-slot:breadcrumb>

    <x-slot:actions>
        <a href="{{ route('admin.banners.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg" aria-hidden="true"></i> Tambah Banner
        </a>
    </x-slot:actions>

    <div class="mb-3">
        <x-module-tabs group="content" />
    </div>

    <x-data-table
        :rows="$banners"
        :preserve="['urut']"
        search-placeholder="Cari judul banner…"
        caption="Daftar banner beranda"
        empty-title="Belum ada banner"
        empty-message="Tambahkan banner agar carousel di beranda menampilkan informasi terbaru. Tanpa banner aktif, beranda memakai tampilan statis.">
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
                @foreach (['urutan' => 'Urutan tampil', 'judul' => 'Judul'] as $key => $label)
                    <a href="{{ $sortUrl($key) }}" class="btn btn-sm {{ $currentSort === $key ? 'btn-primary' : 'btn-outline-secondary' }}"@if ($currentSort === $key) aria-current="true"@endif>{{ $label }}</a>
                @endforeach
            </nav>
        </x-slot:actions>

        <x-slot:head>
            <th scope="col">Gambar</th>
            <th scope="col">Judul</th>
            <th scope="col">Urutan</th>
            <th scope="col">Periode</th>
            <th scope="col">Status</th>
            <th scope="col">Aksi</th>
        </x-slot:head>

        @foreach ($banners as $banner)
            @php($status = $banner->scheduleLabel())
            <tr>
                <td data-label="Gambar">
                    <div class="admin-thumb">
                        <x-image type="banner" :media="$banner->firstMedia('image')" variant="thumb" ratio="3x1" :decorative="true" />
                    </div>
                </td>
                <td data-label="Judul"><span class="fw-semibold">{{ $banner->title }}</span></td>
                <td data-label="Urutan">{{ $banner->sort_order }}</td>
                <td data-label="Periode">{{ $periodLabel($banner) }}</td>
                <td data-label="Status"><span class="badge text-bg-{{ $badgeColors[$status] ?? 'secondary' }}">{{ $status }}</span></td>
                <td data-label="Aksi" class="table-actions">
                    <a href="{{ route('admin.banners.edit', $banner) }}" class="btn btn-sm btn-outline-primary" aria-label="Ubah {{ $banner->title }}">Ubah</a>
                    <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modal-hapus-banner" data-confirm-action="{{ route('admin.banners.destroy', $banner) }}" data-confirm-message="Hapus banner {{ $banner->title }}? Gambarnya ikut terhapus. Tindakan ini tidak dapat dibatalkan." aria-label="Hapus {{ $banner->title }}">Hapus</button>
                </td>
            </tr>
        @endforeach
    </x-data-table>

    <x-confirm-modal
        id="modal-hapus-banner"
        title="Hapus banner?"
        message="Banner akan dihapus permanen."
        confirm-label="Ya, hapus"
        variant="danger"
        method="DELETE" />
</x-layouts.admin>
