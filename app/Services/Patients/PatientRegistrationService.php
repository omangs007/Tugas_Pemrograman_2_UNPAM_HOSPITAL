<?php

declare(strict_types=1);

namespace App\Services\Patients;

use App\Enums\AccountType;
use App\Enums\BookingChannel;
use App\Models\Appointment;
use App\Models\PatientProfile;
use App\Models\User;
use App\Notifications\PatientWelcomeNotification;
use App\Rules\IndonesianPhone;
use App\Services\Audit\AuditLogger;
use App\Services\Settings\SettingsService;
use App\Support\Actor;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Pembuatan akun pasien beserta profilnya dan perubahan profil (F5-R9, F5-R20, 11 §6.8). Service tidak membaca
 * request(): validasi, normalisasi (email huruf kecil, telepon tanpa tanda baca), dan penanganan galat HTTP
 * ada di Form Request dan controller. Email unik ditegakkan DB (`users_email_unique`); pelanggarannya dilempar
 * apa adanya sebagai UniqueConstraintViolationException.
 */
final class PatientRegistrationService
{
    /** Kolom profil yang boleh diubah pasien lewat updateProfile() (PAS-05). */
    private const PROFILE_FIELDS = ['full_name', 'birth_date', 'gender', 'phone', 'email', 'address'];

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly SettingsService $settings,
    ) {}

    /**
     * Registrasi online: akun pasien + profil + nomor pasien + audit dalam SATU transaksi; email selamat datang
     * dikirim sesudah commit dan kegagalannya tidak membatalkan registrasi (06 §18).
     *
     * @param  array<string, mixed>  $validated  full_name, email, phone, birth_date, gender, address, password
     */
    public function register(array $validated): User
    {
        $user = DB::transaction(function () use ($validated): User {
            $user = new User;
            $user->forceFill([
                'name' => $validated['full_name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'],
                'password' => $validated['password'],
                'account_type' => AccountType::Patient,
                'is_active' => true,
                'must_change_password' => false,
            ])->save();

            $consentVersion = $this->settings->string('privacy.consent_version', '1.0');

            $profile = new PatientProfile;
            $profile->fill([
                'full_name' => $validated['full_name'],
                'birth_date' => $validated['birth_date'],
                'gender' => $validated['gender'],
                'phone' => $validated['phone'],
                'email' => $validated['email'],
                'address' => $validated['address'],
                'registration_channel' => BookingChannel::Online,
                'consent_at' => now(),
                'consent_version' => $consentVersion,
                'is_active' => true,
            ]);
            $profile->forceFill(['user_id' => $user->getKey(), 'created_by_user_id' => null])->save();

            // Id baru diketahui setelah insert, jadi nomor pasien diisi dalam transaksi yang sama.
            $profile->forceFill(['patient_number' => PatientNumberGenerator::format((int) $profile->getKey())])->save();

            $this->audit->log(
                'patient.registered',
                $profile,
                null,
                [
                    'patient_number' => $profile->patient_number,
                    'registration_channel' => BookingChannel::Online->value,
                    'consent_version' => $consentVersion,
                ],
                "Pasien {$profile->patient_number} mendaftar akun secara online.",
                Actor::patient($user),
            );

            return $user->refresh()->load('patientProfile');
        });

        try {
            $user->notify(new PatientWelcomeNotification);
        } catch (Throwable $e) {
            report($e);
        }

        return $user;
    }

    /**
     * Mengubah enam kolom profil (+ menghapus nomor BPJS bila diminta) dan menyinkronkan nama, email, dan telepon
     * akun login (BR-151) dalam satu transaksi. Audit `patient.updated` hanya bila ada perubahan profil.
     *
     * @param  array<string, mixed>  $validated  full_name, birth_date, gender, phone, email, address, remove_bpjs (opsional)
     */
    public function updateProfile(PatientProfile $profile, array $validated, Actor $actor): PatientProfile
    {
        return DB::transaction(function () use ($profile, $validated, $actor): PatientProfile {
            $profile->fill(Arr::only($validated, self::PROFILE_FIELDS));

            if (filter_var($validated['remove_bpjs'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                $profile->bpjs_number = null;
            }

            // Diambil sebelum save(): getChanges() bertahan pada instance dan bisa memuat perubahan lama.
            $changed = $profile->isDirty();
            $profile->save();
            $diff = $changed ? $this->audit->diff($profile) : ['old' => [], 'new' => []];

            $profile->loadMissing('user');
            $account = $profile->user;

            if ($account !== null) {
                $account->forceFill([
                    'name' => $profile->full_name,
                    'email' => $profile->email ?? $account->email,
                    'phone' => $profile->phone,
                ])->save();
            }

            // bpjs_number_last4 turunan dari bpjs_number dan tidak dicatat; updated_at sudah dibuang diff().
            $old = Arr::except($diff['old'], ['bpjs_number_last4']);
            $new = Arr::except($diff['new'], ['bpjs_number_last4']);

            if ($new !== []) {
                $this->audit->log(
                    'patient.updated',
                    $profile,
                    $old,
                    $new,
                    'Profil pasien '.($profile->patient_number ?? '-').' diperbarui.',
                    $actor,
                );
            }

            return $profile->refresh();
        });
    }

    /**
     * Profil pasien walk-in yang dibuat petugas loket (F7A-R8, BR-98..BR-103): tanpa akun login, kanal `loket`,
     * persetujuan privasi lisan dicatat sebagai `consent_at = now()` dan versi persetujuan yang berlaku. Hanya enam
     * kunci identitas yang dibaca; nomor BPJS tidak diisi di sini (V-13: hanya lewat booking `save_bpjs` atau pasien
     * sendiri). Nomor pasien diisi sesudah insert dalam transaksi yang sama (D-145). Audit `patient.created` tanpa data pribadi.
     *
     * @param  array<string, mixed>  $validated  full_name, birth_date (Y-m-d), gender, phone, address, email (opsional)
     */
    public function createWalkIn(array $validated, User $staff): PatientProfile
    {
        // Akun petugas yang belum tersimpan gagal di sini, sebelum ada yang ditulis.
        $actor = Actor::staff($staff);

        return DB::transaction(function () use ($validated, $staff, $actor): PatientProfile {
            $consentVersion = $this->settings->string('privacy.consent_version', '1.0');
            $email = mb_strtolower(trim((string) ($validated['email'] ?? '')));

            $profile = new PatientProfile;
            $profile->fill([
                'full_name' => trim((string) $validated['full_name']),
                'birth_date' => $validated['birth_date'],
                'gender' => $validated['gender'],
                'phone' => IndonesianPhone::normalize((string) $validated['phone']),
                'email' => $email === '' ? null : $email,
                'address' => trim((string) $validated['address']),
                'registration_channel' => BookingChannel::Loket,
                'consent_at' => now(),
                'consent_version' => $consentVersion,
                'is_active' => true,
            ]);
            $profile->forceFill(['user_id' => null, 'created_by_user_id' => $staff->getKey()])->save();

            // Id baru diketahui setelah insert, jadi nomor pasien diisi dalam transaksi yang sama.
            $profile->forceFill(['patient_number' => PatientNumberGenerator::format((int) $profile->getKey())])->save();

            $this->audit->log(
                'patient.created',
                $profile,
                null,
                [
                    'patient_number' => $profile->patient_number,
                    'registration_channel' => BookingChannel::Loket->value,
                    'consent_version' => $consentVersion,
                ],
                "Profil pasien {$profile->patient_number} dibuat oleh petugas (walk-in).",
                $actor,
            );

            return $profile->refresh();
        });
    }

    /**
     * Aktifkan atau nonaktifkan profil (F7A-R10, BR-105). Satu transaksi: profil dikunci lebih dulu, lalu pendaftaran
     * aktif (`pending`/`confirmed`/`checked_in`) dihitung di bawah kunci itu; menonaktifkan dengan N > 0 ditolak
     * (`ValidationException` kunci `is_active`). Status yang sama tidak menulis apa pun dan tidak diaudit. Profil
     * berakun: `users.is_active` ikut berubah dan, saat dinonaktifkan, baris `sessions` akun dihapus agar login
     * pasien berakhir. Kewajiban alasan saat menonaktifkan (V-18) ada di Form Request; service hanya mencatatnya.
     */
    public function changeStatus(PatientProfile $profile, bool $active, Actor $actor, ?string $reason = null): PatientProfile
    {
        $reason = $reason === null ? '' : trim($reason);
        $reason = $reason === '' ? null : $reason;

        return DB::transaction(function () use ($profile, $active, $actor, $reason): PatientProfile {
            $locked = PatientProfile::query()->whereKey($profile->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->is_active === $active) {
                $profile->setRawAttributes($locked->getRawOriginal(), true);

                return $profile;
            }

            if (! $active) {
                $count = Appointment::query()->where('patient_profile_id', $locked->getKey())->active()->count();

                if ($count > 0) {
                    throw ValidationException::withMessages([
                        'is_active' => __('patients.status.active_appointments', ['count' => $count]),
                    ]);
                }
            }

            $locked->forceFill(['is_active' => $active])->save();

            if ($locked->user_id !== null) {
                User::query()->whereKey($locked->user_id)->update(['is_active' => $active]);

                if (! $active) {
                    DB::table('sessions')->where('user_id', $locked->user_id)->delete();
                }
            }

            $new = ['is_active' => $active];

            if ($reason !== null) {
                $new['reason'] = $reason;
            }

            $this->audit->log(
                'patient.status_changed',
                $locked,
                ['is_active' => ! $active],
                $new,
                __($active ? 'patients.status.activated' : 'patients.status.deactivated', ['number' => $locked->patient_number ?? '-']),
                $actor,
            );

            $profile->setRawAttributes($locked->getRawOriginal(), true);

            return $profile;
        });
    }
}
