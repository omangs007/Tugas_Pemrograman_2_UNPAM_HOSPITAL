<?php

namespace Database\Factories;

use App\Enums\BookingChannel;
use App\Enums\Gender;
use App\Models\PatientProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PatientProfile>
 */
class PatientProfileFactory extends Factory
{
    /**
     * Pasien walk-in tanpa akun (kanal loket) yang sudah memberi persetujuan privasi. `patient_number` diisi
     * langsung berformat `P` + 6 digit; di aplikasi nyata nomor dibuat PatientNumberGenerator (fase 5).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => null,
            'patient_number' => fake()->unique()->numerify('P######'),
            'full_name' => fake()->name(),
            'birth_date' => fake()->dateTimeBetween('-80 years', '-1 year')->format('Y-m-d'),
            'gender' => fake()->randomElement(Gender::cases()),
            'phone' => fake()->numerify('08##########'),
            'email' => null,
            'address' => fake()->address(),
            'bpjs_number' => null,
            'bpjs_number_last4' => null,
            'registration_channel' => BookingChannel::Loket,
            'created_by_user_id' => null,
            'consent_at' => now(),
            'consent_version' => '1.0',
            'is_active' => true,
        ];
    }

    /** Pasien berakun (kanal online): membuat akun pasien baru dan menyamakan email profil dengan email akun. */
    public function withAccount(): static
    {
        return $this->state(fn (array $attributes): array => [
            'user_id' => User::factory()->patient(),
            'registration_channel' => BookingChannel::Online,
        ])->afterCreating(function (PatientProfile $profile): void {
            $user = User::query()->findOrFail($profile->user_id);

            $profile->forceFill(['email' => $user->email])->save();
        });
    }

    /** Profil dinonaktifkan (`is_active = false`). */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }
}
