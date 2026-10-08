<?php

namespace Tests\Feature\Patient;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\PatientProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\Support\InteractsWithMasterData;
use Tests\Support\InteractsWithPatients;
use Tests\Support\InteractsWithSchedules;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

/**
 * F6-R16: `createOnline` (akun pasien aktif dengan profil aktif), `view` dan `cancel` (pemilik; pasien lain 404 lewat
 * `denyAsNotFound`; staf menurut izin `appointments.view`/`appointments.manage`). Status dan batas waktu tidak di Policy.
 */
class AppointmentPolicyTest extends TestCase
{
    use InteractsWithMasterData;
    use InteractsWithPatients;
    use InteractsWithSchedules;
    use InteractsWithStaff;
    use RefreshDatabase;

    private function appointmentOf(?PatientProfile $profile = null): Appointment
    {
        $session = $this->createSession();

        return $this->bookAppointment($session, AppointmentStatus::Pending, array_filter(['patient_profile_id' => $profile?->id]));
    }

    public function test_create_online_is_allowed_for_an_active_patient_with_an_active_profile(): void
    {
        $this->assertTrue(Gate::forUser($this->createPatient())->allows('createOnline', Appointment::class));
    }

    public function test_create_online_is_denied_for_an_inactive_profile_an_inactive_account_and_a_missing_profile(): void
    {
        $inactiveProfile = $this->createPatient([], ['is_active' => false]);
        $inactiveAccount = $this->createPatient(['is_active' => false]);
        $withoutProfile = User::factory()->patient()->create();

        $this->assertFalse(Gate::forUser($inactiveProfile)->allows('createOnline', Appointment::class));
        $this->assertFalse(Gate::forUser($inactiveAccount)->allows('createOnline', Appointment::class));
        $this->assertFalse(Gate::forUser($withoutProfile)->allows('createOnline', Appointment::class));
    }

    public function test_create_online_is_denied_for_staff_except_the_super_admin_gate_bypass(): void
    {
        $this->assertFalse(Gate::forUser($this->createStaff('registration-officer'))->allows('createOnline', Appointment::class));
        $this->assertFalse(Gate::forUser($this->createStaff('content-admin'))->allows('createOnline', Appointment::class));
        $this->assertTrue(Gate::forUser($this->createStaff('super-admin'))->allows('createOnline', Appointment::class), 'Gate::before meloloskan Super Admin.');
    }

    public function test_the_owner_may_view_and_cancel_the_appointment(): void
    {
        $owner = $this->createPatient();
        $appointment = $this->appointmentOf($owner->patientProfile);

        $this->assertTrue(Gate::forUser($owner)->allows('view', $appointment));
        $this->assertTrue(Gate::forUser($owner)->allows('cancel', $appointment));
    }

    public function test_another_patient_gets_a_404_style_denial_for_view_and_cancel(): void
    {
        $owner = $this->createPatient(['email' => 'pemilik@contoh.test']);
        $other = $this->createPatient(['email' => 'lain@contoh.test']);
        $appointment = $this->appointmentOf($owner->patientProfile);

        foreach (['view', 'cancel'] as $ability) {
            $response = Gate::forUser($other)->inspect($ability, $appointment);

            $this->assertTrue($response->denied(), $ability);
            $this->assertSame(404, $response->status(), $ability);
        }
    }

    public function test_a_walk_in_appointment_without_an_account_belongs_to_nobody_online(): void
    {
        $appointment = $this->appointmentOf();
        $patient = $this->createPatient();

        $this->assertSame(404, Gate::forUser($patient)->inspect('view', $appointment)->status());
    }

    public function test_staff_follow_the_view_and_manage_permissions(): void
    {
        $appointment = $this->appointmentOf();
        $officer = $this->createStaff('registration-officer');
        $contentAdmin = $this->createStaff('content-admin');

        $this->assertTrue(Gate::forUser($officer)->allows('view', $appointment));
        $this->assertTrue(Gate::forUser($officer)->allows('cancel', $appointment));
        $this->assertFalse(Gate::forUser($contentAdmin)->allows('view', $appointment));
        $this->assertFalse(Gate::forUser($contentAdmin)->allows('cancel', $appointment));
    }

    public function test_the_super_admin_passes_through_the_gate_before_hook(): void
    {
        $appointment = $this->appointmentOf();
        $super = $this->createStaff('super-admin');

        $this->assertTrue(Gate::forUser($super)->allows('view', $appointment));
        $this->assertTrue(Gate::forUser($super)->allows('cancel', $appointment));
    }

    public function test_status_and_cutoff_are_not_policy_concerns(): void
    {
        $owner = $this->createPatient();
        $session = $this->createSession();
        $completed = $this->bookAppointment($session, AppointmentStatus::Completed, ['patient_profile_id' => $owner->patientProfile->id]);

        $this->assertTrue(Gate::forUser($owner)->allows('cancel', $completed), 'Status final ditolak oleh AppointmentStatusService (pesan spesifik), bukan Policy.');
    }
}
