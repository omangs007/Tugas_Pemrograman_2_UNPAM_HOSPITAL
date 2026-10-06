{{-- Variabel: $faq (Faq|null), $action (URL), $submitLabel. Urutan tampil = field sort_order (F4A-R22). --}}
@php($isEdit = $faq !== null)
<form method="POST" action="{{ $action }}">
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    <x-form.errors-summary />

    <div class="card">
        <div class="card-body">
            <x-form.select name="category" label="Kategori" :options="\App\Models\Faq::CATEGORY_LABELS" :selected="$faq?->category" placeholder="Pilih kategori" :required="true" help="FAQ dikelompokkan per kategori di halaman FAQ." />
            <x-form.input name="question" label="Pertanyaan" :value="$faq?->question" :required="true" maxlength="255" help="5 sampai 255 karakter, teks biasa tanpa tag HTML." />
            <x-form.textarea name="answer" label="Jawaban" :value="$faq?->answer" :rows="6" maxlength="2000" :required="true" help="Teks biasa tanpa tag HTML, maksimal 2000 karakter. Baris baru dipertahankan di halaman publik." />
            <x-form.input name="sort_order" type="number" label="Urutan tampil" :value="$faq?->sort_order ?? 0" inputmode="numeric" min="0" max="9999" help="Angka kecil tampil lebih dulu di dalam kategorinya (0 sampai 9999)." />
            <x-form.checkbox name="is_published" label="Terbitkan (tampil di halaman FAQ)" :checked="$faq?->is_published ?? true" :with-hidden="true" help="FAQ draf tidak tampil di situs publik." />
        </div>
        <div class="card-footer d-flex flex-wrap gap-2 justify-content-end">
            <a href="{{ route('admin.faqs.index') }}" class="btn btn-outline-secondary">Batal</a>
            <x-loading-button icon="bi-check-lg" loading-text="Menyimpan…">{{ $submitLabel }}</x-loading-button>
        </div>
    </div>
</form>
