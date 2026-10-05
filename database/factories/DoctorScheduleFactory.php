<?php

namespace Database\Factories;

use App\Models\Doctor;
use App\Models\DoctorSchedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DoctorSchedule>
 */
class DoctorScheduleFactory extends Factory
{
    /**
     * Templat aktif hari Senin 08.00-12.00, kuota 20, berlaku mulai hari ini tanpa batas akhir. Poliklinik
     * mengikuti poliklinik utama dokter (isi `clinic_id` untuk menimpa).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'doctor_id' => Doctor::factory(),
            'clinic_id' => fn (array $attributes): int => Doctor::query()->findOrFail($attributes['doctor_id'])->clinic_id,
            'day_of_week' => 1,
            'start_time' => '08:00:00',
            'end_time' => '12:00:00',
            'quota' => 20,
            'valid_from' => today()->toDateString(),
            'valid_until' => null,
            'is_active' => true,
            'notes' => null,
        ];
    }

    /** Templat nonaktif: tidak dibangkitkan dan tidak dihitung bentrok. */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }
}
