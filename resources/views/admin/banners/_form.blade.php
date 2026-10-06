{{-- Variabel: $banner (Banner|null), $action, $submitLabel. Tanpa input slug; periode memakai datetime-local (F4A-R26). --}}
@php
    $isEdit = $banner !== null;
    $currentImage = $banner?->firstMedia('image');
@endphp
<form method="POST" action="{{ $action }}" enctype="multipart/form-data">
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    <x-form.errors-summary />

    <div class="row g-4">
        <div class="col-12 col-lg-8">
            <div class="card mb-4">
                <div class="card-body">
                    <x-form.input name="title" label="Judul banner" :value="$banner?->title" :required="true" maxlength="100" />
                    <x-form.input name="subtitle" label="Subjudul" :value="$banner?->subtitle" maxlength="200" help="Opsional. Tampil di bawah judul pada carousel beranda." />
                    <div class="row">
                        <div class="col-12 col-md-5">
                            <x-form.input name="button_label" label="Label tombol" :value="$banner?->button_label" maxlength="30" help="Opsional. Wajib diisi bila tautan diisi." />
                        </div>
                        <div class="col-12 col-md-7">
                            <x-form.input name="button_url" label="Tautan tombol" :value="$banner?->button_url" maxlength="255" autocomplete="off" help="Alamat internal diawali / (mis. /dokter) atau URL https. Wajib diisi bila label diisi." />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-4">
            <div class="card mb-4">
                <div class="card-header fw-semibold">Tayang</div>
                <div class="card-body">
                    <x-form.checkbox name="is_active" label="Aktif (tampil di beranda)" :checked="$banner?->is_active ?? true" :with-hidden="true" />
                    <x-form.input name="sort_order" type="number" label="Urutan tampil" :value="$banner?->sort_order ?? 0" inputmode="numeric" min="0" max="9999" help="Angka kecil tampil lebih dulu." />
                    <x-form.input name="starts_at" type="datetime-local" label="Mulai tayang" :value="$banner?->starts_at?->format('Y-m-d\TH:i')" help="Kosong = langsung tayang. Waktu mengikuti zona Asia/Jakarta." />
                    <x-form.input name="ends_at" type="datetime-local" label="Selesai tayang" :value="$banner?->ends_at?->format('Y-m-d\TH:i')" help="Kosong = tanpa batas akhir. Harus setelah waktu mulai." />
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header fw-semibold">Gambar banner</div>
                <div class="card-body">
                    <x-form.file name="image" label="Gambar banner" :required="! $isEdit" :max-kb="4096" :current-media="$currentImage" alt-name="image_alt" :alt-value="old('image_alt', $currentImage?->alt_text)" help="Minimal 1200 x 400 piksel (disarankan 1600 x 600), maksimal 4 MB. Tanpa gambar, beranda memakai gambar pengganti." />
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex flex-wrap gap-2 justify-content-end">
        <a href="{{ route('admin.banners.index') }}" class="btn btn-outline-secondary">Batal</a>
        <x-loading-button icon="bi-check-lg" loading-text="Menyimpan…">{{ $submitLabel }}</x-loading-button>
    </div>
</form>
