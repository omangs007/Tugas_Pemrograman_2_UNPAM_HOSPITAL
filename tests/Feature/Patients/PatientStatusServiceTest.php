<?php

namespace Tests\Feature\Patients;

use App\Enums\ActorType;
use App\Enums\AppointmentStatus;
use App\Models\AuditLog;
use App\Models\PatientProfile;
use App\Models\User;
use App\Services\Patients\PatientRegistrationService;
use App\Support\Actor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\Support\InteractsWithPatients;
use Tests\Support\InteractsWithSchedules;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

/**
 * F7A-R10 / WLK-07 sisi service / BR-105: nonaktif ditolak bila ada appointment aktif (pesan berjumlah), profil berakun
 * menyinkronkan `users.is_active` dan menghapus baris `sessions`, aktifkan kembali, status sama tanpa tulis dan tanpa audit.
 */
class PatientStatusServiceTest extends TestCase
{
    use InteractsWithPatients;
    use InteractsWithSchedules;
    use InteractsWithStaff;
    use RefreshDatabase;

    private User $staff;

    private Actor $actor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-09 10:15:00');
        $this->staff = $this->createStaff('registration-officer');
        $this->actor = Actor::staff($this->staff);
    }

    private function service(): PatientRegistrationService
    {
        return $this->app->make(PatientRegistrationService::class);
    }

    private function walkIn(): PatientProfile
    {
        return PatientProfile::factory()->create();
    }

    /** Menyisipkan baris sesi login (driver database) untuk akun. */
    private function loginSession(string $id, ?int $userId): void
    {
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $userId,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'uji',
            'payload' => 'x',
            'last_activity' => time(),
        ]);
    }

    private function book(PatientProfile $profile, AppointmentStatus $status): void
    {
        $this->bookAppointment($this->createSession(), $status, ['patient_profile_id' => $profile->id]);
    }

    private function statusAudits(): int
    {
        return AuditLog::query()->where('action', 'patient.status_changed')->count();
    }

    // --- menonaktifkan / mengaktifkan ---

    public function test_it_deactivates_a_walk_in_profile_and_audits_it(): void
    {
        $profile = $this->walkIn();

        $result = $this->service()->changeStatus($profile, false, $this->actor, 'Pasien pindah domisili');

        $this->assertSame($profile, $result, 'Instance pemanggil dikembalikan dan disinkronkan.');
        $this->assertFalse($profile->is_active);
        $this->assertFalse(PatientProfile::query()->findOrFail($profile->id)->is_active);

        $log = AuditLog::query()->where('action', 'patient.status_changed')->sole();
        $this->assertSame(ActorType::Staff, $log->actor_type);
        $this->assertSame($this->staff->id, (int) $log->user_id);
        $this->assertSame($profile->getMorphClass(), $log->auditable_type);
        $this->assertSame($profile->id, (int) $log->auditable_id);
        $this->assertSame(['is_active' => true], $log->old_values);
        $this->assertSame(['is_active' => false, 'reason' => 'Pasien pindah domisili'], $log->new_values);
        $this->assertSame("Profil pasien {$profile->patient_number} dinonaktifkan.", $log->description);
    }

    public function test_the_reason_is_optional_and_a_blank_reason_is_not_audited(): void
    {
        $first = $this->walkIn();
        $second = $this->walkIn();

        $this->service()->changeStatus($first, false, $this->actor);
        $this->service()->changeStatus($second, false, $this->actor, "   \n ");

        foreach (AuditLog::query()->where('action', 'patient.status_changed')->get() as $log) {
            $this->assertSame(['is_active' => false], $log->new_values);
        }

        $this->assertSame(2, $this->statusAudits());
    }

    public function test_it_reactivates_a_profile_and_audits_it(): void
    {
        $profile = PatientProfile::factory()->inactive()->create();

        $this->service()->changeStatus($profile, true, $this->actor);

        $this->assertTrue(PatientProfile::query()->findOrFail($profile->id)->is_active);

        $log = AuditLog::query()->where('action', 'patient.status_changed')->sole();
        $this->assertSame(['is_active' => false], $log->old_values);
        $this->assertSame(['is_active' => true], $log->new_values);
        $this->assertSame("Profil pasien {$profile->patient_number} diaktifkan kembali.", $log->description);
    }

    public function test_the_same_status_writes_nothing_and_no_audit(): void
    {
        $active = $this->walkIn();
        $inactive = PatientProfile::factory()->inactive()->create();
        $before = [
            DB::table('patient_profiles')->where('id', $active->id)->value('updated_at'),
            DB::table('patient_profiles')->where('id', $inactive->id)->value('updated_at'),
        ];

        $this->travelTo('2026-10-09 12:00:00');

        $this->service()->changeStatus($active, true, $this->actor, 'Tidak ada perubahan');
        $this->service()->changeStatus($inactive, false, $this->actor, 'Tidak ada perubahan');

        $this->assertSame(0, $this->statusAudits());
        $this->assertSame($before[0], DB::table('patient_profiles')->where('id', $active->id)->value('updated_at'));
        $this->assertSame($before[1], DB::table('patient_profiles')->where('id', $inactive->id)->value('updated_at'));
    }

    public function test_a_stale_instance_is_resynchronised_and_a_no_op_does_not_audit(): void
    {
        $profile = $this->walkIn();
        $stale = PatientProfile::query()->findOrFail($profile->id);
        PatientProfile::query()->whereKey($profile->id)->update(['is_active' => false]);

        $this->service()->changeStatus($stale, false, $this->actor);

        $this->assertFalse($stale->is_active, 'Status dibaca ulang di bawah kunci.');
        $this->assertSame(0, $this->statusAudits());
    }

    // --- penolakan karena pendaftaran aktif ---

    public function test_it_refuses_to_deactivate_while_active_appointments_exist(): void
    {
        $patient = $this->createPatient();
        $profile = $patient->patientProfile;
        $this->book($profile, AppointmentStatus::Pending);
        $this->book($profile, AppointmentStatus::CheckedIn);
        $this->loginSession('sess-a', $patient->id);

        try {
            $this->service()->changeStatus($profile, false, $this->actor, 'Pasien pindah domisili');
            $this->fail('Penonaktifan harus ditolak.');
        } catch (ValidationException $e) {
            $this->assertSame(['is_active' => ['Pasien masih memiliki 2 pendaftaran aktif. Batalkan atau selesaikan terlebih dahulu.']], $e->errors());
        }

        $this->assertTrue(PatientProfile::query()->findOrFail($profile->id)->is_active);
        $this->assertTrue(User::query()->findOrFail($patient->id)->is_active);
        $this->assertSame(1, DB::table('sessions')->where('user_id', $patient->id)->count(), 'Sesi login tidak disentuh pada penolakan.');
        $this->assertSame(0, $this->statusAudits());
    }

    public function test_the_refusal_counts_every_active_status_including_a_confirmed_one(): void
    {
        $profile = $this->walkIn();
        $this->book($profile, AppointmentStatus::Confirmed);

        try {
            $this->service()->changeStatus($profile, false, $this->actor);
            $this->fail('Penonaktifan harus ditolak.');
        } catch (ValidationException $e) {
            $this->assertSame('Pasien masih memiliki 1 pendaftaran aktif. Batalkan atau selesaikan terlebih dahulu.', $e->errors()['is_active'][0]);
        }
    }

    public function test_an_old_pending_appointment_on_a_past_session_still_blocks(): void
    {
        $profile = $this->walkIn();
        $this->bookAppointment($this->createSession(['session_date' => '2026-10-01']), AppointmentStatus::Pending, ['patient_profile_id' => $profile->id]);

        $this->expectException(ValidationException::class);

        $this->service()->changeStatus($profile, false, $this->actor);
    }

    public function test_final_appointments_do_not_block_the_deactivation(): void
    {
        $profile = $this->walkIn();
        $this->book($profile, AppointmentStatus::Completed);
        $this->book($profile, AppointmentStatus::Cancelled);
        $this->book($profile, AppointmentStatus::NoShow);

        $this->service()->changeStatus($profile, false, $this->actor);

        $this->assertFalse(PatientProfile::query()->findOrFail($profile->id)->is_active);
    }

    public function test_only_the_appointments_of_this_profile_are_counted(): void
    {
        $profile = $this->walkIn();
        $this->book($this->walkIn(), AppointmentStatus::Pending);

        $this->service()->changeStatus($profile, false, $this->actor);

        $this->assertFalse(PatientProfile::query()->findOrFail($profile->id)->is_active);
    }

    // --- profil berakun ---

    public function test_deactivating_a_profile_with_an_account_deactivates_the_account_and_deletes_its_sessions(): void
    {
        $patient = $this->createPatient();
        $other = $this->createPatient();
        $this->loginSession('sess-a', $patient->id);
        $this->loginSession('sess-b', $patient->id);
        $this->loginSession('sess-c', $other->id);
        $this->loginSession('sess-d', null);

        $this->service()->changeStatus($patient->patientProfile, false, $this->actor, 'Permintaan keluarga pasien');

        $this->assertFalse(PatientProfile::query()->findOrFail($patient->patientProfile->id)->is_active);
        $this->assertFalse(User::query()->findOrFail($patient->id)->is_active);
        $this->assertSame(0, DB::table('sessions')->where('user_id', $patient->id)->count());
        $this->assertSame(1, DB::table('sessions')->where('user_id', $other->id)->count());
        $this->assertSame(1, DB::table('sessions')->whereNull('user_id')->count());
        $this->assertTrue(User::query()->findOrFail($other->id)->is_active);
        $this->assertSame(1, $this->statusAudits(), 'Satu baris audit, tanpa baris audit akun.');
    }

    public function test_reactivating_a_profile_with_an_account_reactivates_the_account_without_restoring_sessions(): void
    {
        $patient = $this->createPatient();
        $this->loginSession('sess-a', $patient->id);

        $this->service()->changeStatus($patient->patientProfile, false, $this->actor);
        $this->service()->changeStatus(PatientProfile::query()->findOrFail($patient->patientProfile->id), true, $this->actor);

        $this->assertTrue(PatientProfile::query()->findOrFail($patient->patientProfile->id)->is_active);
        $this->assertTrue(User::query()->findOrFail($patient->id)->is_active);
        $this->assertSame(0, DB::table('sessions')->where('user_id', $patient->id)->count());
        $this->assertSame(2, $this->statusAudits());
    }

    public function test_deactivating_a_walk_in_profile_leaves_every_session_and_account_alone(): void
    {
        $other = $this->createPatient();
        $this->loginSession('sess-c', $other->id);

        $this->service()->changeStatus($this->walkIn(), false, $this->actor);

        $this->assertSame(1, DB::table('sessions')->count());
        $this->assertTrue(User::query()->findOrFail($other->id)->is_active);
    }

    public function test_an_audit_failure_rolls_back_the_profile_the_account_and_the_sessions(): void
    {
        $patient = $this->createPatient();
        $this->loginSession('sess-a', $patient->id);
        AuditLog::creating(function (): void {
            throw new RuntimeException('Audit gagal ditulis.');
        });

        try {
            $this->service()->changeStatus($patient->patientProfile, false, $this->actor);
            $this->fail('Kegagalan audit harus merambat keluar dari service.');
        } catch (RuntimeException $e) {
            $this->assertSame('Audit gagal ditulis.', $e->getMessage());
        }

        $this->assertTrue(PatientProfile::query()->findOrFail($patient->patientProfile->id)->is_active);
        $this->assertTrue(User::query()->findOrFail($patient->id)->is_active);
        $this->assertSame(1, DB::table('sessions')->where('user_id', $patient->id)->count());
    }

    public function test_the_status_messages_are_verbatim(): void
    {
        $this->assertSame('Pasien masih memiliki 3 pendaftaran aktif. Batalkan atau selesaikan terlebih dahulu.', __('patients.status.active_appointments', ['count' => 3]));
        $this->assertSame('Profil pasien P000012 dinonaktifkan.', __('patients.status.deactivated', ['number' => 'P000012']));
        $this->assertSame('Profil pasien P000012 diaktifkan kembali.', __('patients.status.activated', ['number' => 'P000012']));
    }
}
