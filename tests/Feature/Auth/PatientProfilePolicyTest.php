<?php

namespace Tests\Feature\Auth;

use App\Models\PatientProfile;
use App\Models\User;
use App\Policies\PatientProfilePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\Support\InteractsWithPatients;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

/**
 * F5-R14 / PAS-05 (dasar): `view` dan `update` profil pasien. Pemilik boleh; pasien lain tidak; staf menurut izin
 * `patients.view` / `patients.manage` (hanya Petugas Pendaftaran di RolePermissionSeeder); Super Admin lewat Gate::before.
 */
class PatientProfilePolicyTest extends TestCase
{
    use InteractsWithPatients;
    use InteractsWithStaff;
    use RefreshDatabase;

    private function allows(User $user, string $ability, PatientProfile $profile): bool
    {
        return Gate::forUser($user)->allows($ability, $profile);
    }

    public function test_the_owner_can_view_and_update_their_own_profile(): void
    {
        $owner = $this->createPatient();

        $this->assertTrue($this->allows($owner, 'view', $owner->patientProfile));
        $this->assertTrue($this->allows($owner, 'update', $owner->patientProfile));
        $this->assertTrue($owner->can('view', $owner->patientProfile));
        $this->assertTrue($owner->can('update', $owner->patientProfile));
    }

    public function test_another_patient_cannot_view_or_update_the_profile(): void
    {
        $owner = $this->createPatient();
        $other = $this->createPatient();

        $this->assertFalse($this->allows($other, 'view', $owner->patientProfile));
        $this->assertFalse($this->allows($other, 'update', $owner->patientProfile));
        $this->assertFalse($this->allows($owner, 'view', $other->patientProfile));
        $this->assertFalse($this->allows($owner, 'update', $other->patientProfile));
    }

    public function test_a_walk_in_profile_without_an_account_belongs_to_no_patient(): void
    {
        $walkIn = PatientProfile::factory()->create();
        $patient = $this->createPatient();

        $this->assertNull($walkIn->user_id);
        $this->assertFalse($this->allows($patient, 'view', $walkIn));
        $this->assertFalse($this->allows($patient, 'update', $walkIn));
    }

    /** @return array<string, array{0: string, 1: bool, 2: bool}> */
    public static function staffRoles(): array
    {
        return [
            'super-admin (Gate::before)' => ['super-admin', true, true],
            'content-admin tanpa izin pasien' => ['content-admin', false, false],
            'registration-officer memegang patients.view dan patients.manage' => ['registration-officer', true, true],
        ];
    }

    #[DataProvider('staffRoles')]
    public function test_staff_access_follows_the_patient_permissions_of_the_role(string $role, bool $canView, bool $canUpdate): void
    {
        $staff = $this->createStaff($role);
        $profile = $this->createPatient()->patientProfile;

        $this->assertSame($canView, $this->allows($staff, 'view', $profile), "{$role} view");
        $this->assertSame($canUpdate, $this->allows($staff, 'update', $profile), "{$role} update");
    }

    public function test_view_requires_the_view_permission_and_does_not_grant_update(): void
    {
        $staff = $this->createStaff('content-admin');
        $staff->givePermissionTo('patients.view');
        $staff = User::query()->findOrFail($staff->id);
        $profile = $this->createPatient()->patientProfile;

        $this->assertTrue($this->allows($staff, 'view', $profile));
        $this->assertFalse($this->allows($staff, 'update', $profile));
    }

    public function test_update_requires_the_manage_permission_and_does_not_grant_view(): void
    {
        // Izin tidak bertingkat di Policy; peran nyata memegang keduanya (lihat staffRoles).
        $staff = $this->createStaff('content-admin');
        $staff->givePermissionTo('patients.manage');
        $staff = User::query()->findOrFail($staff->id);
        $profile = $this->createPatient()->patientProfile;

        $this->assertTrue($this->allows($staff, 'update', $profile));
        $this->assertFalse($this->allows($staff, 'view', $profile));
    }

    public function test_super_admin_passes_through_gate_before_even_without_any_permission(): void
    {
        $super = $this->createStaff('super-admin');
        Role::findByName('super-admin')->syncPermissions([]);
        $super = User::query()->findOrFail($super->id);
        $profile = $this->createPatient()->patientProfile;

        $this->assertTrue($super->getAllPermissions()->isEmpty());
        $this->assertTrue($this->allows($super, 'view', $profile));
        $this->assertTrue($this->allows($super, 'update', $profile));
    }

    public function test_a_guest_is_denied(): void
    {
        $profile = $this->createPatient()->patientProfile;

        $this->assertFalse(Gate::allows('view', $profile));
        $this->assertFalse(Gate::allows('update', $profile));
    }

    public function test_the_policy_is_discovered_automatically(): void
    {
        $profile = PatientProfile::factory()->create();

        $this->assertInstanceOf(PatientProfilePolicy::class, Gate::getPolicyFor($profile));
    }
}
