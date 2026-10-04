<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Doctor;
use App\Models\User;

/**
 * 04 §5 / 11 §8. Lihat: `doctors.view`; kelola: `doctors.manage`. Unggah/ganti foto memakai `update`.
 * Tidak ada `delete`: dokter dirujuk riwayat booking, hanya dinonaktifkan atau diarsipkan.
 * Aturan "arsip ditolak bila ada appointment aktif" ditegakkan Form Request/service (F2-R8), bukan di sini.
 */
final class DoctorPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('doctors.view');
    }

    public function view(User $user, Doctor $doctor): bool
    {
        return $user->can('doctors.view');
    }

    public function create(User $user): bool
    {
        return $user->can('doctors.manage');
    }

    public function update(User $user, Doctor $doctor): bool
    {
        return $user->can('doctors.manage');
    }

    public function changeStatus(User $user, Doctor $doctor): bool
    {
        return $user->can('doctors.manage');
    }

    public function archive(User $user, Doctor $doctor): bool
    {
        return $user->can('doctors.manage');
    }
}
