{{-- Variabel: $clinic (Clinic|null), $specialties (id => nama), $action, $submitLabel. Tanpa input slug (F2-R5). --}}
@php($isEdit = $clinic !== null)
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
                    <x-form.input name="name" label="Nama layanan" :value="$clinic?->name" :required="true" maxlength="100" help="Alamat halaman (slug) dibuat otomatis dan tidak berubah saat nama diubah." />
                    <div class="row">
                        <div class="col-12 col-md-4">
                            <x-form.input name="code" label="Kode antrean" :value="$clinic?->code" :required="true" maxlength="5" autocomplete="off" class="text-uppercase" help="2 sampai 5 huruf kapital, mis. ANK. Menjadi awalan nomor antrean." />
                        </div>
                        <div class="col-12 col-md-8">
                            <x-form.select name="type" label="Jenis layanan" :options="\App\Enums\ClinicType::options()" :selected="$clinic?->type" placeholder="Pilih jenis layanan" :required="true" />
                        </div>
                    </div>
                    <x-form.select name="specialty_id" label="Spesialisasi" :options="['' => 'Tanpa spesialisasi'] + $specialties" :selected="$clinic?->specialty_id" help="Kosongkan untuk layanan penunjang." />
                    <x-form.textarea name="short_description" label="Deskripsi singkat" :value="$clinic?->short_description" :rows="2" maxlength="255" :required="true" help="Satu atau dua kalimat; tampil di kartu layanan." />
                    <x-form.rich-text name="description" label="Deskripsi lengkap" :value="$clinic?->description" :maxlength="30000" help="Tampil di halaman detail layanan. Gambar disisipkan lewat tombol gambar di editor dan wajib diberi teks alternatif." />
                    <x-form.input name="location" label="Lokasi" :value="$clinic?->location" :required="true" maxlength="100" help="Mis. Gedung A Lt. 1." />

                    <details class="mb-3" @if ($errors->has('meta_title') || $errors->has('meta_description')) open @endif>
                        <summary class="fw-semibold mb-2">Pengaturan SEO (opsional)</summary>
                        <x-form.input name="meta_title" label="Meta title" :value="$clinic?->meta_title" maxlength="70" help="Maksimal 70 karakter. Kosong = memakai nama layanan." />
                        <x-form.textarea name="meta_description" label="Meta description" :value="$clinic?->meta_description" :rows="2" maxlength="160" help="Maksimal 160 karakter. Kosong = memakai deskripsi singkat." />
                    </details>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-4">
            <div class="card mb-4">
                <div class="card-header fw-semibold">Status</div>
                <div class="card-body">
                    <x-form.checkbox name="is_active" label="Aktif (tampil di halaman Layanan)" :checked="$clinic?->is_active ?? true" :with-hidden="true" />
                    <x-form.checkbox name="is_bookable" label="Dapat didaftar online" :checked="$clinic?->is_bookable ?? true" :with-hidden="true" help="Hanya untuk jenis Poliklinik; layanan penunjang tidak dapat dibuka untuk pendaftaran." />
                    <x-form.input name="sort_order" type="number" label="Urutan tampil" :value="$clinic?->sort_order ?? 0" inputmode="numeric" min="0" max="9999" help="Angka kecil tampil lebih dulu." />
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header fw-semibold">Foto sampul</div>
                <div class="card-body">
                    <x-form.file name="cover" label="Foto sampul" :current-media="$clinic?->firstMedia('cover')" alt-name="cover_alt" :alt-value="old('cover_alt', $clinic?->firstMedia('cover')?->alt_text)" help="Minimal 800 x 450 piksel. Tanpa foto, halaman publik memakai gambar pengganti." />
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header fw-semibold">Tambah foto galeri</div>
                <div class="card-body">
                    <p class="form-text">Maksimal 20 foto per poliklinik. JPG, PNG, atau WebP, maksimal 2 MB, minimal 600 x 400 piksel. Teks alternatif wajib untuk setiap foto. Untuk menambah lebih banyak, simpan lalu ubah lagi.</p>
                    @for ($i = 0; $i < 3; $i++)
                        <div class="mb-3">
                            <label for="gallery-{{ $i }}" class="form-label fw-semibold">Foto galeri {{ $i + 1 }}</label>
                            <input type="file" id="gallery-{{ $i }}" name="gallery[{{ $i }}]" accept="image/jpeg,image/png,image/webp" class="form-control{{ $errors->has('gallery.'.$i) ? ' is-invalid' : '' }}" data-max-kb="2048">
                            @if ($errors->has('gallery.'.$i))
                                <div class="invalid-feedback d-block">{{ $errors->first('gallery.'.$i) }}</div>
                            @endif
                            <label for="gallery-alt-{{ $i }}" class="form-label visually-hidden">Teks alternatif foto galeri {{ $i + 1 }}</label>
                            <input type="text" id="gallery-alt-{{ $i }}" name="gallery_alt[{{ $i }}]" value="{{ old('gallery_alt.'.$i) }}" maxlength="255" placeholder="Teks alternatif foto {{ $i + 1 }}" class="form-control mt-1{{ $errors->has('gallery_alt.'.$i) ? ' is-invalid' : '' }}">
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
        <a href="{{ route('admin.clinics.index') }}" class="btn btn-outline-secondary">Batal</a>
        <x-loading-button icon="bi-check-lg" loading-text="Menyimpan…">{{ $submitLabel }}</x-loading-button>
    </div>
</form>
