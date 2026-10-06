@php
    $photos = $gallery->mediaIn('photos')->values();
    $photoIds = $photos->pluck('id')->all();
    $remaining = \App\Models\Gallery::MAX_PHOTOS - $photos->count();
    $breadcrumbItems = [
        ['label' => 'Konten', 'url' => route('admin.pages.index')],
        ['label' => 'Galeri', 'url' => route('admin.galleries.index')],
        ['label' => 'Ubah Album '.$gallery->title],
    ];
@endphp
<x-layouts.admin :title="'Ubah Album '.$gallery->title">
    <x-slot:breadcrumb>
        <x-breadcrumb home="admin.dashboard" :items="$breadcrumbItems" />
    </x-slot:breadcrumb>

    @include('admin.galleries._form', [
        'gallery' => $gallery,
        'action' => route('admin.galleries.update', $gallery),
        'submitLabel' => 'Simpan Perubahan',
    ])

    {{-- Kelola foto: form tersendiri (tidak boleh bersarang di form album) ke admin.media.* --}}
    <div class="card mt-4" id="foto-album">
        <div class="card-header fw-semibold">Foto album ({{ $photos->count() }} dari {{ \App\Models\Gallery::MAX_PHOTOS }})</div>
        @if ($photos->isEmpty())
            <div class="card-body text-secondary">Belum ada foto. Tambahkan lewat form "Tambah foto" di bawah.</div>
        @else
            <ul class="list-group list-group-flush">
                @foreach ($photos as $position => $photo)
                    @php
                        $up = $photoIds;
                        $down = $photoIds;
                        if ($position > 0) {
                            [$up[$position - 1], $up[$position]] = [$up[$position], $up[$position - 1]];
                        }
                        if ($position < count($photoIds) - 1) {
                            [$down[$position + 1], $down[$position]] = [$down[$position], $down[$position + 1]];
                        }
                    @endphp
                    <li class="list-group-item">
                        <div class="row g-3 align-items-center">
                            <div class="col-12 col-md-3 col-lg-2">
                                <x-image type="gallery" :media="$photo" :alt="$photo->alt_text" variant="thumb" ratio="4x3" class="rounded" />
                                @if ($position === 0)
                                    <span class="badge text-bg-primary mt-1">Sampul album</span>
                                @endif
                            </div>
                            <div class="col-12 col-md-9 col-lg-10">
                                <form method="POST" action="{{ route('admin.media.update', $photo) }}" class="row g-2 align-items-end mb-2">
                                    @csrf
                                    @method('PATCH')
                                    <div class="col-12 col-lg">
                                        <label for="alt-media-{{ $photo->id }}" class="form-label fw-semibold">Teks alternatif</label>
                                        <input type="text" id="alt-media-{{ $photo->id }}" name="alt_text" value="{{ $photo->alt_text }}" maxlength="255" required class="form-control">
                                    </div>
                                    <div class="col-12 col-lg">
                                        <label for="caption-media-{{ $photo->id }}" class="form-label fw-semibold">Keterangan (opsional)</label>
                                        <input type="text" id="caption-media-{{ $photo->id }}" name="caption" value="{{ $photo->caption }}" maxlength="255" class="form-control">
                                    </div>
                                    <div class="col-12 col-lg-auto">
                                        <x-loading-button variant="outline-primary" loading-text="Menyimpan…">Simpan teks</x-loading-button>
                                    </div>
                                </form>
                                <div class="d-flex flex-wrap gap-2">
                                    <form method="POST" action="{{ route('admin.media.reorder') }}">
                                        @csrf
                                        @foreach ($up as $id)
                                            <input type="hidden" name="ids[]" value="{{ $id }}">
                                        @endforeach
                                        <button type="submit" class="btn btn-sm btn-outline-secondary" @disabled($position === 0) aria-label="Naikkan foto {{ $position + 1 }}">Naikkan</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.media.reorder') }}">
                                        @csrf
                                        @foreach ($down as $id)
                                            <input type="hidden" name="ids[]" value="{{ $id }}">
                                        @endforeach
                                        <button type="submit" class="btn btn-sm btn-outline-secondary" @disabled($position === count($photoIds) - 1) aria-label="Turunkan foto {{ $position + 1 }}">Turunkan</button>
                                    </form>
                                    <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modal-hapus-foto" data-confirm-action="{{ route('admin.media.destroy', $photo) }}" data-confirm-message="Hapus foto &quot;{{ $photo->alt_text }}&quot; dari album? Tindakan ini tidak dapat dibatalkan.">Hapus</button>
                                </div>
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    {{-- Tambah foto: form unggah terpisah, lima slot per kirim (F4A-R23, V-34) --}}
    <div class="card mt-4" id="tambah-foto">
        <div class="card-header fw-semibold">Tambah foto</div>
        <div class="card-body">
            @if ($remaining <= 0)
                <p class="mb-0 text-secondary">Album sudah mencapai batas 100 foto. Hapus foto lama untuk menambah yang baru.</p>
            @else
                <form method="POST" action="{{ route('admin.galleries.photos.store', $gallery) }}" enctype="multipart/form-data">
                    @csrf
                    <p class="form-text">Sisa tempat: {{ $remaining }} foto. Maksimal 10 foto sekali kirim (form ini menyediakan 5 slot; kirim lagi untuk menambah). JPG, PNG, atau WebP, maksimal 2 MB, minimal 600 x 400 piksel. Teks alternatif wajib untuk setiap foto.</p>
                    @for ($i = 0; $i < 5; $i++)
                        <div class="mb-3">
                            <label for="photos-{{ $i }}" class="form-label fw-semibold">Foto {{ $i + 1 }}</label>
                            <input type="file" id="photos-{{ $i }}" name="photos[{{ $i }}]" accept="image/jpeg,image/png,image/webp" class="form-control{{ $errors->has('photos.'.$i) ? ' is-invalid' : '' }}" data-max-kb="2048">
                            @if ($errors->has('photos.'.$i))
                                <div class="invalid-feedback d-block">{{ $errors->first('photos.'.$i) }}</div>
                            @endif
                            <label for="alt_texts-{{ $i }}" class="form-label visually-hidden">Teks alternatif foto {{ $i + 1 }}</label>
                            <input type="text" id="alt_texts-{{ $i }}" name="alt_texts[{{ $i }}]" value="{{ old('alt_texts.'.$i) }}" maxlength="255" placeholder="Teks alternatif foto {{ $i + 1 }}" class="form-control mt-1{{ $errors->has('alt_texts.'.$i) ? ' is-invalid' : '' }}">
                            @if ($errors->has('alt_texts.'.$i))
                                <div class="invalid-feedback d-block">{{ $errors->first('alt_texts.'.$i) }}</div>
                            @endif
                            <label for="captions-{{ $i }}" class="form-label visually-hidden">Keterangan foto {{ $i + 1 }}</label>
                            <input type="text" id="captions-{{ $i }}" name="captions[{{ $i }}]" value="{{ old('captions.'.$i) }}" maxlength="255" placeholder="Keterangan foto {{ $i + 1 }} (opsional)" class="form-control mt-1{{ $errors->has('captions.'.$i) ? ' is-invalid' : '' }}">
                            @if ($errors->has('captions.'.$i))
                                <div class="invalid-feedback d-block">{{ $errors->first('captions.'.$i) }}</div>
                            @endif
                        </div>
                    @endfor
                    @if ($errors->has('photos'))
                        <div id="photos" class="invalid-feedback d-block mb-3">{{ $errors->first('photos') }}</div>
                    @endif
                    <x-loading-button icon="bi-upload" loading-text="Mengunggah…">Unggah Foto</x-loading-button>
                </form>
            @endif
        </div>
    </div>

    <x-confirm-modal
        id="modal-hapus-foto"
        title="Hapus foto dari album?"
        message="Foto akan dihapus permanen."
        confirm-label="Ya, hapus"
        variant="danger"
        method="DELETE" />
</x-layouts.admin>
