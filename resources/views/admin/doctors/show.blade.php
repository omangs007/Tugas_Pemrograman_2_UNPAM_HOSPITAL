@php
    $photo = $doctor->firstMedia('photo');
    $specialties = $doctor->specialties->sortByDesc(fn ($specialty) => (int) $specialty->pivot->is_primary);
    $paragraphs = $doctor->bio === null ? [] : preg_split('/\R{2,}/', trim($doctor->bio));
@endphp
<x-layouts.admin :title="$doctor->display_name">
    <x-slot:breadcrumb>
        <x-breadcrumb home="admin.dashboard" :items="[
            ['label' => 'Dokter', 'url' => route('admin.doctors.index')],
            ['label' => $doctor->display_name],
        ]" />
    </x-slot:breadcrumb>

    <x-slot:actions>
        <a href="{{ route('admin.doctors.index') }}" class="btn btn-outline-secondary">Kembali ke daftar</a>
        @can('update', $doctor)
            <a href="{{ route('admin.doctors.edit', $doctor) }}" class="btn btn-primary">
                <i class="bi bi-pencil" aria-hidden="true"></i> Ubah Data
            </a>
        @endcan
        @include('admin.doctors._status-actions', ['doctor' => $doctor])
    </x-slot:actions>

    <div class="row g-4">
        <div class="col-12 col-md-4 col-lg-3">
            <div class="card">
                <div class="card-body">
                    <x-image type="doctor" :media="$photo" :alt="$photo?->alt_text ?? 'Foto '.$doctor->display_name" ratio="1x1" class="rounded" />
                    <p class="form-text mb-0 mt-2">{{ $photo ? 'Foto profil dokter.' : 'Belum ada foto; situs publik memakai gambar pengganti.' }}</p>
                </div>
            </div>
            {{-- Ganti foto dokter --}}
        </div>

        <div class="col-12 col-md-8 col-lg-9">
            <div class="card mb-4">
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Nama tampilan</dt>
                        <dd class="col-sm-8">{{ $doctor->display_name }}</dd>
                        <dt class="col-sm-4">Status</dt>
                        <dd class="col-sm-8"><x-status-badge :status="$doctor->status" />@if ($doctor->archived_at) <span class="text-secondary small">sejak {{ $doctor->archived_at->translatedFormat('j F Y') }}</span>@endif</dd>
                        <dt class="col-sm-4">Jenis kelamin</dt>
                        <dd class="col-sm-8">{{ $doctor->gender->label() }}</dd>
                        <dt class="col-sm-4">Poliklinik utama</dt>
                        <dd class="col-sm-8">{{ $doctor->clinic->name }}</dd>
                        <dt class="col-sm-4">Spesialisasi</dt>
                        <dd class="col-sm-8">
                            @foreach ($specialties as $specialty)
                                <span class="d-block">{{ $specialty->name }}@if ($specialty->pivot->is_primary) <span class="badge text-bg-primary">(utama)</span>@endif</span>
                            @endforeach
                        </dd>
                        <dt class="col-sm-4">Urutan tampil</dt>
                        <dd class="col-sm-8">{{ $doctor->sort_order }}</dd>
                        <dt class="col-sm-4">Dibuat</dt>
                        <dd class="col-sm-8">{{ $doctor->created_at->translatedFormat('j F Y H:i') }}</dd>
                        <dt class="col-sm-4">Diperbarui</dt>
                        <dd class="col-sm-8">{{ $doctor->updated_at->translatedFormat('j F Y H:i') }}</dd>
                    </dl>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header fw-semibold">Profil</div>
                <div class="card-body">
                    <h2 class="h6">Profil singkat</h2>
                    <p>{{ $doctor->short_bio ?? '—' }}</p>
                    <h2 class="h6">Profil lengkap</h2>
                    @forelse ($paragraphs as $paragraph)
                        <p>{{ $paragraph }}</p>
                    @empty
                        <p class="mb-0">—</p>
                    @endforelse
                </div>
            </div>

            <div class="card">
                <div class="card-header fw-semibold">SEO</div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Meta title</dt>
                        <dd class="col-sm-8">{{ $doctor->meta_title ?? 'Otomatis: nama tampilan' }}</dd>
                        <dt class="col-sm-4">Meta description</dt>
                        <dd class="col-sm-8">{{ $doctor->meta_description ?? 'Otomatis: profil singkat' }}</dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>

    @include('admin.doctors._status-modals')
</x-layouts.admin>
