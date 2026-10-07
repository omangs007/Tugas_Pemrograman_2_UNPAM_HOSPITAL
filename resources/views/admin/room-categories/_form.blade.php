{{-- Variabel: $room (RoomCategory|null), $action, $submitLabel. Tanpa input slug (F4B-R5). --}}
@php
    $isEdit = $room !== null;

    // F4B-R19: old('amenities') berupa larik setelah validasi gagal; gabungkan dengan baris baru.
    $amenitiesValue = old('amenities', $room?->amenities ?? []);
    $amenitiesValue = is_array($amenitiesValue)
        ? implode("\n", array_filter($amenitiesValue, 'is_string'))
        : (is_scalar($amenitiesValue) ? (string) $amenitiesValue : '');
    $amenitiesError = (string) ($errors->first('amenities') ?: $errors->first('amenities.*'));

    $rate = $room?->sample_rate;
    $rateValue = $rate === null ? null : (str_contains((string) $rate, '.') ? rtrim(rtrim((string) $rate, '0'), '.') : (string) $rate);
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
                    <x-form.input name="name" label="Nama kategori" :value="$room?->name" :required="true" maxlength="100" help="Nama dan klasifikasi adalah kebutuhan prototipe, bukan ketentuan BPJS yang sudah diverifikasi. Alamat halaman (slug) dibuat otomatis dan tidak berubah saat nama diubah." />
                    <x-form.textarea name="short_description" label="Deskripsi singkat" :value="$room?->short_description" :rows="2" maxlength="255" :required="true" help="Satu atau dua kalimat; tampil di kartu kategori." />
                    <x-form.rich-text name="description" label="Deskripsi lengkap" :value="$room?->description" :maxlength="30000" help="Tampil di halaman detail kamar. Gambar disisipkan lewat tombol gambar di editor dan wajib diberi teks alternatif." />

                    <div class="mb-3">
                        <label for="amenities" class="form-label fw-semibold">Fasilitas kamar (satu per baris)</label>
                        <textarea id="amenities" name="amenities" rows="6" @if ($amenitiesError !== '') aria-invalid="true" @endif aria-describedby="amenities-help{{ $amenitiesError !== '' ? ' amenities-error' : '' }}" class="form-control{{ $amenitiesError !== '' ? ' is-invalid' : '' }}">{{ $amenitiesValue }}</textarea>
                        <div id="amenities-help" class="form-text">Tulis satu fasilitas per baris, mis. AC, Televisi, Kamar mandi dalam. Maksimal 30 butir, tiap butir 2 sampai 80 karakter dan tidak boleh sama.</div>
                        @if ($amenitiesError !== '')
                            <div id="amenities-error" class="invalid-feedback d-block">{{ $amenitiesError }}</div>
                        @endif
                    </div>

                    <div class="row">
                        <div class="col-12 col-md-6">
                            <x-form.input name="bed_capacity" type="number" label="Kapasitas tempat tidur" :value="$room?->bed_capacity" :required="true" inputmode="numeric" min="1" max="500" help="Total tempat tidur kategori ini (1 sampai 500)." />
                        </div>
                        <div class="col-12 col-md-6">
                            @if ($isEdit)
                                <p class="form-label fw-semibold mb-1">Tersedia saat ini</p>
                                <p class="mb-1">Tempat tidur tersedia saat ini: {{ $room->beds_available }} dari {{ $room->bed_capacity }}.</p>
                                <p class="form-text">Ketersediaan diubah dari daftar <a href="{{ route('admin.room-categories.index') }}">Kamar dan Kapasitas</a>. Mengubah kapasitas tidak mengubah waktu pembaruan ketersediaan.</p>
                            @else
                                <x-form.input name="beds_available" type="number" label="Tempat tidur tersedia" value="" :required="true" inputmode="numeric" min="0" max="500" help="Jumlah yang tersedia sekarang; tidak boleh melebihi kapasitas. Waktu dan petugas pembaruan dicatat otomatis." />
                            @endif
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-12 col-md-4">
                            <x-form.input name="sample_rate" type="number" label="Tarif contoh (Rp)" :value="$rateValue" inputmode="decimal" min="0" step="0.01" help="Opsional. Isi angka tanpa titik pemisah ribuan, mis. 400000. Kosong = halaman publik menampilkan Hubungi kami." />
                        </div>
                        <div class="col-12 col-md-4">
                            <x-form.input name="rate_unit" label="Satuan tarif" :value="$room?->rate_unit ?? 'per malam'" :required="true" maxlength="30" />
                        </div>
                        <div class="col-12 col-md-4">
                            <x-form.input name="rate_note" label="Catatan tarif" :value="$room?->rate_note" maxlength="255" help="Mis. Tarif contoh, belum final." />
                        </div>
                    </div>

                    <details class="mb-3" @if ($errors->has('meta_title') || $errors->has('meta_description')) open @endif>
                        <summary class="fw-semibold mb-2">Pengaturan SEO (opsional)</summary>
                        <x-form.input name="meta_title" label="Meta title" :value="$room?->meta_title" maxlength="70" help="Maksimal 70 karakter. Kosong = memakai nama kategori." />
                        <x-form.textarea name="meta_description" label="Meta description" :value="$room?->meta_description" :rows="2" maxlength="160" help="Maksimal 160 karakter. Kosong = memakai deskripsi singkat." />
                    </details>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-4">
            <div class="card mb-4">
                <div class="card-header fw-semibold">Status</div>
                <div class="card-body">
                    <x-form.checkbox name="is_active" label="Aktif (tampil di halaman Kamar Rawat Inap)" :checked="$room?->is_active ?? true" :with-hidden="true" />
                    <x-form.input name="sort_order" type="number" label="Urutan tampil" :value="$room?->sort_order ?? 0" inputmode="numeric" min="0" max="9999" help="Angka kecil tampil lebih dulu." />
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header fw-semibold">Foto sampul</div>
                <div class="card-body">
                    <x-form.file name="cover" label="Foto sampul" placeholder="room" :current-media="$room?->firstMedia('cover')" alt-name="cover_alt" :alt-value="old('cover_alt', $room?->firstMedia('cover')?->alt_text)" help="Minimal 800 x 450 piksel. Tanpa foto, halaman publik memakai gambar pengganti." />
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header fw-semibold">Tambah foto galeri</div>
                <div class="card-body">
                    <p class="form-text">Maksimal 20 foto per kategori. JPG, PNG, atau WebP, maksimal 2 MB, minimal 600 x 400 piksel. Teks alternatif wajib untuk setiap foto. Untuk menambah lebih banyak, simpan lalu ubah lagi.</p>
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
        <a href="{{ route('admin.room-categories.index') }}" class="btn btn-outline-secondary">Batal</a>
        <x-loading-button icon="bi-check-lg" loading-text="Menyimpan…">{{ $submitLabel }}</x-loading-button>
    </div>
</form>
