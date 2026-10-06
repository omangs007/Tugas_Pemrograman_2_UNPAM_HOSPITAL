{{-- Variabel: $partner (InsurancePartner|null), $action (URL), $submitLabel. Tanpa input slug (F4A-R7). --}}
@php($isEdit = $partner !== null)
<form method="POST" action="{{ $action }}" enctype="multipart/form-data">
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    <x-form.errors-summary />

    <div class="alert alert-warning" role="note">
        Mitra tampil di halaman BPJS dan Asuransi dengan lencana "Contoh". Jangan menyatakan kerja sama resmi yang belum ada.
    </div>

    <div class="row g-4">
        <div class="col-12 col-lg-8">
            <div class="card mb-4">
                <div class="card-body">
                    <x-form.input name="name" label="Nama mitra" :value="$partner?->name" :required="true" maxlength="100" help="3 sampai 100 karakter. Alamat (slug) dibuat otomatis dan tidak berubah saat nama diubah." />
                    <x-form.radio-group name="type" legend="Jenis mitra" :options="\App\Models\InsurancePartner::TYPE_LABELS" :selected="$partner?->type ?? 'asuransi'" :required="true" :inline="true" />
                    <x-form.textarea name="description" label="Deskripsi" :value="$partner?->description" :rows="3" maxlength="500" help="Opsional. Teks biasa tanpa tag HTML, maksimal 500 karakter." />
                    <x-form.input name="website_url" type="url" label="Alamat situs" :value="$partner?->website_url" maxlength="255" inputmode="url" placeholder="https://contoh.co.id" help="Opsional. Harus diawali https://." />
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-4">
            <div class="card mb-4">
                <div class="card-header fw-semibold">Status</div>
                <div class="card-body">
                    <x-form.checkbox name="is_active" label="Aktif (tampil di halaman BPJS dan Asuransi)" :checked="$partner?->is_active ?? true" :with-hidden="true" help="Mitra yang sudah dipakai pendaftaran tidak dapat dihapus; nonaktifkan saja." />
                    <x-form.input name="sort_order" type="number" label="Urutan tampil" :value="$partner?->sort_order ?? 0" inputmode="numeric" min="0" max="9999" help="Angka kecil tampil lebih dulu (0 sampai 9999)." />
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header fw-semibold">Logo</div>
                <div class="card-body">
                    <x-form.file name="logo" label="Logo mitra" :current-media="$partner?->firstMedia('logo')" alt-name="logo_alt" :alt-value="old('logo_alt', $partner?->firstMedia('logo')?->alt_text)" help="Minimal 120 x 40 piksel. Tanpa logo, halaman publik memakai gambar pengganti." />
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex flex-wrap gap-2 justify-content-end">
        <a href="{{ route('admin.insurance-partners.index') }}" class="btn btn-outline-secondary">Batal</a>
        <x-loading-button icon="bi-check-lg" loading-text="Menyimpan…">{{ $submitLabel }}</x-loading-button>
    </div>
</form>
