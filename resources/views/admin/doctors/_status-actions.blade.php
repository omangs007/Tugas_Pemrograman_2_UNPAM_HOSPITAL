{{-- Tombol aksi status dokter. Variabel: $doctor. Satu modal per aksi dipakai ulang lewat data-confirm-*. --}}
@php($doctorLabel = $doctor->display_name)
@can('changeStatus', $doctor)
    @if ($doctor->status === \App\Enums\DoctorStatus::Active)
        <button type="button" class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#modal-doctor-deactivate" data-confirm-action="{{ route('admin.doctors.status', $doctor) }}" data-confirm-message="Nonaktifkan {{ $doctorLabel }}? Dokter tidak akan tampil di situs publik dan tidak dapat dipesan.">Nonaktifkan<span class="visually-hidden"> {{ $doctorLabel }}</span></button>
    @elseif ($doctor->status === \App\Enums\DoctorStatus::Inactive)
        <button type="button" class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#modal-doctor-activate" data-confirm-action="{{ route('admin.doctors.status', $doctor) }}" data-confirm-message="Aktifkan kembali {{ $doctorLabel }}? Dokter akan tampil di situs publik.">Aktifkan<span class="visually-hidden"> {{ $doctorLabel }}</span></button>
    @else
        <button type="button" class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#modal-doctor-restore" data-confirm-action="{{ route('admin.doctors.status', $doctor) }}" data-confirm-message="Pulihkan {{ $doctorLabel }} dari arsip? Dokter dipulihkan sebagai nonaktif dan perlu diaktifkan kembali.">Pulihkan<span class="visually-hidden"> {{ $doctorLabel }}</span></button>
    @endif
@endcan
@can('archive', $doctor)
    @if ($doctor->status !== \App\Enums\DoctorStatus::Archived)
        <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modal-doctor-archive" data-confirm-action="{{ route('admin.doctors.archive', $doctor) }}" data-confirm-message="Arsipkan {{ $doctorLabel }}? Dokter tidak tampil di situs publik dan tidak dapat diberi jadwal. Arsip hanya diizinkan bila tidak ada pendaftaran aktif pada sesi mendatang.">Arsipkan<span class="visually-hidden"> {{ $doctorLabel }}</span></button>
    @endif
@endcan
