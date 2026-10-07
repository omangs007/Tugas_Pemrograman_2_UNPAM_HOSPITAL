@php
    $canManage = auth()->user()?->can('create', \App\Models\RoomCategory::class) === true;
    // Old input dapat berupa larik bila permintaan berbahaya; hanya nilai skalar yang dipakai (catatan teknis 6).
    $oldFor = old('availability_for');
    $oldBeds = old('beds_available');
    $breadcrumbItems = [
        ['label' => 'Fasilitas dan Kamar', 'url' => route('admin.room-categories.index')],
        ['label' => 'Kamar dan Kapasitas'],
    ];
@endphp
<x-layouts.admin title="Kamar dan Kapasitas">
    <x-slot:breadcrumb>
        <x-breadcrumb home="admin.dashboard" :items="$breadcrumbItems" />
    </x-slot:breadcrumb>

    @if ($canManage)
        <x-slot:actions>
            <a href="{{ route('admin.room-categories.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-lg" aria-hidden="true"></i> Tambah Kategori Kamar
            </a>
        </x-slot:actions>
    @endif

    <div class="mb-3">
        <x-module-tabs group="facilities" />
    </div>

    <p class="text-secondary mb-2">Ketersediaan tempat tidur diperbarui manual oleh petugas dan tampil di halaman publik beserta waktu pembaruannya.</p>
    @unless ($canManage)
        <p class="text-secondary">Anda hanya dapat memperbarui ketersediaan tempat tidur.</p>
    @endunless

    @if ($rooms->isEmpty())
        <x-empty-state title="Belum ada kategori kamar" message="Tambahkan kategori kamar rawat inap beserta kapasitas tempat tidurnya." icon="bi-building">
            @if ($canManage)
                <a href="{{ route('admin.room-categories.create') }}" class="btn btn-primary">Tambah Kategori Kamar</a>
            @endif
        </x-empty-state>
    @else
        <div class="table-responsive">
            <table class="table table-hover align-middle table-cards bg-white">
                <caption class="visually-hidden">Daftar kategori kamar dan ketersediaan tempat tidur</caption>
                <thead class="table-light">
                    <tr>
                        <th scope="col">Kategori</th>
                        <th scope="col">Kapasitas</th>
                        <th scope="col">Tersedia</th>
                        <th scope="col">Diperbarui</th>
                        <th scope="col">Tarif contoh</th>
                        <th scope="col">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rooms as $room)
                        <tr>
                            <td data-label="Kategori">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="admin-thumb">
                                        <x-image type="room" :media="$room->firstMedia('cover')" variant="thumb" ratio="4x3" :decorative="true" />
                                    </div>
                                    <div>
                                        <span class="fw-semibold">{{ $room->name }}</span>
                                        @if ($room->is_active)
                                            <span class="badge text-bg-success">Aktif</span>
                                        @else
                                            <span class="badge text-bg-secondary">Nonaktif</span>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td data-label="Kapasitas">{{ $room->bed_capacity }} tempat tidur</td>
                            <td data-label="Tersedia">
                                @can('updateAvailability', $room)
                                    @php
                                        $isThisRow = is_scalar($oldFor) && (string) $oldFor === (string) $room->id;
                                        $rowError = $isThisRow ? (string) $errors->first('beds_available') : '';
                                        $shown = $isThisRow && is_scalar($oldBeds) ? $oldBeds : $room->beds_available;
                                        $fieldId = 'beds-available-'.$room->id;
                                    @endphp
                                    <form method="POST" action="{{ route('admin.room-categories.availability', $room) }}" class="d-flex flex-wrap align-items-start gap-2" aria-label="Perbarui ketersediaan {{ $room->name }}">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="availability_for" value="{{ $room->id }}">
                                        <div>
                                            <label for="{{ $fieldId }}" class="visually-hidden">Tempat tidur tersedia untuk {{ $room->name }}</label>
                                            <input type="number" id="{{ $fieldId }}" name="beds_available" value="{{ $shown }}" min="0" max="{{ $room->bed_capacity }}" step="1" inputmode="numeric" required @if ($rowError !== '') aria-invalid="true" aria-describedby="{{ $fieldId }}-error" @endif class="form-control form-control-sm{{ $rowError !== '' ? ' is-invalid' : '' }}">
                                            <span class="small text-secondary">dari {{ $room->bed_capacity }}</span>
                                            @if ($rowError !== '')
                                                <div id="{{ $fieldId }}-error" class="invalid-feedback d-block">{{ $rowError }}</div>
                                            @endif
                                        </div>
                                        <x-loading-button variant="outline-primary" class="btn-sm" loading-text="Menyimpan…" aria-label="Simpan ketersediaan {{ $room->name }}">Simpan</x-loading-button>
                                    </form>
                                @else
                                    {{ $room->beds_available }} dari {{ $room->bed_capacity }}
                                @endcan
                            </td>
                            <td data-label="Diperbarui">
                                <span>{{ $room->availability_updated_label }}</span>
                                @if ($room->availabilityUpdater)
                                    <div class="small text-secondary">oleh {{ $room->availabilityUpdater->name }}</div>
                                @endif
                            </td>
                            <td data-label="Tarif contoh">
                                {{ $room->sample_rate_label }}
                                @if (filled($room->rate_note))
                                    <div class="small text-secondary">{{ $room->rate_note }}</div>
                                @endif
                            </td>
                            <td data-label="Aksi" class="table-actions">
                                @if ($canManage)
                                    <a href="{{ route('admin.room-categories.edit', $room) }}" class="btn btn-sm btn-outline-primary" aria-label="Ubah {{ $room->name }}">Ubah</a>
                                    <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modal-hapus-kamar" data-confirm-action="{{ route('admin.room-categories.destroy', $room) }}" data-confirm-message="Hapus {{ $room->name }}? Gambarnya ikut terhapus dan halaman publiknya tidak dapat dibuka lagi. Bila hanya ingin menyembunyikannya, nonaktifkan saja lewat tombol Ubah. Tindakan ini tidak dapat dibatalkan." aria-label="Hapus {{ $room->name }}">Hapus</button>
                                @else
                                    <span class="text-secondary">—</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if ($canManage)
        <x-confirm-modal
            id="modal-hapus-kamar"
            title="Hapus kategori kamar?"
            message="Kategori kamar beserta gambarnya akan dihapus permanen dan halaman publiknya tidak dapat dibuka lagi. Bila hanya ingin menyembunyikannya, nonaktifkan saja."
            confirm-label="Ya, hapus"
            variant="danger"
            method="DELETE" />
    @endif
</x-layouts.admin>
