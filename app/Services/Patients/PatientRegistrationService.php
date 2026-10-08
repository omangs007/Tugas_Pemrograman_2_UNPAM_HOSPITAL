<?php

declare(strict_types=1);

namespace App\Services\Patients;

use App\Enums\AccountType;
use App\Enums\BookingChannel;
use App\Models\PatientProfile;
use App\Models\User;
use App\Notifications\PatientWelcomeNotification;
use App\Services\Audit\AuditLogger;
use App\Services\Settings\SettingsService;
use App\Support\Actor;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
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
}
