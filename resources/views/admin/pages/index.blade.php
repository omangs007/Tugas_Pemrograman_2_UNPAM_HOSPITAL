@php
    $breadcrumbItems = [
        ['label' => 'Konten', 'url' => route('admin.pages.index')],
        ['label' => 'Halaman'],
    ];
@endphp
<x-layouts.admin title="Halaman">
    <x-slot:breadcrumb>
        <x-breadcrumb home="admin.dashboard" :items="$breadcrumbItems" />
    </x-slot:breadcrumb>

    <div class="mb-3">
        <x-module-tabs group="content" />
    </div>

    <p class="text-secondary">Halaman tetap situs. Judul, subjudul, isi, dan pengaturan SEO dapat diubah; halaman tidak dapat ditambah atau dihapus.</p>

    <div class="table-responsive">
        <table class="table table-hover align-middle table-cards bg-white">
            <caption class="visually-hidden">Daftar 15 halaman tetap situs</caption>
            <thead class="table-light">
                <tr>
                    <th scope="col">Halaman</th>
                    <th scope="col">Alamat</th>
                    <th scope="col">Isi</th>
                    <th scope="col">Terakhir diubah</th>
                    <th scope="col">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($pages as $page)
                    @php
                        $routeName = \App\Models\Page::ROUTES[$page->key] ?? null;
                        $hasRoute = $routeName !== null && \Illuminate\Support\Facades\Route::has($routeName);
                    @endphp
                    <tr>
                        <td data-label="Halaman">
                            <span class="fw-semibold">{{ $page->title }}</span>
                            <div class="small text-secondary"><code>{{ $page->key }}</code></div>
                        </td>
                        <td data-label="Alamat">
                            @if ($hasRoute)
                                <code>{{ route($routeName, [], false) }}</code>
                                <a href="{{ route($routeName) }}" class="ms-2" target="_blank" rel="noopener" aria-label="Lihat halaman {{ $page->title }} di situs publik (tab baru)">Lihat</a>
                            @else
                                —
                            @endif
                        </td>
                        <td data-label="Isi">{{ filled($page->body) ? 'Ya' : '—' }}</td>
                        <td data-label="Terakhir diubah">
                            @if ($page->updated_at)
                                <time datetime="{{ $page->updated_at->toIso8601String() }}">{{ $page->updated_at->translatedFormat('j M Y H.i') }}</time>
                                @if ($page->updater)
                                    <div class="small text-secondary">oleh {{ $page->updater->name }}</div>
                                @endif
                            @else
                                —
                            @endif
                        </td>
                        <td data-label="Aksi" class="table-actions">
                            <a href="{{ route('admin.pages.edit', $page) }}" class="btn btn-sm btn-outline-primary" aria-label="Ubah {{ $page->title }}">Ubah</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-layouts.admin>
