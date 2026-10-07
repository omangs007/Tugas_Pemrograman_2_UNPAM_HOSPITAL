@php
    $gallery = $facility->mediaIn('gallery')->values();
    $galleryIds = $gallery->pluck('id')->all();
    $rootUrl = \Illuminate\Support\Facades\Route::has('admin.room-categories.index') ? route('admin.room-categories.index') : route('admin.facilities.index');
    $breadcrumbItems = [
        ['label' => 'Fasilitas dan Kamar', 'url' => $rootUrl],
        ['label' => 'Fasilitas', 'url' => route('admin.facilities.index')],
        ['label' => 'Ubah '.$facility->name],
    ];
@endphp
<x-layouts.admin :title="'Ubah '.$facility->name">
    <x-slot:breadcrumb>
        <x-breadcrumb home="admin.dashboard" :items="$breadcrumbItems" />
    </x-slot:breadcrumb>

    @include('admin.facilities._form', [
        'facility' => $facility,
        'action' => route('admin.facilities.update', $facility),
        'submitLabel' => 'Simpan Perubahan',
    ])

    {{-- Kelola galeri: form tersendiri (tidak boleh bersarang di form utama) ke admin.media.* --}}
    <div class="card mt-4" id="galeri-fasilitas">
        <div class="card-header fw-semibold">Galeri ({{ $gallery->count() }} dari 20 foto)</div>
        @if ($gallery->isEmpty())
            <div class="card-body text-secondary">Belum ada foto galeri. Tambahkan lewat form di atas.</div>
        @else
            <ul class="list-group list-group-flush">
                @foreach ($gallery as $position => $image)
                    @php
                        $up = $galleryIds;
                        $down = $galleryIds;
                        if ($position > 0) {
                            [$up[$position - 1], $up[$position]] = [$up[$position], $up[$position - 1]];
                        }
                        if ($position < count($galleryIds) - 1) {
                            [$down[$position + 1], $down[$position]] = [$down[$position], $down[$position + 1]];
                        }
                    @endphp
                    <li class="list-group-item">
                        <div class="row g-3 align-items-center">
                            <div class="col-12 col-md-3 col-lg-2">
                                <x-image type="facility" :media="$image" :alt="$image->alt_text" variant="thumb" ratio="4x3" class="rounded" />
                            </div>
                            <div class="col-12 col-md-9 col-lg-10">
                                <form method="POST" action="{{ route('admin.media.update', $image) }}" class="row g-2 align-items-end mb-2">
                                    @csrf
                                    @method('PATCH')
                                    <div class="col-12 col-md">
                                        <label for="alt-media-{{ $image->id }}" class="form-label fw-semibold">Teks alternatif</label>
                                        <input type="text" id="alt-media-{{ $image->id }}" name="alt_text" value="{{ $image->alt_text }}" maxlength="255" required class="form-control">
                                    </div>
                                    <div class="col-12 col-md-auto">
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
                                        <button type="submit" class="btn btn-sm btn-outline-secondary" @disabled($position === count($galleryIds) - 1) aria-label="Turunkan foto {{ $position + 1 }}">Turunkan</button>
                                    </form>
                                    <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modal-hapus-foto" data-confirm-action="{{ route('admin.media.destroy', $image) }}" data-confirm-message="Hapus foto &quot;{{ $image->alt_text }}&quot; dari galeri? Tindakan ini tidak dapat dibatalkan.">Hapus</button>
                                </div>
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    <x-confirm-modal
        id="modal-hapus-foto"
        title="Hapus foto dari galeri?"
        message="Foto akan dihapus permanen."
        confirm-label="Ya, hapus"
        variant="danger"
        method="DELETE" />
</x-layouts.admin>
