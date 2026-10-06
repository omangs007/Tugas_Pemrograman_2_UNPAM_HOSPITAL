```blade
{{-- Variabel: $gallery (Gallery|null), $action (URL), $submitLabel. Tanpa input slug (F4A-R7) dan tanpa unggah foto (F4A-R23). --}}
@php($isEdit = $gallery !== null)
<form method="POST" action="{{ $action }}">
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    <x-form.errors-summary />

    <div class="row g-4">
        <div class="col-12 col-lg-8">
            <div class="card mb-4">
                <div class="card-body">
                    <x-form.input name="title" label="Judul album" :value="$gallery?->title" :required="true" maxlength="100" help="3 sampai 100 karakter. Alamat halaman (slug) dibuat otomatis dan tidak berubah saat judul diubah." />
                    <x-form.textarea name="description" label="Deskripsi" :value="$gallery?->description" :rows="3" maxlength="500" help="Opsional. Teks biasa, maksimal 500 karakter." />
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-4">
            <div class="card mb-4">
                <div class="card-header fw-semibold">Status</div>
                <div class="card-body">
                    <x-form.checkbox name="is_published" label="Terbitkan (tampil di halaman Galeri)" :checked="$gallery?->is_published ?? false" :with-hidden="true" help="Album draf tidak tampil di situs publik." />
                    <x-form.input name="sort_order" type="number" label="Urutan tampil" :value="$gallery?->sort_order ?? 0" inputmode="numeric" min="0" max="9999" help="Angka kecil tampil lebih dulu (0 sampai 9999)." />
                </div>
            </div>

            @unless ($isEdit)
                <div class="alert alert-info" role="note">Foto diunggah setelah album disimpan.</div>
            @endunless
        </div>
    </div>

    <div class="d-flex flex-wrap gap-2 justify-content-end">
        <a href="{{ route('admin.galleries.index') }}" class="btn btn-outline-secondary">Batal</a>
        <x-loading-button icon="bi-check-lg" loading-text="Menyimpan…">{{ $submitLabel }}</x-loading-button>
    </div>
</form>
