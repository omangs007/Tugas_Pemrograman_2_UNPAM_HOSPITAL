{{-- Variabel: $doctor (Doctor|null), $clinics (id => nama), $specialties (koleksi Specialty), $action, $submitLabel.
     Tanpa input status dan slug (F2-R5, F2-R6). --}}
@php
    $isEdit = $doctor !== null;
    $selectedIds = array_map('intval', (array) old('specialty_ids', $isEdit ? $doctor->specialties->pluck('id')->all() : []));
    $primaryId = (int) old('primary_specialty_id', $isEdit ? ($doctor->specialties->firstWhere('pivot.is_primary', true)?->id ?? 0) : 0);
    $specialtyError = (string) ($errors->first('specialty_ids') ?: $errors->first('specialty_ids.*'));
    $primaryError = (string) $errors->first('primary_specialty_id');
    $photo = $isEdit ? $doctor->firstMedia('photo') : null;
@endphp
<form method="POST" action="{{ $action }}" enctype="multipart/form-data">
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    <x-form.errors-summary />

    <div class="row g-4">
        <div class="col-12 col-lg-4">
            <div class="card">
                <div class="card-header fw-semibold">Foto dokter</div>
                <div class="card-body">
                    <x-form.file name="photo" label="Foto" :current-media="$photo" alt-name="photo_alt" :alt-value="old('photo_alt', $photo?->alt_text)" help="Minimal 300 x 300 piksel. Tanpa foto, situs memakai gambar pengganti. Mengunggah foto baru menggantikan foto lama." />
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-8">
            <div class="card mb-4">
                <div class="card-body">
                    <x-form.input name="name" label="Nama (tanpa gelar)" :value="$doctor?->name" :required="true" maxlength="100" help="Contoh: Laras Wulandari. Nama tampilan dibentuk dari gelar depan, nama, dan gelar belakang; alamat halaman (slug) dibuat otomatis dan tidak berubah." />
                    <div class="row">
                        <div class="col-12 col-sm-5">
                            <x-form.input name="title_prefix" label="Gelar depan" :value="$doctor?->title_prefix" maxlength="20" help="Contoh: dr., drg." />
                        </div>
                        <div class="col-12 col-sm-7">
                            <x-form.input name="title_suffix" label="Gelar belakang" :value="$doctor?->title_suffix" maxlength="50" help="Contoh: Sp.A, Sp.PD." />
                        </div>
                    </div>
                    <x-form.radio-group name="gender" legend="Jenis kelamin" :options="\App\Enums\Gender::options()" :selected="$doctor?->gender" :inline="true" :required="true" />
                    <x-form.select name="clinic_id" label="Poliklinik utama" :options="$clinics" :selected="$doctor?->clinic_id" placeholder="Pilih poliklinik" :required="true" help="Hanya poliklinik aktif." />

                    <fieldset id="specialty_ids" tabindex="-1" class="mb-3"@if ($specialtyError !== '' || $primaryError !== '') aria-describedby="{{ trim(($specialtyError !== '' ? 'specialty_ids-error ' : '').($primaryError !== '' ? 'primary_specialty_id' : '')) }}"@endif>
                        <legend class="form-label fw-semibold fs-6">Spesialisasi <span class="required-mark" aria-hidden="true">*</span></legend>
                        <p class="form-text">Pilih 1 sampai 5 spesialisasi, lalu tandai tepat satu sebagai Utama.</p>
                        <div class="border rounded p-2">
                            @foreach ($specialties as $specialty)
                                <div class="row g-2 align-items-start border-bottom">
                                    <div class="col-8">
                                        <x-form.checkbox name="specialty_ids[]" :value="$specialty->id" :label="$specialty->name.($specialty->is_active ? '' : ' (nonaktif)')" :checked-values="$selectedIds" :show-error="false" />
                                    </div>
                                    <div class="col-4">
                                        <div class="form-check mt-1">
                                            <input class="form-check-input" type="radio" name="primary_specialty_id" id="primary-specialty-{{ $specialty->id }}" value="{{ $specialty->id }}" @checked($primaryId === $specialty->id)>
                                            <label class="form-check-label" for="primary-specialty-{{ $specialty->id }}">Utama<span class="visually-hidden">: jadikan {{ $specialty->name }} spesialisasi utama</span></label>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        {{-- K3: setiap kotak :show-error="false"; galat kelompok dirender SATU kali di sini (id yang ditunjuk aria-describedby tiap kotak, Task 5). --}}
                        @if ($specialtyError !== '')
                            <div id="specialty_ids-error" class="invalid-feedback d-block">{{ $specialtyError }}</div>
                        @endif
                        @if ($primaryError !== '')
                            <div id="primary_specialty_id" tabindex="-1" class="invalid-feedback d-block">{{ $primaryError }}</div>
                        @endif
                    </fieldset>

                    <x-form.textarea name="short_bio" label="Profil singkat" :value="$doctor?->short_bio" :rows="3" maxlength="500" help="Maksimal 500 karakter; tampil di kartu dokter." />
                    <x-form.textarea name="bio" label="Profil lengkap" :value="$doctor?->bio" :rows="6" maxlength="5000" help="Teks biasa tanpa tag HTML. Pisahkan paragraf dengan satu baris kosong." />
                    <x-form.input name="sort_order" type="number" label="Urutan tampil" :value="$doctor?->sort_order ?? 0" inputmode="numeric" min="0" max="9999" help="Angka kecil tampil lebih dulu." />

                    <details class="mb-3" @if ($errors->has('meta_title') || $errors->has('meta_description')) open @endif>
                        <summary class="fw-semibold mb-2">Pengaturan SEO (opsional)</summary>
                        <x-form.input name="meta_title" label="Meta title" :value="$doctor?->meta_title" maxlength="70" help="Maksimal 70 karakter. Kosong = memakai nama tampilan." />
                        <x-form.textarea name="meta_description" label="Meta description" :value="$doctor?->meta_description" :rows="2" maxlength="160" help="Maksimal 160 karakter. Kosong = memakai profil singkat." />
                    </details>

                    @unless ($isEdit)
                        <p class="form-text mb-0">Dokter baru berstatus Aktif. Status diubah dari daftar atau halaman detail dokter.</p>
                    @endunless
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex flex-wrap gap-2 justify-content-end">
        <a href="{{ $isEdit ? route('admin.doctors.show', $doctor) : route('admin.doctors.index') }}" class="btn btn-outline-secondary">Batal</a>
        <x-loading-button icon="bi-check-lg" loading-text="Menyimpan…">{{ $submitLabel }}</x-loading-button>
    </div>
</form>
