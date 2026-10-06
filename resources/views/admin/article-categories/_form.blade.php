{{-- Variabel: $category (ArticleCategory|null), $action, $submitLabel. Tanpa input slug (F4A-R7). --}}
@php($isEdit = $category !== null)
<form method="POST" action="{{ $action }}">
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    <x-form.errors-summary />

    <div class="card mb-4">
        <div class="card-body">
            <x-form.input name="name" label="Nama kategori" :value="$category?->name" :required="true" maxlength="100" help="Alamat kategori (slug) dibuat otomatis dan tidak berubah saat nama diubah." />
            <x-form.textarea name="description" label="Deskripsi" :value="$category?->description" :rows="2" maxlength="255" help="Opsional. Teks biasa tanpa tag HTML." />
            <x-form.input name="sort_order" type="number" label="Urutan tampil" :value="$category?->sort_order ?? 0" inputmode="numeric" min="0" max="9999" help="Angka kecil tampil lebih dulu." />
        </div>
    </div>

    <div class="d-flex flex-wrap gap-2 justify-content-end">
        <a href="{{ route('admin.article-categories.index') }}" class="btn btn-outline-secondary">Batal</a>
        <x-loading-button icon="bi-check-lg" loading-text="Menyimpan…">{{ $submitLabel }}</x-loading-button>
    </div>
</form>
