<?php

namespace Database\Factories;

use App\Enums\ExceptionType;
use App\Models\Doctor;
use App\Models\ScheduleException;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScheduleException>
 */
class ScheduleExceptionFactory extends Factory
{
    /**
     * Cuti dokter tiga hari yang dimulai 7 hari lagi. Kombinasi jenis dan dokter selalu sah menurut CHECK
     * `chk_schedule_exceptions_scope`: gunakan state (hospitalHoliday, nationalHoliday, global) untuk jenis lain.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'doctor_id' => Doctor::factory(),
            'type' => ExceptionType::DoctorLeave,
            'start_date' => today()->addDays(7)->toDateString(),
            'end_date' => today()->addDays(9)->toDateString(),
            'reason' => 'Cuti tahunan',
        ];
    }

    /** Cuti dokter (`doctor_id` wajib). */
    public function leave(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => ExceptionType::DoctorLeave,
            'doctor_id' => $attributes['doctor_id'] ?? Doctor::factory(),
            'reason' => 'Cuti tahunan',
        ]);
    }

    /** Libur rumah sakit: berlaku untuk seluruh dokter (`doctor_id` NULL). */
    public function hospitalHoliday(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => ExceptionType::HospitalHoliday,
            'doctor_id' => null,
            'reason' => 'Libur rumah sakit',
        ]);
    }

    /** Libur nasional: berlaku untuk seluruh dokter (`doctor_id` NULL). */
    public function nationalHoliday(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => ExceptionType::NationalHoliday,
            'doctor_id' => null,
            'reason' => 'Libur nasional',
        ]);
    }

    /** Jenis "lainnya" yang berlaku untuk seluruh dokter (`doctor_id` NULL). */
    public function global(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => ExceptionType::Other,
            'doctor_id' => null,
            'reason' => 'Penutupan khusus',
        ]);
    }
}
