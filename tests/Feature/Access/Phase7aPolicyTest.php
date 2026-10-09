<?php

namespace Tests\Feature\Access;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\PatientProfile;
use App\Models\PracticeSession;
use App\Models\User;
use App\Policies\AppointmentPolicy;
use App\Policies\PatientProfilePolicy;
use App\Policies\PracticeSessionPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InteractsWithPatients;
use Tests\Support\InteractsWithSchedules;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

/**
 * F7A-R13 (04 §5, 11 §8): kemampuan baru Policy fase 7a per peran: `PatientProfilePolicy::viewAny|create|changeStatus|linkAccount`,
 * `AppointmentPolicy::viewAny|createCounter|transition`, `PracticeSessionPolicy::markNoShow`. Peran: Super Admin (Gate::before),
 * Admin Konten (tanpa izin pasien/pendaftaran), Petugas Pendaftaran, dan pasien. Service tidak memeriksa izin (D-158); Policy
 * dan middleware `permission:` yang menjaganya.
 */
class Phase7aPolicyTest extends TestCase
{
    use InteractsWithPatients;
    use InteractsWithSchedules;
    use InteractsWithStaff;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();
    }

    private function userWithRole(string $role): User
    {
        return $role === 'patient' ? $this->createPatient() : $this->createStaff($role);
    }

    /** Staf Admin Konten yang diberi tepat satu izin tambahan (izin dimuat ulang lewat pengguna baru). */
    private function staffWithOnly(string $permission): User
    {
        $staff = $this->createStaff('content-admin');
        $staff->givePermissionTo($permission);

        return User::query()->findOrFail($staff->id);
    }

    private function allows(User $user, string $ability, mixed $subject): bool
    {
        return Gate::forUser($user)->allows($ability, $subject);
    }

    private function appointment(?PatientProfile $profile = null): Appointment
    {
        return $this->bookAppointment(
            $this->createSession(),
            AppointmentStatus::Pending,
            $profile === null ? [] : ['patient_profile_id' => $profile->id],
        );
    }

    // ------------------------------------------------------------------ PatientProfilePolicy

    /**
     * @return array<string, array{string, bool, bool, bool, bool, bool}>
     */
    public static function patientProfileMatrix(): array
    {
        // [peran, viewAny, create, changeStatus (kedua profil), linkAccount profil walk-in, linkAccount profil berakun]
        return [
            'super admin (Gate::before, juga untuk profil berakun)' => ['super-admin', true, true, true, true, true],
            'admin konten' => ['content-admin', false, false, false, false, false],
            'petugas pendaftaran' => ['registration-officer', true, true, true, true, false],
            'pasien' => ['patient', false, false, false, false, false],
        ];
    }

    #[DataProvider('patientProfileMatrix')]
    public function test_patient_profile_abilities_follow_the_role(string $role, bool $viewAny, bool $create, bool $changeStatus, bool $linkWalkIn, bool $linkLinked): void
    {
        $user = $this->userWithRole($role);
        $walkIn = PatientProfile::factory()->create();
        $linked = $this->createPatient()->patientProfile;

        $this->assertSame($viewAny, $this->allows($user, 'viewAny', PatientProfile::class), 'viewAny');
        $this->assertSame($create, $this->allows($user, 'create', PatientProfile::class), 'create');
        $this->assertSame($changeStatus, $this->allows($user, 'changeStatus', $walkIn), 'changeStatus walk-in');
        $this->assertSame($changeStatus, $this->allows($user, 'changeStatus', $linked), 'changeStatus berakun');
        $this->assertSame($linkWalkIn, $this->allows($user, 'linkAccount', $walkIn), 'linkAccount walk-in');
        $this->assertSame($linkLinked, $this->allows($user, 'linkAccount', $linked), 'linkAccount berakun');
    }

    public function test_link_account_is_denied_to_the_officer_for_a_profile_that_already_has_an_account(): void
    {
        $officer = $this->createStaff('registration-officer');
        $linked = $this->createPatient()->patientProfile;

        $this->assertNotNull($linked->user_id);
        $this->assertFalse($this->allows($officer, 'linkAccount', $linked));
        $this->assertTrue($this->allows($officer, 'changeStatus', $linked), 'Status profil berakun boleh diubah petugas.');
    }

    public function test_the_super_admin_gate_bypass_is_why_the_service_and_view_enforce_unlinked_profiles_too(): void
    {
        $super = $this->createStaff('super-admin');
        $linked = $this->createPatient()->patientProfile;

        $this->assertTrue($this->allows($super, 'linkAccount', $linked), 'Gate::before melewati Policy; PatientAccountLinker menolak PROFILE_ALREADY_LINKED.');
    }

    /**
     * @return array<string, array{string, bool, bool, bool, bool}>
     */
    public static function patientPermissionOnly(): array
    {
        // [izin tunggal, viewAny, create, changeStatus, linkAccount (walk-in)]
        return [
            'hanya patients.view' => ['patients.view', true, false, false, false],
            'hanya patients.manage' => ['patients.manage', false, true, true, true],
        ];
    }

    #[DataProvider('patientPermissionOnly')]
    public function test_each_patient_ability_depends_on_its_own_permission(string $permission, bool $viewAny, bool $create, bool $changeStatus, bool $linkAccount): void
    {
        $staff = $this->staffWithOnly($permission);
        $walkIn = PatientProfile::factory()->create();

        $this->assertSame($viewAny, $this->allows($staff, 'viewAny', PatientProfile::class));
        $this->assertSame($create, $this->allows($staff, 'create', PatientProfile::class));
        $this->assertSame($changeStatus, $this->allows($staff, 'changeStatus', $walkIn));
        $this->assertSame($linkAccount, $this->allows($staff, 'linkAccount', $walkIn));
    }

    public function test_a_patient_cannot_use_the_staff_abilities_on_their_own_profile(): void
    {
        $owner = $this->createPatient();
        $profile = $owner->patientProfile;

        $this->assertTrue($this->allows($owner, 'view', $profile), 'Pemilik tetap boleh melihat profilnya.');
        $this->assertTrue($this->allows($owner, 'update', $profile), 'Pemilik tetap boleh memperbarui profilnya.');
        $this->assertFalse($this->allows($owner, 'viewAny', PatientProfile::class));
        $this->assertFalse($this->allows($owner, 'create', PatientProfile::class));
        $this->assertFalse($this->allows($owner, 'changeStatus', $profile));
        $this->assertFalse($this->allows($owner, 'linkAccount', $profile));
    }

    public function test_a_guest_has_no_patient_ability(): void
    {
        $walkIn = PatientProfile::factory()->create();

        $this->assertFalse(Gate::allows('viewAny', PatientProfile::class));
        $this->assertFalse(Gate::allows('create', PatientProfile::class));
        $this->assertFalse(Gate::allows('changeStatus', $walkIn));
        $this->assertFalse(Gate::allows('linkAccount', $walkIn));
    }

    // ------------------------------------------------------------------ AppointmentPolicy

    /**
     * @return array<string, array{string, bool, bool, bool}>
     */
    public static function appointmentMatrix(): array
    {
        // [peran, viewAny, createCounter, transition]
        return [
            'super admin' => ['super-admin', true, true, true],
            'admin konten' => ['content-admin', false, false, false],
            'petugas pendaftaran' => ['registration-officer', true, true, true],
            'pasien' => ['patient', false, false, false],
        ];
    }

    #[DataProvider('appointmentMatrix')]
    public function test_appointment_abilities_follow_the_role(string $role, bool $viewAny, bool $createCounter, bool $transition): void
    {
        $user = $this->userWithRole($role);
        $appointment = $this->appointment();

        $this->assertSame($viewAny, $this->allows($user, 'viewAny', Appointment::class), 'viewAny');
        $this->assertSame($createCounter, $this->allows($user, 'createCounter', Appointment::class), 'createCounter');
        $this->assertSame($transition, $this->allows($user, 'transition', $appointment), 'transition');
    }

    /**
     * @return array<string, array{string, bool, bool, bool}>
     */
    public static function appointmentPermissionOnly(): array
    {
        // [izin tunggal, viewAny, createCounter, transition]
        return [
            'hanya appointments.view' => ['appointments.view', true, false, false],
            'hanya appointments.manage' => ['appointments.manage', false, true, true],
        ];
    }

    #[DataProvider('appointmentPermissionOnly')]
    public function test_each_appointment_ability_depends_on_its_own_permission(string $permission, bool $viewAny, bool $createCounter, bool $transition): void
    {
        $staff = $this->staffWithOnly($permission);
        $appointment = $this->appointment();

        $this->assertSame($viewAny, $this->allows($staff, 'viewAny', Appointment::class));
        $this->assertSame($createCounter, $this->allows($staff, 'createCounter', Appointment::class));
        $this->assertSame($transition, $this->allows($staff, 'transition', $appointment));
    }

    public function test_the_owner_cannot_transition_their_own_appointment_but_can_still_view_and_cancel_it(): void
    {
        $owner = $this->createPatient();
        $appointment = $this->appointment($owner->patientProfile);

        $this->assertFalse($this->allows($owner, 'transition', $appointment), 'Transisi individual hanya untuk staf; pasien membatalkan lewat cancel.');
        $this->assertTrue($this->allows($owner, 'view', $appointment));
        $this->assertTrue($this->allows($owner, 'cancel', $appointment));
        $this->assertFalse($this->allows($owner, 'viewAny', Appointment::class));
        $this->assertFalse($this->allows($owner, 'createCounter', Appointment::class));
    }

    public function test_the_existing_view_and_cancel_abilities_still_serve_staff(): void
    {
        $officer = $this->createStaff('registration-officer');
        $contentAdmin = $this->createStaff('content-admin');
        $appointment = $this->appointment();

        $this->assertTrue($this->allows($officer, 'view', $appointment));
        $this->assertTrue($this->allows($officer, 'cancel', $appointment));
        $this->assertFalse($this->allows($contentAdmin, 'view', $appointment));
        $this->assertFalse($this->allows($contentAdmin, 'cancel', $appointment));
    }

    public function test_a_guest_has_no_appointment_ability(): void
    {
        $appointment = $this->appointment();

        $this->assertFalse(Gate::allows('viewAny', Appointment::class));
        $this->assertFalse(Gate::allows('createCounter', Appointment::class));
        $this->assertFalse(Gate::allows('transition', $appointment));
    }

    // ------------------------------------------------------------------ PracticeSessionPolicy::markNoShow

    /**
     * @return array<string, array{string, bool}>
     */
    public static function markNoShowMatrix(): array
    {
        return [
            'super admin' => ['super-admin', true],
            'admin konten' => ['content-admin', false],
            'petugas pendaftaran' => ['registration-officer', true],
            'pasien' => ['patient', false],
        ];
    }

    #[DataProvider('markNoShowMatrix')]
    public function test_mark_no_show_follows_the_role(string $role, bool $expected): void
    {
        $user = $this->userWithRole($role);

        $this->assertSame($expected, $this->allows($user, 'markNoShow', $this->createSession()));
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function markNoShowPermissions(): array
    {
        return [
            'appointments.manage' => ['appointments.manage', true],
            'appointments.view' => ['appointments.view', false],
            'sessions.review' => ['sessions.review', false],
            'schedules.manage' => ['schedules.manage', false],
        ];
    }

    #[DataProvider('markNoShowPermissions')]
    public function test_mark_no_show_depends_only_on_the_appointments_manage_permission(string $permission, bool $expected): void
    {
        $staff = $this->staffWithOnly($permission);

        $this->assertSame($expected, $this->allows($staff, 'markNoShow', $this->createSession()));
    }

    public function test_a_guest_cannot_mark_no_show(): void
    {
        $this->assertFalse(Gate::allows('markNoShow', $this->createSession()));
    }

    // ------------------------------------------------------------------ penemuan Policy

    public function test_the_policies_are_discovered_and_expose_the_new_abilities(): void
    {
        $this->assertInstanceOf(PatientProfilePolicy::class, Gate::getPolicyFor(PatientProfile::class));
        $this->assertInstanceOf(AppointmentPolicy::class, Gate::getPolicyFor(Appointment::class));
        $this->assertInstanceOf(PracticeSessionPolicy::class, Gate::getPolicyFor(PracticeSession::class));

        foreach (['viewAny', 'create', 'changeStatus', 'linkAccount', 'view', 'update'] as $method) {
            $this->assertTrue(method_exists(PatientProfilePolicy::class, $method), "PatientProfilePolicy::{$method}");
        }

        foreach (['viewAny', 'createCounter', 'transition', 'view', 'cancel', 'createOnline'] as $method) {
            $this->assertTrue(method_exists(AppointmentPolicy::class, $method), "AppointmentPolicy::{$method}");
        }

        $this->assertTrue(method_exists(PracticeSessionPolicy::class, 'markNoShow'));
    }
}
