<?php

namespace Tests\Feature\Auth;

use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\Specialty;
use App\Models\User;
use App\Policies\ClinicPolicy;
use App\Policies\DoctorPolicy;
use App\Policies\SpecialtyPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InteractsWithMasterData;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

/**
 * 04 §5 dan 11 §8 untuk SpecialtyPolicy, ClinicPolicy, DoctorPolicy.
 * Peran: super-admin, content-admin, registration-officer, ditambah pasien.
 */
class MasterDataPoliciesTest extends TestCase
{
    use InteractsWithMasterData;
    use InteractsWithStaff;
    use RefreshDatabase;

    /** @return array<string, array{0: string, 1: string, 2: array<string, bool>}> */
    public static function matrix(): array
    {
        $manage = ['super-admin' => true, 'content-admin' => true, 'registration-officer' => false, 'patient' => false];
        $view = ['super-admin' => true, 'content-admin' => true, 'registration-officer' => true, 'patient' => false];
        $cases = [];

        foreach (['specialty', 'clinic'] as $model) {
            foreach (['viewAny', 'view', 'create', 'update', 'delete'] as $ability) {
                $cases["{$model}.{$ability}"] = [$model, $ability, $manage];
            }
        }

        foreach (['viewAny', 'view'] as $ability) {
            $cases["doctor.{$ability}"] = ['doctor', $ability, $view];
        }

        foreach (['create', 'update', 'changeStatus', 'archive'] as $ability) {
            $cases["doctor.{$ability}"] = ['doctor', $ability, $manage];
        }

        return $cases;
    }

    /**
     * @param  array<string, bool>  $expected
     */
    #[DataProvider('matrix')]
    public function test_ability_per_role(string $model, string $ability, array $expected): void
    {
        $subject = match ($model) {
            'specialty' => $this->createSpecialty(),
            'clinic' => $this->createClinic(),
            'doctor' => $this->createDoctor(),
        };
        $class = $subject::class;
        $argument = in_array($ability, ['viewAny', 'create'], true) ? $class : $subject;

        foreach ($expected as $role => $allowed) {
            $user = $this->userFor($role);

            $this->assertSame(
                $allowed,
                Gate::forUser($user)->allows($ability, $argument),
                "{$role} -> {$class}::{$ability}",
            );
        }
    }

    public function test_doctor_has_no_delete_ability_even_for_super_admin_through_the_policy(): void
    {
        $this->assertFalse(method_exists(DoctorPolicy::class, 'delete'));

        // Gate::before meloloskan Super Admin hanya untuk ability yang dipanggil; route hapus dokter memang tidak ada.
        $this->assertTrue(Gate::forUser($this->userFor('super-admin'))->allows('delete', $this->createDoctor()));
        $this->assertFalse(Gate::forUser($this->userFor('content-admin'))->allows('delete', $this->createDoctor()));
        $this->assertFalse(Gate::forUser($this->userFor('registration-officer'))->allows('delete', $this->createDoctor()));
    }

    public function test_policies_are_discovered_automatically(): void
    {
        $this->assertInstanceOf(SpecialtyPolicy::class, Gate::getPolicyFor(Specialty::class));
        $this->assertInstanceOf(ClinicPolicy::class, Gate::getPolicyFor(Clinic::class));
        $this->assertInstanceOf(DoctorPolicy::class, Gate::getPolicyFor(Doctor::class));
    }

    public function test_policies_ignore_deletion_blocking_data_because_the_controller_decides(): void
    {
        $clinic = $this->createDoctor()->clinic()->first();
        $specialty = $this->createDoctor()->specialties()->first();

        $this->assertNotNull($clinic->deletionBlockedReason());
        $this->assertTrue(Gate::forUser($this->userFor('content-admin'))->allows('delete', $clinic));
        $this->assertTrue(Gate::forUser($this->userFor('content-admin'))->allows('delete', $specialty));
    }

    private function userFor(string $role): User
    {
        return $role === 'patient'
            ? User::factory()->create()
            : User::query()->findOrFail($this->createStaff($role)->id);
    }
}
