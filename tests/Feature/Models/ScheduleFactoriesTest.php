<?php

namespace Tests\Feature\Models;

use App\Enums\ExceptionType;
use App\Enums\SessionStatus;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\PracticeSession;
use App\Models\ScheduleException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Factory fase 3 (DoctorSchedule, ScheduleException, PracticeSession). Factory appointment, pasien, dan
 * riwayat diuji di BookingModelsTest.
 */
class ScheduleFactoriesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-05 08:00:00');
    }

    public function test_schedule_factory_creates_an_active_template_in_the_doctors_clinic(): void
    {
        $schedule = DoctorSchedule::factory()->create();

        $this->assertTrue($schedule->is_active);
        $this->assertSame(1, $schedule->day_of_week);
        $this->assertSame(20, $schedule->quota);
        $this->assertSame('2026-10-05', $schedule->valid_from->toDateString());
        $this->assertNull($schedule->valid_until);
        $this->assertSame(Doctor::query()->findOrFail($schedule->doctor_id)->clinic_id, $schedule->clinic_id);
    }

    public function test_schedule_factory_keeps_an_explicit_clinic_and_has_an_inactive_state(): void
    {
        $doctor = Doctor::factory()->create();
        $other = Doctor::factory()->create();

        $schedule = DoctorSchedule::factory()->inactive()->create(['doctor_id' => $doctor->id, 'clinic_id' => $other->clinic_id]);

        $this->assertFalse($schedule->is_active);
        $this->assertSame($doctor->id, $schedule->doctor_id);
        $this->assertSame($other->clinic_id, $schedule->clinic_id);
    }

    public function test_exception_factory_default_is_a_doctor_leave_that_satisfies_the_scope_check(): void
    {
        $exception = ScheduleException::factory()->create();

        $this->assertSame(ExceptionType::DoctorLeave, $exception->type);
        $this->assertNotNull($exception->doctor_id);
        $this->assertSame('2026-10-12', $exception->start_date->toDateString());
        $this->assertSame('2026-10-14', $exception->end_date->toDateString());
    }

    public function test_exception_factory_states_produce_valid_type_and_doctor_combinations(): void
    {
        $leave = ScheduleException::factory()->leave()->create();
        $hospital = ScheduleException::factory()->hospitalHoliday()->create();
        $national = ScheduleException::factory()->nationalHoliday()->create();
        $global = ScheduleException::factory()->global()->create();

        $this->assertSame(ExceptionType::DoctorLeave, $leave->type);
        $this->assertNotNull($leave->doctor_id);
        $this->assertSame(ExceptionType::HospitalHoliday, $hospital->type);
        $this->assertNull($hospital->doctor_id);
        $this->assertSame(ExceptionType::NationalHoliday, $national->type);
        $this->assertNull($national->doctor_id);
        $this->assertSame(ExceptionType::Other, $global->type);
        $this->assertNull($global->doctor_id);
        $this->assertTrue($global->is_global);
    }

    public function test_exception_leave_state_keeps_a_given_doctor(): void
    {
        $doctor = Doctor::factory()->create();

        $exception = ScheduleException::factory()->leave()->create(['doctor_id' => $doctor->id]);

        $this->assertSame($doctor->id, $exception->doctor_id);
    }

    public function test_session_factory_creates_a_coherent_template_session(): void
    {
        $session = PracticeSession::factory()->create();
        $schedule = DoctorSchedule::query()->findOrFail($session->doctor_schedule_id);

        $this->assertSame('2026-10-06', $session->session_date->toDateString());
        $this->assertSame(SessionStatus::Open, $session->status);
        $this->assertSame($session->doctor_id, $schedule->doctor_id);
        $this->assertSame($session->clinic_id, $schedule->clinic_id);
        $this->assertSame($session->session_date->isoWeekday(), $schedule->day_of_week);
        $this->assertSame([$session->start_time, $session->end_time, $session->quota], [$schedule->start_time, $schedule->end_time, $schedule->quota]);
        $this->assertSame([0, 0], [$session->booked_count, $session->last_queue_number]);
    }

    public function test_session_factory_manual_state_has_no_template(): void
    {
        $session = PracticeSession::factory()->manual()->create();

        $this->assertNull($session->doctor_schedule_id);
        $this->assertTrue($session->isManual());
        $this->assertSame(0, DoctorSchedule::query()->count());
    }

    public function test_session_factory_status_states(): void
    {
        $closed = PracticeSession::factory()->manual()->closed()->create();
        $cancelled = PracticeSession::factory()->manual()->cancelled()->create();
        $review = PracticeSession::factory()->manual()->needsReview(['source' => 'manual_cancel', 'reason' => 'Dokter sakit'])->create();

        $this->assertSame(SessionStatus::Closed, $closed->status);
        $this->assertSame(SessionStatus::Cancelled, $cancelled->status);
        $this->assertSame(SessionStatus::NeedsReview, $review->status);
        $this->assertSame(['source' => 'manual_cancel', 'reason' => 'Dokter sakit'], $review->fresh()->pending_change);
    }

    public function test_session_factory_keeps_the_counters_within_the_check_constraints(): void
    {
        $capped = PracticeSession::factory()->manual()->create(['quota' => 5, 'booked_count' => 9]);
        $queue = PracticeSession::factory()->manual()->create(['quota' => 10, 'booked_count' => 4, 'last_queue_number' => 1]);

        $this->assertSame([5, 5], [$capped->fresh()->booked_count, $capped->fresh()->last_queue_number]);
        $this->assertSame([4, 4], [$queue->fresh()->booked_count, $queue->fresh()->last_queue_number]);
    }

    public function test_session_factory_batch_has_distinct_templates_and_no_unique_collisions(): void
    {
        $sessions = PracticeSession::factory()->count(3)->create();

        $this->assertCount(3, $sessions->pluck('doctor_schedule_id')->unique());
        $this->assertSame(3, PracticeSession::query()->count());
    }
}
