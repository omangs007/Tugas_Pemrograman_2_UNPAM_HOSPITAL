<?php

namespace Tests\Feature\Models;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\DoctorSchedule;
use App\Models\PatientProfile;
use App\Models\PracticeSession;
use App\Models\ScheduleException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InteractsWithSchedules;
use Tests\TestCase;

/**
 * Pembantu tes `InteractsWithSchedules`: pencacah sesi harus selalu konsisten dengan CHECK dan invarian I-1/I-2.
 */
class InteractsWithSchedulesTest extends TestCase
{
    use InteractsWithSchedules;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-05 08:00:00');
    }

    public function test_create_helpers_return_persisted_models_and_accept_overrides(): void
    {
        $schedule = $this->createSchedule(['quota' => 33]);
        $session = $this->createSession(['quota' => 44, 'doctor_schedule_id' => null]);
        $exception = $this->createException(['reason' => 'Seminar']);

        $this->assertInstanceOf(DoctorSchedule::class, $schedule);
        $this->assertInstanceOf(PracticeSession::class, $session);
        $this->assertInstanceOf(ScheduleException::class, $exception);
        $this->assertSame(33, $schedule->fresh()->quota);
        $this->assertSame(44, $session->fresh()->quota);
        $this->assertNull($session->fresh()->doctor_schedule_id);
        $this->assertSame('Seminar', $exception->fresh()->reason);
    }

    public function test_book_appointment_increments_the_counters_and_numbers_the_queue_sequentially(): void
    {
        $session = $this->createSession(['quota' => 5]);

        $first = $this->bookAppointment($session);
        $second = $this->bookAppointment($session, AppointmentStatus::Pending);

        $this->assertSame([1, 2], [$first->queue_number, $second->queue_number]);
        $this->assertSame([2, 2], [$session->booked_count, $session->last_queue_number], 'Instance pemanggil ikut diperbarui.');
        $this->assertSame([2, 2], [$session->fresh()->booked_count, $session->fresh()->last_queue_number]);
    }

    public function test_book_appointment_copies_doctor_clinic_and_date_from_the_session(): void
    {
        $session = $this->createSession(['session_date' => '2026-10-12']);

        $appointment = $this->bookAppointment($session);

        $this->assertSame($session->id, $appointment->practice_session_id);
        $this->assertSame($session->doctor_id, $appointment->doctor_id);
        $this->assertSame($session->clinic_id, $appointment->clinic_id);
        $this->assertSame('2026-10-12', $appointment->session_date->toDateString());
    }

    public function test_a_cancelled_appointment_consumes_a_queue_number_but_not_quota(): void
    {
        $session = $this->createSession(['quota' => 2]);

        $cancelled = $this->bookAppointment($session, AppointmentStatus::Cancelled);
        $active = $this->bookAppointment($session);

        $this->assertSame(1, $cancelled->queue_number);
        $this->assertSame(2, $active->queue_number);
        $this->assertSame(1, $session->booked_count);
        $this->assertSame(2, $session->last_queue_number);
        $this->assertNull($cancelled->active_flag);
        $this->assertSame(1, $active->active_flag, 'Hasil dikembalikan dalam keadaan ter-refresh sehingga active_flag terbaca.');
    }

    public function test_counters_satisfy_i1_and_i2_after_a_mixed_batch(): void
    {
        $session = $this->createSession(['quota' => 10]);

        foreach (AppointmentStatus::cases() as $status) {
            $this->bookAppointment($session, $status);
        }

        $nonCancelled = Appointment::query()->where('practice_session_id', $session->id)->quotaConsuming()->count();
        $maxQueue = (int) Appointment::query()->where('practice_session_id', $session->id)->max('queue_number');

        $fresh = $session->fresh();
        $this->assertSame($nonCancelled, $fresh->booked_count, 'I-1');
        $this->assertSame($maxQueue, $fresh->last_queue_number, 'I-2');
        $this->assertGreaterThanOrEqual($fresh->booked_count, $fresh->last_queue_number, 'chk_sessions_queue');
    }

    public function test_booking_beyond_the_quota_is_rejected_by_the_check_constraint(): void
    {
        $session = $this->createSession(['quota' => 1]);
        $this->bookAppointment($session);

        try {
            $this->bookAppointment($session);
            $this->fail('Kuota terlampaui seharusnya ditolak.');
        } catch (QueryException $e) {
            $this->assertSame(4025, $e->errorInfo[1]);
            $this->assertStringContainsString('`chk_sessions_quota`', $e->getMessage());
        }

        $this->assertSame(1, $session->fresh()->booked_count);
        $this->assertSame(1, Appointment::query()->where('practice_session_id', $session->id)->count());
    }

    public function test_book_appointment_uses_a_new_patient_each_time_or_the_given_one(): void
    {
        $session = $this->createSession(['quota' => 5]);
        $patient = PatientProfile::factory()->create();

        $first = $this->bookAppointment($session);
        $second = $this->bookAppointment($session);
        $same = $this->bookAppointment($session, AppointmentStatus::Cancelled, ['patient_profile_id' => $patient->id]);

        $this->assertNotSame($first->patient_profile_id, $second->patient_profile_id);
        $this->assertSame($patient->id, $same->patient_profile_id);
        $this->assertSame(3, PatientProfile::query()->count());
    }

    public function test_book_appointment_applies_overrides(): void
    {
        $session = $this->createSession(['quota' => 5]);

        $appointment = $this->bookAppointment($session, AppointmentStatus::Pending, ['complaint' => 'Demam tiga hari']);

        $this->assertSame('Demam tiga hari', $appointment->complaint);
        $this->assertSame(AppointmentStatus::Pending, $appointment->status);
    }
}
