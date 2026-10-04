<?php

namespace Tests\Feature\Models;

use App\Enums\ClinicType;
use App\Models\Clinic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\InteractsWithMasterData;
use Tests\TestCase;

class InteractsWithMasterDataTest extends TestCase
{
    use InteractsWithMasterData;
    use RefreshDatabase;

    public function test_create_specialty_and_clinic_accept_overrides(): void
    {
        $specialty = $this->createSpecialty(['name' => 'Anak', 'slug' => 'anak']);
        $clinic = $this->createClinic(['specialty_id' => $specialty->id, 'code' => 'ank']);

        $this->assertSame('Anak', $specialty->name);
        $this->assertSame('ANK', $clinic->fresh()->code);
        $this->assertSame($specialty->id, $clinic->specialty_id);
    }

    public function test_create_doctor_without_arguments_builds_clinic_and_one_primary_specialty(): void
    {
        $doctor = $this->createDoctor();

        $this->assertSame(1, Clinic::query()->count());
        $this->assertCount(1, $doctor->specialties);
        $this->assertSame(1, (int) $doctor->specialties->first()->pivot->is_primary);
        $this->assertSame($doctor->specialties->first()->id, $doctor->primary_specialty->id);
        $this->assertSame(ClinicType::Poliklinik, $doctor->clinic()->first()->type);
    }

    public function test_create_doctor_uses_the_given_clinic_and_specialties_with_the_first_primary(): void
    {
        $clinic = $this->createClinic();
        $first = $this->createSpecialty();
        $second = $this->createSpecialty();

        $doctor = $this->createDoctor(['name' => 'Maya Kartika Sari'], $clinic, [$first, $second]);

        $this->assertSame($clinic->id, $doctor->clinic_id);
        $this->assertSame('Maya Kartika Sari', $doctor->name);
        $this->assertSame(1, Clinic::query()->count());
        $this->assertSame(
            [$first->id => 1, $second->id => 0],
            DB::table('doctor_specialties')->where('doctor_id', $doctor->id)->pluck('is_primary', 'specialty_id')->map(fn ($v) => (int) $v)->all(),
        );
        $this->assertTrue($doctor->primary_specialty->is($first));
    }

    public function test_create_doctor_takes_clinic_id_from_attributes_when_no_clinic_is_given(): void
    {
        $clinic = $this->createClinic();

        $doctor = $this->createDoctor(['clinic_id' => $clinic->id]);

        $this->assertSame($clinic->id, $doctor->clinic_id);
        $this->assertSame(1, Clinic::query()->count());
    }
}
