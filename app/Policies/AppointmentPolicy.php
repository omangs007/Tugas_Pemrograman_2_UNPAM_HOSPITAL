<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Appointment;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * F6-R16 / 11 §8. Pasien: hanya pemilik (`patientProfile.user_id`), selain itu 404 agar keberadaan kode tidak dapat
 * diuji (BR-54). Staf: `appointments.view` (lihat, bukti) dan `appointments.manage` (batal, dipakai fase 7). Status dan
 * batas waktu pembatalan TIDAK di sini: pesan spesifiknya datang dari AppointmentStatusService. Super Admin lolos
 * lewat Gate::before.
 */
final class AppointmentPolicy
{
    /** Boleh membuat pendaftaran online: akun pasien aktif dengan profil aktif. */
    public function createOnline(User $user): bool
    {
        if (! $user->isPatient() || ! $user->is_active) {
            return false;
        }

        $profile = $user->loadMissing('patientProfile')->patientProfile;

        return $profile !== null && $profile->is_active === true;
    }

    public function view(User $user, Appointment $appointment): Response|bool
    {
        if ($user->isStaff()) {
            return $user->can('appointments.view');
        }

        return $this->owns($user, $appointment) ? true : Response::denyAsNotFound();
    }

    public function cancel(User $user, Appointment $appointment): Response|bool
    {
        if ($user->isStaff()) {
            return $user->can('appointments.manage');
        }

        return $this->owns($user, $appointment) ? true : Response::denyAsNotFound();
    }

    /** Daftar pendaftaran dan antrean (#38, fase 7a): staf dengan `appointments.view`. */
    public function viewAny(User $user): bool
    {
        return $user->isStaff() && $user->can('appointments.view');
    }

    /** Pendaftaran walk-in di loket (W-20): staf dengan `appointments.manage`. */
    public function createCounter(User $user): bool
    {
        return $user->isStaff() && $user->can('appointments.manage');
    }

    /**
     * Transisi status individual oleh staf (konfirmasi, check-in, selesai, batal, koreksi): `appointments.manage`.
     * Aturan status, pelaku, dan jendela waktu ada di AppointmentStatusService; pasien membatalkan lewat `cancel`.
     */
    public function transition(User $user, Appointment $appointment): bool
    {
        return $user->isStaff() && $user->can('appointments.manage');
    }

    /** Profil walk-in (`user_id` NULL) tidak dimiliki siapa pun secara online. */
    private function owns(User $user, Appointment $appointment): bool
    {
        $profile = $appointment->loadMissing('patientProfile')->patientProfile;

        return $user->isPatient()
            && $profile !== null
            && $profile->user_id !== null
            && (int) $profile->user_id === (int) $user->getKey();
    }
}
