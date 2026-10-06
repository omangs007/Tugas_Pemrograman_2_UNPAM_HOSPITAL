@php
    $routeName = \App\Models\Page::ROUTES[$page->key] ?? null;
    $hasRoute = $routeName !== null && \Illuminate\Support\Facades\Route::has($routeName);
    $breadcrumbItems = [
        ['label' => 'Konten', 'url' => route('admin.pages.index')],
        ['label' => 'Halaman', 'url' => route('admin.pages.index')],
        ['label' => 'Ubah '.$page->title],
    ];
@endphp
<x-layouts.admin :title="'Ubah '.$page->title">
    <x-slot:breadcrumb>
        <x-breadcrumb home="admin.dashboard" :items="$breadcrumbItems" />
    </x-slot:breadcrumb>

    @if ($hasRoute)
        <x-slot:actions>
            <a href="{{ route($routeName) }}" class="btn btn-outline-secondary" target="_blank" rel="noopener">
                <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i> Lihat Halaman
            </a>
        </x-slot:actions>
    @endif

    <form method="POST" action="{{ route('admin.pages.update', $page) }}">
        @csrf
        @method('PUT')

        <x-form.errors-summary />

        <div class="card mb-4">
            <div class="card-body">
                <div class="mb-3">
                    <label for="page-key" class="form-label fw-semibold">Kunci halaman</label>
                    <input type="text" id="page-key" value="{{ $page->key }}" readonly class="form-control" aria-describedby="page-key-help">
                    <div id="page-key-help" class="form-text">Kunci halaman tetap dan tidak dapat diubah.@if ($hasRoute) Alamat di situs: {{ route($routeName, [], false) }}.@endif</div>
                </div>

                <x-form.input name="title" label="Judul" :value="$page->title" :required="true" maxlength="150" />
                <x-form.input name="subtitle" label="Subjudul" :value="$page->subtitle" maxlength="255" />

                @if ($page->hasBody())
                    <x-form.rich-text name="body" label="Isi halaman" :value="$page->body" :maxlength="100000" help="Gambar disisipkan lewat tombol gambar di editor dan wajib diberi teks alternatif. Isi dibersihkan otomatis saat disimpan." />
                @else
                    <p class="text-secondary mb-3">Halaman ini tidak memiliki isi yang dapat diedit.</p>
                @endif

                <details class="mb-0" @if ($errors->has('meta_title') || $errors->has('meta_description')) open @endif>
                    <summary class="fw-semibold mb-2">Pengaturan SEO (opsional)</summary>
                    <x-form.input name="meta_title" label="Meta title" :value="$page->meta_title" maxlength="70" help="Maksimal 70 karakter. Kosong = memakai judul halaman." />
                    <x-form.textarea name="meta_description" label="Meta description" :value="$page->meta_description" :rows="2" maxlength="160" help="Maksimal 160 karakter. Kosong = memakai subjudul halaman." />
                </details>
            </div>
        </div>

        <div class="d-flex flex-wrap gap-2 justify-content-end">
            <a href="{{ route('admin.pages.index') }}" class="btn btn-outline-secondary">Batal</a>
            <x-loading-button icon="bi-check-lg" loading-text="Menyimpan…">Simpan Perubahan</x-loading-button>
        </div>
    </form>
</x-layouts.admin>
