<?php

namespace Tests\Support;

use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\Specialty;

/**
 * Pembantu tes untuk data induk (spesialisasi, poliklinik, dokter) yang siap dipakai.
 */
trait InteractsWithMasterData
{
    /** @param  array<string, mixed>  $attrs */
    protected function createSpecialty(array $attrs = []): Specialty
    {
        return Specialty::factory()->create($attrs);
    }

    /** @param  array<string, mixed>  $attrs */
    protected function createClinic(array $attrs = []): Clinic
    {
        return Clinic::factory()->create($attrs);
    }

    /**
     * Membuat dokter dengan minimal satu spesialisasi; spesialisasi pertama menjadi utama.
     * Poliklinik: argumen $clinic, lalu `clinic_id` pada $attrs, lalu poliklinik baru.
     * Relasi `specialties` pada hasil sudah dimuat (aman di mode ketat).
     *
     * @param  array<string, mixed>  $attrs
     * @param  array<int, Specialty>  $specialties
     */
    protected function createDoctor(array $attrs = [], ?Clinic $clinic = null, array $specialties = []): Doctor
    {
        $clinicId = $clinic?->id ?? $attrs['clinic_id'] ?? $this->createClinic()->id;
        $doctor = Doctor::factory()->create(array_merge($attrs, ['clinic_id' => $clinicId]));

        foreach (array_values($specialties === [] ? [$this->createSpecialty()] : $specialties) as $index => $specialty) {
            $doctor->specialties()->attach($specialty->id, ['is_primary' => $index === 0]);
        }

        return $doctor->load('specialties');
    }
}
