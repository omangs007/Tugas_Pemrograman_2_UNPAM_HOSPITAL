<?php

namespace Tests\Support;

use App\Enums\BookingChannel;
use App\Models\PatientProfile;
use App\Models\User;
use App\Services\Patients\PatientNumberGenerator;

/**
 * Pembantu tes untuk akun pasien berprofil (fase 5). Dipakai bersama RefreshDatabase.
 */
trait InteractsWithPatients
{
    /** Password bawaan akun pasien uji (pola AdminLoginTest::PASSWORD). */
    protected function patientPassword(): string
    {
        return 'Rahasia123';
    }

    /**
     * Membuat akun pasien aktif beserta profil online yang terhubung: nomor pasien berformat
     * PatientNumberGenerator, email dan nama profil mengikuti akun, telepon akun mengikuti telepon profil.
     * Argumen pemanggil menimpa nilai bawaan. Relasi `patientProfile` sudah termuat pada hasil.
     *
     * @param  array<string, mixed>  $userAttributes
     * @param  array<string, mixed>  $profileAttributes
     */
    protected function createPatient(array $userAttributes = [], array $profileAttributes = []): User
    {
        $user = User::factory()->patient()->create($userAttributes + ['password' => $this->patientPassword()]);

        $profile = PatientProfile::factory()->create($profileAttributes + [
            'user_id' => $user->id,
            'full_name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone ?? '081234567890',
            'registration_channel' => BookingChannel::Online,
        ]);

        if (! array_key_exists('patient_number', $profileAttributes)) {
            $profile->forceFill(['patient_number' => PatientNumberGenerator::format($profile->id)])->save();
        }

        if ($user->phone === null) {
            $user->forceFill(['phone' => $profile->phone])->save();
        }

        return $user->fresh('patientProfile');
    }

    /**
     * Data registrasi valid menurut 12 V-01 (belum dinormalisasi: telepon masih berformat dengan tanda hubung).
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function registrationPayload(array $overrides = []): array
    {
        return array_merge([
            'full_name' => 'Siti Rahma',
            'email' => 'siti@contoh.test',
            'phone' => '0812-3456-7890',
            'birth_date' => '1990-03-12',
            'gender' => 'P',
            'address' => 'Jl. Merpati No. 5, Pamulang',
            'password' => 'Rahasia123',
            'password_confirmation' => 'Rahasia123',
            'consent' => '1',
        ], $overrides);
    }
}
