<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PatientProfile;
use App\Models\User;

/**
 * F5-R14 / F7A-R13 / 11 §2.2: `view` dan `update` = pemilik profil atau staf berizin (fase 5). Kemampuan petugas fase 7a:
 * `viewAny` (daftar, `patients.view`), `create` (profil walk-in), `changeStatus`, dan `linkAccount` (semuanya `patients.manage`;
 * `linkAccount` juga mensyaratkan profil tanpa akun). Super Admin lolos lewat Gate::before sebelum sampai ke sini, sehingga
 * aturan "hanya profil tanpa akun" ditegakkan juga oleh view (T7) dan PatientAccountLinker. Pasien tidak memegang
 * peran/izin Spatie, jadi bagi pasien hanya kepemilikan yang menentukan `view`/`update`, dan kemampuan petugas selalu ditolak.
 */
final class PatientProfilePolicy
{
    /** Daftar pasien (#37). */
    public function viewAny(User $user): bool
    {
        return $user->isStaff() && $user->can('patients.view');
    }

    public function view(User $user, PatientProfile $profile): bool
    {
        return $user->can('patients.view') || $this->owns($user, $profile);
    }

    /** Membuat profil walk-in. */
    public function create(User $user): bool
    {
        return $user->isStaff() && $user->can('patients.manage');
    }

    public function update(User $user, PatientProfile $profile): bool
    {
        return $user->can('patients.manage') || $this->owns($user, $profile);
    }

    /** Aktifkan/nonaktifkan profil (aturan pendaftaran aktif ada di PatientRegistrationService::changeStatus). */
    public function changeStatus(User $user, PatientProfile $profile): bool
    {
        return $user->isStaff() && $user->can('patients.manage');
    }

    /** Hubungkan profil walk-in ke akun pasien: hanya profil yang belum punya akun. */
    public function linkAccount(User $user, PatientProfile $profile): bool
    {
        return $user->isStaff() && $user->can('patients.manage') && $profile->user_id === null;
    }

    /** Profil walk-in (`user_id` NULL) tidak dimiliki siapa pun. */
    private function owns(User $user, PatientProfile $profile): bool
    {
        return $profile->user_id !== null && (int) $profile->user_id === (int) $user->getKey();
    }
}
