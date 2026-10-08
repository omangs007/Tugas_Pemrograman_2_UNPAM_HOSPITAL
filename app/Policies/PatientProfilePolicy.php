<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PatientProfile;
use App\Models\User;

/**
 * F5-R14 / 11 §2.2: pemilik profil atau staf berizin. Hanya `view` dan `update`; kemampuan lain (daftar, buat
 * walk-in, ubah status) dibangun fase 7. Super Admin lolos lewat Gate::before sebelum sampai ke sini.
 * Pasien tidak memegang peran/izin Spatie, jadi bagi pasien hanya kepemilikan yang menentukan.
 */
final class PatientProfilePolicy
{
    public function view(User $user, PatientProfile $profile): bool
    {
        return $user->can('patients.view') || $this->owns($user, $profile);
    }

    public function update(User $user, PatientProfile $profile): bool
    {
        return $user->can('patients.manage') || $this->owns($user, $profile);
    }

    /** Profil walk-in (`user_id` NULL) tidak dimiliki siapa pun. */
    private function owns(User $user, PatientProfile $profile): bool
    {
        return $profile->user_id !== null && (int) $profile->user_id === (int) $user->getKey();
    }
}
