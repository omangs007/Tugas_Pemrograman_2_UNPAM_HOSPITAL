<?php

namespace Tests\Feature\Scheduling;

use App\Enums\AppointmentStatus;
use App\Enums\DoctorStatus;
use App\Enums\SessionStatus;
use App\Models\AuditLog;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\PracticeSession;
use App\Models\User;
use App\Services\Doctors\DoctorService;
use App\Services\Scheduling\ScheduleChangeReport;
use App\Support\Actor;
use Carbon\CarbonImmutable;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\Support\InteractsWithSchedules;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

/**
 * T8: dokter dinonaktifkan/diarsipkan memicu ScheduleChangeService::applyDoctorInactive() dalam transaksi yang sama
 * (F3-R16, JDW-16). "Sekarang" = Senin 2026-10-05 08:00.
 */
class DoctorStatusScheduleTest extends TestCase
{
    use InteractsWithSchedules;
    use InteractsWithStaff;
    use RefreshDatabase;

    private DoctorService $service;

    private Actor $actor;

    private Clinic $clinic;

    private Doctor $doctor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-10-05 08:00:00'));
        $this->seed(SettingsSeeder::class);

        $this->service = $this->app->make(DoctorService::class);
        $this->actor = Actor::staff(User::factory()->staff()->create());
        $this->clinic = Clinic::factory()->create();
        $this->doctor = Doctor::factory()->withSpecialties()->create(['clinic_id' => $this->clinic->id]);
    }

    // ------------------------------------------------------------------ pembantu

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeSession(string $date, array $overrides = []): PracticeSession
    {
        return $this->createSession(array_merge([
            'doctor_id' => $this->doctor->id,
            'clinic_id' => $this->clinic->id,
            'doctor_schedule_id' => null,
            'session_date' => $date,
            'start_time' => '08:00:00',
            'end_time' => '12:00:00',
            'quota' => 10,
            'status' => SessionStatus::Open,
        ], $overrides));
    }

    private function withAppointments(PracticeSession $session, int $count, AppointmentStatus $status = AppointmentStatus::Confirmed): PracticeSession
    {
        foreach (range(1, $count) as $ignored) {
            $this->bookAppointment($session->fresh(), $status);
        }

        return $session->fresh();
    }

    /**
     * Satu sesi tanpa appointment aktif dan satu dengan dua appointment aktif.
     *
     * @return array{PracticeSession, PracticeSession}
     */
    private function emptyAndBookedSessions(): array
    {
        return [
            $this->makeSession('2026-10-12'),
            $this->withAppointments($this->makeSession('2026-10-13'), 2),
        ];
    }

    private function makeTemplate(): DoctorSchedule
    {
        return $this->createSchedule([
            'doctor_id' => $this->doctor->id,
            'clinic_id' => $this->clinic->id,
            'day_of_week' => 1,
            'start_time' => '08:00:00',
            'end_time' => '12:00:00',
            'quota' => 10,
            'valid_from' => '2026-10-12',
            'valid_until' => null,
            'is_active' => true,
        ]);
    }

    // ------------------------------------------------------------------ nonaktif (JDW-16)

    public function test_deactivating_a_doctor_cancels_or_holds_future_sessions_and_audits_the_impact(): void
    {
        [$empty, $booked] = $this->emptyAndBookedSessions();

        $result = $this->service->changeStatus($this->doctor, DoctorStatus::Inactive, 'Cuti panjang', $this->actor);

        $this->assertSame(DoctorStatus::Inactive, $result->status);

        $cancelled = $empty->fresh();
        $this->assertSame(SessionStatus::Cancelled, $cancelled->status);
        $this->assertSame('Dokter tidak aktif', $cancelled->status_reason);

        $held = $booked->fresh();
        $this->assertSame(SessionStatus::NeedsReview, $held->status);
        $this->assertSame(['source' => 'doctor_inactive', 'doctor_id' => $this->doctor->id], $held->pending_change);
        $this->assertSame(2, $held->appointments()->active()->count());

        $report = $this->service->lastReport();
        $this->assertInstanceOf(ScheduleChangeReport::class, $report);
        $this->assertSame('1 sesi dibatalkan, 1 sesi perlu ditindaklanjuti petugas pendaftaran.', $report->message());

        $log = AuditLog::query()->where('action', 'doctor.status_changed')->sole();
        $this->assertEquals(['status' => 'active'], $log->old_values);
        $this->assertEquals([
            'status' => 'inactive',
            'impact' => ['cancelled' => 1, 'needs_review' => 1],
            'reason' => 'Cuti panjang',
        ], $log->new_values);
    }

    public function test_deactivating_a_doctor_without_future_sessions_keeps_the_phase_two_audit_shape(): void
    {
        $this->service->changeStatus($this->doctor, DoctorStatus::Inactive, null, $this->actor);

        $report = $this->service->lastReport();
        $this->assertInstanceOf(ScheduleChangeReport::class, $report);
        $this->assertSame('', $report->message());

        $log = AuditLog::query()->where('action', 'doctor.status_changed')->sole();
        $this->assertEquals(['status' => 'inactive'], $log->new_values);
    }

    public function test_reactivating_a_doctor_never_reopens_cancelled_sessions(): void
    {
        [$empty, $booked] = $this->emptyAndBookedSessions();
        $this->service->changeStatus($this->doctor, DoctorStatus::Inactive, null, $this->actor);

        $this->service->changeStatus($this->doctor, DoctorStatus::Active, null, $this->actor);

        $this->assertNull($this->service->lastReport());
        $this->assertSame(SessionStatus::Cancelled, $empty->fresh()->status);
        $this->assertSame(SessionStatus::NeedsReview, $booked->fresh()->status);
        $this->assertSame(DoctorStatus::Active, Doctor::query()->findOrFail($this->doctor->id)->status);
    }

    public function test_a_status_change_to_the_same_status_leaves_no_report(): void
    {
        $this->makeSession('2026-10-12');

        $this->service->changeStatus($this->doctor, DoctorStatus::Inactive, null, $this->actor);
        $this->assertNotNull($this->service->lastReport());

        $this->service->changeStatus($this->doctor, DoctorStatus::Inactive, null, $this->actor);

        $this->assertNull($this->service->lastReport());
    }

    public function test_deactivation_is_rolled_back_with_the_sessions_when_the_audit_row_cannot_be_written(): void
    {
        [$empty, $booked] = $this->emptyAndBookedSessions();

        AuditLog::creating(fn () => throw new RuntimeException('audit gagal'));

        $thrown = false;

        try {
            $this->service->changeStatus($this->doctor, DoctorStatus::Inactive, null, $this->actor);
        } catch (RuntimeException) {
            $thrown = true;
        }

        $this->assertTrue($thrown);
        $this->assertSame(DoctorStatus::Active, Doctor::query()->findOrFail($this->doctor->id)->status);
        $this->assertSame(SessionStatus::Open, $empty->fresh()->status);
        $this->assertSame(SessionStatus::Open, $booked->fresh()->status);
    }

    // ------------------------------------------------------------------ arsip (JDW-16)

    public function test_archiving_is_refused_while_future_active_appointments_exist(): void
    {
        [$empty, $booked] = $this->emptyAndBookedSessions();

        try {
            $this->service->archive($this->doctor, null, $this->actor);
            $this->fail('ValidationException tidak dilempar.');
        } catch (ValidationException $e) {
            $this->assertSame(
                ['Dokter masih memiliki 2 pendaftaran aktif pada sesi mendatang. Selesaikan sesi terdampak terlebih dahulu, lalu arsipkan dokter.'],
                $e->errors()['status'],
            );
        }

        $fresh = Doctor::query()->findOrFail($this->doctor->id);
        $this->assertSame(DoctorStatus::Active, $fresh->status);
        $this->assertNull($fresh->archived_at);
        $this->assertSame(SessionStatus::Open, $empty->fresh()->status);
        $this->assertSame(SessionStatus::Open, $booked->fresh()->status);
        $this->assertSame(0, AuditLog::query()->where('action', 'doctor.archived')->count());
        $this->assertNull($this->service->lastReport());
    }

    public function test_archiving_cancels_future_sessions_deactivates_templates_and_audits_the_impact(): void
    {
        $template = $this->makeTemplate();
        $empty = $this->makeSession('2026-10-12', ['doctor_schedule_id' => $template->id]);
        $history = $this->withAppointments($this->makeSession('2026-10-13'), 1, AppointmentStatus::Cancelled);
        $endedWithActive = $this->withAppointments($this->makeSession('2026-10-05', ['start_time' => '06:00:00', 'end_time' => '07:00:00']), 1);

        $this->assertNull($this->service->archiveBlockedReason($this->doctor));

        $archived = $this->service->archive($this->doctor, 'Pensiun', $this->actor);

        $this->assertSame(DoctorStatus::Archived, $archived->status);
        $this->assertSame(SessionStatus::Cancelled, $empty->fresh()->status);
        $this->assertSame('Dokter tidak aktif', $empty->fresh()->status_reason);
        $this->assertSame(SessionStatus::Cancelled, $history->fresh()->status);
        $this->assertSame(SessionStatus::Open, $endedWithActive->fresh()->status);
        $this->assertFalse($template->fresh()->is_active);
        $this->assertSame('2 sesi dibatalkan.', $this->service->lastReport()->message());

        $log = AuditLog::query()->where('action', 'doctor.archived')->sole();
        $this->assertSame('archived', $log->new_values['status']);
        $this->assertSame('Pensiun', $log->new_values['reason']);
        $this->assertEquals(['cancelled' => 2, 'needs_review' => 0, 'schedules_deactivated' => 1], $log->new_values['impact']);
    }

    public function test_archiving_without_any_schedule_data_keeps_the_phase_two_audit_shape(): void
    {
        $this->service->archive($this->doctor, null, $this->actor);

        $log = AuditLog::query()->where('action', 'doctor.archived')->sole();
        $this->assertArrayNotHasKey('impact', $log->new_values);
    }

    public function test_archiving_an_inactive_doctor_does_not_touch_sessions_again(): void
    {
        $empty = $this->makeSession('2026-10-12');
        $this->service->changeStatus($this->doctor, DoctorStatus::Inactive, null, $this->actor);
        $this->assertSame(SessionStatus::Cancelled, $empty->fresh()->status);

        $this->service->archive($this->doctor, null, $this->actor);

        $log = AuditLog::query()->where('action', 'doctor.archived')->sole();
        $this->assertArrayNotHasKey('impact', $log->new_values);
    }

    public function test_restoring_an_archived_doctor_does_not_reactivate_templates_or_reopen_sessions(): void
    {
        $template = $this->makeTemplate();
        $empty = $this->makeSession('2026-10-12', ['doctor_schedule_id' => $template->id]);
        $this->service->archive($this->doctor, null, $this->actor);

        $restored = $this->service->changeStatus($this->doctor, DoctorStatus::Inactive, null, $this->actor);

        $this->assertSame(DoctorStatus::Inactive, $restored->status);
        $this->assertNull($this->service->lastReport());
        $this->assertFalse($template->fresh()->is_active);
        $this->assertSame(SessionStatus::Cancelled, $empty->fresh()->status);
    }

    public function test_archive_is_rolled_back_with_templates_and_sessions_when_the_audit_row_cannot_be_written(): void
    {
        $template = $this->makeTemplate();
        $empty = $this->makeSession('2026-10-12', ['doctor_schedule_id' => $template->id]);

        AuditLog::creating(fn () => throw new RuntimeException('audit gagal'));

        $thrown = false;

        try {
            $this->service->archive($this->doctor, null, $this->actor);
        } catch (RuntimeException) {
            $thrown = true;
        }

        $this->assertTrue($thrown);
        $this->assertSame(DoctorStatus::Active, Doctor::query()->findOrFail($this->doctor->id)->status);
        $this->assertTrue($template->fresh()->is_active);
        $this->assertSame(SessionStatus::Open, $empty->fresh()->status);
    }

    // ------------------------------------------------------------------ flash HTTP

    public function test_status_flash_appends_the_schedule_report(): void
    {
        $this->emptyAndBookedSessions();
        $this->actingAs($this->createStaff('content-admin'));

        $this->from(route('admin.doctors.show', $this->doctor))
            ->patch(route('admin.doctors.status', $this->doctor), ['status' => 'inactive', 'reason' => 'Cuti panjang'])
            ->assertRedirect(route('admin.doctors.show', $this->doctor))
            ->assertSessionHas('success', 'Dokter dinonaktifkan. 1 sesi dibatalkan, 1 sesi perlu ditindaklanjuti petugas pendaftaran.');

        $this->assertSame(DoctorStatus::Inactive, $this->doctor->fresh()->status);
    }

    public function test_status_flash_stays_unchanged_without_schedule_impact(): void
    {
        $this->actingAs($this->createStaff('content-admin'));

        $this->from(route('admin.doctors.show', $this->doctor))
            ->patch(route('admin.doctors.status', $this->doctor), ['status' => 'inactive'])
            ->assertSessionHas('success', 'Dokter dinonaktifkan.');

        $this->patch(route('admin.doctors.status', $this->doctor), ['status' => 'active'])
            ->assertSessionHas('success', 'Dokter diaktifkan.');
    }

    public function test_archive_flash_appends_the_schedule_report_and_refusal_keeps_its_message(): void
    {
        $this->actingAs($this->createStaff('super-admin'));
        [$empty, $booked] = $this->emptyAndBookedSessions();
        $message = 'Dokter masih memiliki 2 pendaftaran aktif pada sesi mendatang. Selesaikan sesi terdampak terlebih dahulu, lalu arsipkan dokter.';

        $this->from(route('admin.doctors.show', $this->doctor))
            ->patch(route('admin.doctors.archive', $this->doctor), ['reason' => 'Pindah tugas'])
            ->assertRedirect(route('admin.doctors.show', $this->doctor))
            ->assertSessionHasErrors(['status' => $message])
            ->assertSessionHas('error', $message);

        $this->assertSame(DoctorStatus::Active, $this->doctor->fresh()->status);
        $this->assertSame(SessionStatus::Open, $booked->fresh()->status);

        $this->completeActiveAppointments($booked);

        $this->from(route('admin.doctors.show', $this->doctor))
            ->patch(route('admin.doctors.archive', $this->doctor), ['reason' => 'Pindah tugas'])
            ->assertSessionHas('success', 'Dokter diarsipkan. 2 sesi dibatalkan.');

        $this->assertSame(DoctorStatus::Archived, $this->doctor->fresh()->status);
        $this->assertSame(SessionStatus::Cancelled, $empty->fresh()->status);
    }

    /**
     * Menyelesaikan appointment aktif sebuah sesi (pasien sudah dilayani) agar dokter boleh diarsipkan.
     */
    private function completeActiveAppointments(PracticeSession $session): void
    {
        $session->appointments()->active()->get()->each(
            fn ($appointment) => $appointment->forceFill(['status' => AppointmentStatus::Completed])->save(),
        );
    }
}
