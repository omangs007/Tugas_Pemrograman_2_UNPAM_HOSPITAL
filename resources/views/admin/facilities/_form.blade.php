{{-- Variabel: $facility (Facility|null), $action, $submitLabel. Tanpa input slug (F4B-R5). --}}
@php($isEdit = $facility !== null)
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
                    <x-form.input name="name" label="Nama fasilitas" :value="$facility?->name" :required="true" maxlength="100" help="Alamat halaman (slug) dibuat otomatis dan tidak berubah saat nama diubah." />
                    <x-form.select name="category" label="Kategori" :options="\App\Enums\FacilityCategory::options()" :selected="$facility?->category" placeholder="Pilih kategori" :required="true" />
                    <x-form.textarea name="short_description" label="Deskripsi singkat" :value="$facility?->short_description" :rows="2" maxlength="255" :required="true" help="Satu atau dua kalimat; tampil di kartu fasilitas." />
                    <x-form.rich-text name="description" label="Deskripsi lengkap" :value="$facility?->description" :maxlength="30000" help="Tampil di halaman detail fasilitas. Gambar disisipkan lewat tombol gambar di editor dan wajib diberi teks alternatif." />

                    <details class="mb-3" @if ($errors->has('meta_title') || $errors->has('meta_description')) open @endif>
                        <summary class="fw-semibold mb-2">Pengaturan SEO (opsional)</summary>
                        <x-form.input name="meta_title" label="Meta title" :value="$facility?->meta_title" maxlength="70" help="Maksimal 70 karakter. Kosong = memakai nama fasilitas." />
                        <x-form.textarea name="meta_description" label="Meta description" :value="$facility?->meta_description" :rows="2" maxlength="160" help="Maksimal 160 karakter. Kosong = memakai deskripsi singkat." />
                    </details>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-4">
            <div class="card mb-4">
                <div class="card-header fw-semibold">Status</div>
                <div class="card-body">
                    <x-form.checkbox name="is_active" label="Aktif (tampil di halaman Fasilitas)" :checked="$facility?->is_active ?? true" :with-hidden="true" />
                    <x-form.input name="sort_order" type="number" label="Urutan tampil" :value="$facility?->sort_order ?? 0" inputmode="numeric" min="0" max="9999" help="Angka kecil tampil lebih dulu." />
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header fw-semibold">Foto sampul</div>
                <div class="card-body">
                    <x-form.file name="cover" label="Foto sampul" placeholder="facility" :current-media="$facility?->firstMedia('cover')" alt-name="cover_alt" :alt-value="old('cover_alt', $facility?->firstMedia('cover')?->alt_text)" help="Minimal 800 x 450 piksel. Tanpa foto, halaman publik memakai gambar pengganti." />
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header fw-semibold">Tambah foto galeri</div>
                <div class="card-body">
                    <p class="form-text">Maksimal 20 foto per fasilitas. JPG, PNG, atau WebP, maksimal 2 MB, minimal 600 x 400 piksel. Teks alternatif wajib untuk setiap foto. Untuk menambah lebih banyak, simpan lalu ubah lagi.</p>
                    @for ($i = 0; $i < 3; $i++)
                        <div class="mb-3">
                            <label for="gallery-{{ $i }}" class="form-label fw-semibold">Foto galeri {{ $i + 1 }}</label>
                            <input type="file" id="gallery-{{ $i }}" name="gallery[{{ $i }}]" accept="image/jpeg,image/png,image/webp" class="form-control{{ $errors->has('gallery.'.$i) ? ' is-invalid' : '' }}" data-max-kb="2048">
                            @if ($errors->has('gallery.'.$i))
                                <div class="invalid-feedback d-block">{{ $errors->first('gallery.'.$i) }}</div>
                            @endif
                            <label for="gallery_alt-{{ $i }}" class="form-label visually-hidden">Teks alternatif foto galeri {{ $i + 1 }}</label>
                            <input type="text" id="gallery_alt-{{ $i }}" name="gallery_alt[{{ $i }}]" value="{{ old('gallery_alt.'.$i) }}" maxlength="255" placeholder="Teks alternatif foto {{ $i + 1 }}" class="form-control mt-1{{ $errors->has('gallery_alt.'.$i) ? ' is-invalid' : '' }}">
                            @if ($errors->has('gallery_alt.'.$i))
                                <div class="invalid-feedback d-block">{{ $errors->first('gallery_alt.'.$i) }}</div>
                            @endif
                        </div>
                    @endfor
                    @if ($errors->has('gallery'))
                        <div id="gallery" class="invalid-feedback d-block">{{ $errors->first('gallery') }}</div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex flex-wrap gap-2 justify-content-end">
        <a href="{{ route('admin.facilities.index') }}" class="btn btn-outline-secondary">Batal</a>
        <x-loading-button icon="bi-check-lg" loading-text="Menyimpan…">{{ $submitLabel }}</x-loading-button>
    </div>
</form>
