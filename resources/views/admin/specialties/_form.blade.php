{{-- Variabel: $specialty (Specialty|null), $action (URL), $submitLabel. Tanpa input slug (F2-R5). --}}
@php($isEdit = $specialty !== null)
<form method="POST" action="{{ $action }}">
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    <x-form.errors-summary />

    <div class="card">
        <div class="card-body">
            <x-form.input name="name" label="Nama spesialisasi" :value="$specialty?->name" :required="true" maxlength="100" help="3 sampai 100 karakter. Alamat (slug) dibuat otomatis dari nama dan tidak berubah saat nama diubah." />
            <x-form.textarea name="description" label="Deskripsi" :value="$specialty?->description" :rows="3" maxlength="1000" help="Teks biasa tanpa tag HTML. Maksimal 1000 karakter." />
            <x-form.input name="sort_order" type="number" label="Urutan tampil" :value="$specialty?->sort_order ?? 0" inputmode="numeric" min="0" max="9999" help="Angka kecil tampil lebih dulu (0 sampai 9999)." />
            <x-form.checkbox name="is_active" label="Aktif (tampil sebagai pilihan dan filter)" :checked="$specialty?->is_active ?? true" :with-hidden="true" help="Spesialisasi yang masih dipakai dokter atau poliklinik tidak dapat dihapus; nonaktifkan saja." />
        </div>
        <div class="card-footer d-flex flex-wrap gap-2 justify-content-end">
            <a href="{{ route('admin.specialties.index') }}" class="btn btn-outline-secondary">Batal</a>
            <x-loading-button icon="bi-check-lg" loading-text="Menyimpan…">{{ $submitLabel }}</x-loading-button>
        </div>
    </div>
</form>
