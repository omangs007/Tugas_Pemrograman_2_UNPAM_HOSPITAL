<?php

declare(strict_types=1);

namespace App\Services\Patients;

use App\Models\Appointment;
use App\Models\PatientProfile;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\Actor;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Menghubungkan profil walk-in (tanpa akun) ke akun pasien yang sudah ada (F7A-R12, 06 §16, 11 §6.8, BR-106..BR-109).
 * Tidak pernah ada pencocokan otomatis: petugas memilih akun dari email dan menegaskan verifikasi identitas di loket
 * (Form Request T7). Service tidak memeriksa izin Spatie (D-158). `problem()` dipakai panel UI tanpa kunci dan tanpa
 * tulis; `link()` mengulang pemeriksaan yang sama di bawah kunci karena instance pemanggil boleh basi.
 */
final class PatientAccountLinker
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** Akun pasien (bukan staf) dengan email itu; trim + huruf kecil. Akun nonaktif tetap ditemukan agar alasannya dapat dilaporkan. */
    public function findAccount(string $email): ?User
    {
        $email = mb_strtolower(trim($email));

        if ($email === '') {
            return null;
        }

        return User::query()->patients()->where('email', $email)->first();
    }

    /**
     * Alasan penghubungan ditolak (pesan Indonesia), atau null bila boleh. Tanpa kunci dan tanpa tulis.
     */
    public function problem(PatientProfile $walkIn, ?User $account): ?string
    {
        $accountProfile = $account === null
            ? null
            : PatientProfile::query()->where('user_id', $account->getKey())->first();

        return $this->evaluate($walkIn, $account, $accountProfile);
    }

    /**
     * @throws ValidationException kunci `account_email` bila syarat tidak terpenuhi (tidak ada yang ditulis)
     */
    public function link(PatientProfile $walkIn, User $account, Actor $actor): PatientProfile
    {
        return DB::transaction(function () use ($walkIn, $account, $actor): PatientProfile {
            $accountProfileId = PatientProfile::query()->where('user_id', $account->getKey())->value('id');

            // Kunci kedua profil menurut id menaik (tidak ada deadlock dengan BookingService yang mengunci satu profil).
            $ids = array_values(array_filter([(int) $walkIn->getKey(), $accountProfileId === null ? null : (int) $accountProfileId]));
            sort($ids);

            $profiles = PatientProfile::query()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $lockedWalkIn = $profiles->get((int) $walkIn->getKey());

            if ($lockedWalkIn === null) {
                throw ValidationException::withMessages(['account_email' => __('patients.link.ACCOUNT_NOT_FOUND')]);
            }

            $lockedAccountProfile = $accountProfileId === null ? null : $profiles->get((int) $accountProfileId);
            $lockedAccount = User::query()->whereKey($account->getKey())->lockForUpdate()->first();

            // Profil milik akun berubah sejak dibaca (mis. sudah ditautkan petugas lain): anggap akun sudah berprofil.
            $currentAccountProfileId = PatientProfile::query()->where('user_id', $account->getKey())->value('id');

            if (($currentAccountProfileId === null ? null : (int) $currentAccountProfileId) !== ($accountProfileId === null ? null : (int) $accountProfileId)) {
                throw ValidationException::withMessages(['account_email' => __('patients.link.ACCOUNT_HAS_HISTORY')]);
            }

            $message = $this->evaluate($lockedWalkIn, $lockedAccount, $lockedAccountProfile);

            if ($message !== null) {
                throw ValidationException::withMessages(['account_email' => $message]);
            }

            $removed = $lockedAccountProfile === null ? null : [
                'id' => $lockedAccountProfile->getKey(),
                'patient_number' => $lockedAccountProfile->patient_number,
                'consent_at' => $lockedAccountProfile->consent_at->format('Y-m-d H:i:s'),
                'consent_version' => $lockedAccountProfile->consent_version,
            ];

            // Profil kosong dihapus lebih dulu: `patient_profiles.user_id` unik.
            $lockedAccountProfile?->delete();

            $lockedWalkIn->forceFill([
                'user_id' => $lockedAccount->getKey(),
                'email' => $lockedAccount->email,
                'is_active' => (bool) $lockedAccount->is_active,
            ])->save();

            $this->audit->log(
                'patient.account_linked',
                $lockedWalkIn,
                ['removed_profile' => $removed],
                ['profile_id' => $lockedWalkIn->getKey(), 'user_id' => $lockedAccount->getKey()],
                'Profil pasien '.($lockedWalkIn->patient_number ?? '-').' dihubungkan ke akun pasien.',
                $actor,
            );

            $walkIn->setRawAttributes($lockedWalkIn->getRawOriginal(), true);
            $walkIn->unsetRelation('user');

            return $walkIn;
        });
    }

    /**
     * Urutan 06 §16: profil sudah terhubung, akun tidak ada atau bukan pasien, akun nonaktif, profil akun berriwayat.
     */
    private function evaluate(PatientProfile $walkIn, ?User $account, ?PatientProfile $accountProfile): ?string
    {
        if ($walkIn->user_id !== null) {
            return __('patients.link.PROFILE_ALREADY_LINKED');
        }

        if ($account === null || ! $account->isPatient()) {
            return __('patients.link.ACCOUNT_NOT_FOUND');
        }

        if (! $account->is_active) {
            return __('patients.link.ACCOUNT_INACTIVE');
        }

        if ($accountProfile !== null && Appointment::query()->where('patient_profile_id', $accountProfile->getKey())->exists()) {
            return __('patients.link.ACCOUNT_HAS_HISTORY');
        }

        return null;
    }
}
