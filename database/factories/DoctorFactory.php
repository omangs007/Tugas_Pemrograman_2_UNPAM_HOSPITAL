<?php

namespace Database\Factories;

use App\Enums\DoctorStatus;
use App\Enums\Gender;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\Specialty;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Doctor>
 */
class DoctorFactory extends Factory
{
    /**
     * Dokter aktif tanpa spesialisasi (gunakan withSpecialties()). Nama sengaja tanpa gelar
     * (kolom `name` tidak memuat gelar; gelar ada di `title_prefix`/`title_suffix`).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'clinic_id' => Clinic::factory(),
            'name' => fake()->unique()->firstName().' '.fake()->lastName(),
            'title_prefix' => 'dr.',
            'title_suffix' => 'Sp.PD',
            'gender' => fake()->randomElement(Gender::cases()),
            'short_bio' => fake()->sentence(),
            'bio' => fake()->paragraph(),
            'status' => DoctorStatus::Active,
            'archived_at' => null,
            'sort_order' => 0,
            'meta_title' => null,
            'meta_description' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => DoctorStatus::Inactive,
        ]);
    }

    /** Diarsipkan: `archived_at` terisi (invarian I-23). Pabrik berjalan tanpa guard, jadi kolom ini boleh diisi. */
    public function archived(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => DoctorStatus::Archived,
            'archived_at' => now()->subDays(30),
        ]);
    }

    /**
     * Membuat $count spesialisasi baru lalu menautkannya; yang pertama menjadi spesialisasi utama
     * (invarian I-5: tepat satu `is_primary`).
     */
    public function withSpecialties(int $count = 1): static
    {
        return $this->afterCreating(function (Doctor $doctor) use ($count): void {
            foreach (Specialty::factory()->count(max($count, 1))->create() as $index => $specialty) {
                $doctor->specialties()->attach($specialty->id, ['is_primary' => $index === 0]);
            }
        });
    }
}
