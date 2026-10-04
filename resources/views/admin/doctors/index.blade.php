@php
    $currentSort = (string) request()->query('urut', '');
    $sortUrl = fn (string $key): string => request()->fullUrlWithQuery(['urut' => $key, 'kembali' => null, 'page' => null]);
@endphp
<x-layouts.admin title="Dokter">
    <x-slot:breadcrumb>
        <x-breadcrumb home="admin.dashboard" :items="[['label' => 'Dokter']]" />
    </x-slot:breadcrumb>

    <x-slot:actions>
        @can('create', \App\Models\Doctor::class)
            <a href="{{ route('admin.doctors.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-lg" aria-hidden="true"></i> Tambah Dokter
            </a>
        @endcan
    </x-slot:actions>

    <x-data-table
        :rows="$doctors"
        :preserve="['urut']"
        search-placeholder="Cari nama dokter atau spesialisasi…"
        caption="Daftar dokter"
        empty-title="Belum ada dokter"
        empty-message="Tidak ada dokter dengan status dan filter yang dipilih.">
        <x-slot:filters>
            <div class="col-6 col-md-3 col-lg-2">
                <label for="filter-status" class="form-label fw-semibold">Status</label>
                <select id="filter-status" name="status" class="form-select">
                    @foreach (['active' => 'Aktif', 'inactive' => 'Nonaktif', 'archived' => 'Diarsipkan', 'semua' => 'Semua'] as $value => $label)
                        <option value="{{ $value }}" @selected($statusFilter === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-3 col-lg-3">
                <label for="filter-poli" class="form-label fw-semibold">Poliklinik</label>
                <select id="filter-poli" name="poli" class="form-select">
                    <option value="">Semua</option>
                    @foreach ($clinicOptions as $slug => $name)
                        <option value="{{ $slug }}" @selected(request()->query('poli') === $slug)>{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-3 col-lg-3">
                <label for="filter-spesialisasi" class="form-label fw-semibold">Spesialisasi</label>
                <select id="filter-spesialisasi" name="spesialisasi" class="form-select">
                    <option value="">Semua</option>
                    @foreach ($specialtyOptions as $slug => $name)
                        <option value="{{ $slug }}" @selected(request()->query('spesialisasi') === $slug)>{{ $name }}</option>
                    @endforeach
                </select>
            </div>
        </x-slot:filters>

        <x-slot:actions>
            <nav aria-label="Urutkan daftar" class="d-flex flex-wrap align-items-center gap-2">
                <span class="text-secondary">Urutkan:</span>
                @foreach (['nama' => 'Nama', '-terbaru' => 'Terbaru', 'urutan' => 'Urutan tampil'] as $key => $label)
                    <a href="{{ $sortUrl($key) }}" class="btn btn-sm {{ $currentSort === $key ? 'btn-primary' : 'btn-outline-secondary' }}"@if ($currentSort === $key) aria-current="true"@endif>{{ $label }}</a>
                @endforeach
            </nav>
        </x-slot:actions>

        <x-slot:head>
            <th scope="col">Dokter</th>
            <th scope="col">Spesialisasi</th>
            <th scope="col">Poliklinik</th>
            <th scope="col">Status</th>
            <th scope="col">Aksi</th>
        </x-slot:head>

        @foreach ($doctors as $doctor)
            <tr>
                <td data-label="Dokter">
                    <div class="d-flex align-items-center gap-2">
                        <div class="admin-thumb">
                            <x-image type="doctor" :media="$doctor->firstMedia('photo')" variant="thumb" ratio="1x1" :decorative="true" class="rounded-circle" />
                        </div>
                        <a href="{{ route('admin.doctors.show', $doctor) }}" class="fw-semibold text-decoration-none">{{ $doctor->display_name }}</a>
                    </div>
                </td>
                <td data-label="Spesialisasi">
                    @foreach ($doctor->specialties->sortByDesc(fn ($specialty) => (int) $specialty->pivot->is_primary) as $specialty)
                        <span class="d-block">{{ $specialty->name }}@if ($specialty->pivot->is_primary) <span class="badge text-bg-primary">(utama)</span>@endif</span>
                    @endforeach
                </td>
                <td data-label="Poliklinik">{{ $doctor->clinic->name }}</td>
                <td data-label="Status"><x-status-badge :status="$doctor->status" /></td>
                <td data-label="Aksi" class="table-actions">
                    <a href="{{ route('admin.doctors.show', $doctor) }}" class="btn btn-sm btn-outline-secondary" aria-label="Lihat {{ $doctor->display_name }}">Lihat</a>
                    @can('update', $doctor)
                        <a href="{{ route('admin.doctors.edit', $doctor) }}" class="btn btn-sm btn-outline-primary" aria-label="Ubah {{ $doctor->display_name }}">Ubah</a>
                    @endcan
                    @include('admin.doctors._status-actions', ['doctor' => $doctor])
                </td>
            </tr>
        @endforeach
    </x-data-table>

    @include('admin.doctors._status-modals')
</x-layouts.admin>
