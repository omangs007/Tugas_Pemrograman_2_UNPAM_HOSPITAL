<?php

namespace Tests\Feature\Scheduling;

use App\Enums\AppointmentStatus;
use App\Enums\Availability;
use App\Enums\DoctorStatus;
use App\Enums\ExceptionType;
use App\Enums\SessionStatus;
use App\Models\AuditLog;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\PracticeSession;
use App\Models\ScheduleException;
use App\Models\User;
use App\Services\Scheduling\DoctorAvailabilityQuery;
use App\Services\Scheduling\ScheduleChangeService;
use App\Support\Actor;
use Carbon\CarbonImmutable;
use Database\Seeders\SettingsSeeder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\Support\InteractsWithSchedules;
use Tests\TestCase;

/**
 * T7: pengecualian cuti/libur. "Sekarang" = Senin 2026-10-05 08:00; sesi uji dibuat langsung per tanggal
 * (doctor_schedule_id NULL) kecuali pengujian yang membutuhkan templat.
 */
class ScheduleChangeExceptionTest extends TestCase
{
    use InteractsWithSchedules;
    use RefreshDatabase;

    private ScheduleChangeService $service;

    private DoctorAvailabilityQuery $query;

    private Actor $actor;

    private Clinic $clinic;

    private Doctor $doctor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-10-05 08:00:00'));
        $this->seed(SettingsSeeder::class);

        $this->service = $this->app->make(ScheduleChangeService::class);
        $this->query = $this->app->make(DoctorAvailabilityQuery::class);
        $this->actor = Actor::staff(User::factory()->staff()->create());
        $this->clinic = Clinic::factory()->create();
        $this->doctor = Doctor::factory()->withSpecialties()->create(['clinic_id' => $this->clinic->id]);
    }

    // ------------------------------------------------------------------ pembantu

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeSession(string $date, array $overrides = [], ?Doctor $doctor = null): PracticeSession
    {
        $doctor ??= $this->doctor;

        return $this->createSession(array_merge([
            'doctor_id' => $doctor->id,
            'clinic_id' => $this->clinic->id,
            'doctor_schedule_id' => null,
            'session_date' => $date,
            'start_time' => '08:00:00',
            'end_time' => '12:00:00',
            'quota' => 20,
            'status' => SessionStatus::Open,
        ], $overrides));
    }

    private function withActiveAppointments(PracticeSession $session, int $count = 2): PracticeSession
    {
        foreach (range(1, $count) as $ignored) {
            $this->bookAppointment($session->fresh(), AppointmentStatus::Confirmed);
        }

        return $session->fresh();
    }

    private function leave(string $start, string $end, ?Doctor $doctor = null, string $reason = 'Cuti tahunan'): ScheduleException
    {
        return $this->createException([
            'type' => ExceptionType::DoctorLeave,
            'doctor_id' => ($doctor ?? $this->doctor)->id,
            'start_date' => $start,
            'end_date' => $end,
            'reason' => $reason,
        ]);
    }

    private function holiday(string $start, string $end, string $reason = 'Libur rumah sakit'): ScheduleException
    {
        return $this->createException([
            'type' => ExceptionType::HospitalHoliday,
            'doctor_id' => null,
            'start_date' => $start,
            'end_date' => $end,
            'reason' => $reason,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function previousOf(ScheduleException $exception): array
    {
        return [
            'start_date' => $exception->start_date->toDateString(),
            'end_date' => $exception->end_date->toDateString(),
            'doctor_id' => $exception->doctor_id,
        ];
    }

    private function assertRejected(callable $callback, string $key, string $message): void
    {
        try {
            $callback();
        } catch (ValidationException $e) {
            $this->assertSame([$message], $e->errors()[$key] ?? null);

            return;
        }

        $this->fail('ValidationException tidak dilempar.');
    }

    private function auditRows(string $action): Collection
    {
        return AuditLog::query()->where('action', $action)->orderBy('id')->get();
    }

    // ------------------------------------------------------------------ tambah pengecualian

    public function test_doctor_leave_cancels_the_sessions_in_range_and_labels_them_leave_jdw03(): void
    {
        $other = Doctor::factory()->withSpecialties()->create(['clinic_id' => $this->clinic->id]);
        $inside = [$this->makeSession('2026-10-12'), $this->makeSession('2026-10-13'), $this->makeSession('2026-10-14')];
        $after = $this->makeSession('2026-10-15');
        $otherDoctor = $this->makeSession('2026-10-13', [], $other);
        $leave = $this->leave('2026-10-12', '2026-10-14');

        $report = $this->service->applyException($leave, null, $this->actor);

        foreach ($inside as $session) {
            $fresh = $session->fresh();

            $this->assertSame(SessionStatus::Cancelled, $fresh->status);
            $this->assertSame($leave->id, (int) $fresh->schedule_exception_id);
            $this->assertSame('Cuti tahunan', $fresh->status_reason);

            $item = $this->query->forSession($fresh);
            $this->assertSame(Availability::Leave, $item->availability);
            $this->assertFalse($item->isBookableOnline);
            $this->assertFalse($item->isBookableCounter);
        }

        $this->assertSame(SessionStatus::Open, $after->fresh()->status);
        $this->assertSame(SessionStatus::Open, $otherDoctor->fresh()->status);
        $this->assertSame(3, $report->cancelled);
        $this->assertSame('3 sesi dibatalkan.', $report->message());
    }

    public function test_hospital_holiday_cancels_every_doctor_on_that_day_jdw04(): void
    {
        $doctors = [$this->doctor, Doctor::factory()->withSpecialties()->create(['clinic_id' => $this->clinic->id]), Doctor::factory()->withSpecialties()->create(['clinic_id' => $this->clinic->id])];
        $sessions = array_map(fn (Doctor $doctor) => $this->makeSession('2026-10-12', [], $doctor), $doctors);
        $nextDay = $this->makeSession('2026-10-13');
        $holiday = $this->holiday('2026-10-12', '2026-10-12');

        $report = $this->service->applyException($holiday, null, $this->actor);

        foreach ($sessions as $session) {
            $fresh = $session->fresh();

            $this->assertSame(SessionStatus::Cancelled, $fresh->status);
            $this->assertSame($holiday->id, (int) $fresh->schedule_exception_id);
            $this->assertSame(Availability::Holiday, $this->query->forSession($fresh)->availability);
        }

        $this->assertSame(SessionStatus::Open, $nextDay->fresh()->status);
        $this->assertSame(3, $report->cancelled);
    }

    public function test_sessions_with_active_appointments_need_review_and_keep_their_bookings_jdw09(): void
    {
        $empty = $this->makeSession('2026-10-12');
        $booked = $this->withActiveAppointments($this->makeSession('2026-10-13'), 2);
        $history = $this->makeSession('2026-10-14');
        $this->bookAppointment($history->fresh(), AppointmentStatus::Cancelled);
        $leave = $this->leave('2026-10-12', '2026-10-14');

        $report = $this->service->applyException($leave, null, $this->actor);

        $this->assertSame(SessionStatus::Cancelled, $empty->fresh()->status);
        $this->assertSame(SessionStatus::Cancelled, $history->fresh()->status);

        $review = $booked->fresh();
        $this->assertSame(SessionStatus::NeedsReview, $review->status);
        $this->assertSame(['source' => 'exception', 'exception_id' => $leave->id], $review->pending_change);
        $this->assertSame($leave->id, (int) $review->schedule_exception_id);
        $this->assertSame(2, $review->appointments()->active()->count());
        $this->assertSame(2, $review->booked_count);

        $item = $this->query->forSession($review);
        $this->assertSame(Availability::UnderReview, $item->availability);
        $this->assertFalse($item->isBookableOnline);

        $this->assertSame(2, $report->cancelled);
        $this->assertSame(1, $report->needsReview);
        $this->assertSame('2 sesi dibatalkan, 1 sesi perlu ditindaklanjuti petugas pendaftaran.', $report->message());
    }

    public function test_needs_review_closed_and_manually_modified_sessions_are_processed_f3_r11_br27(): void
    {
        $schedule = $this->createSchedule([
            'doctor_id' => $this->doctor->id,
            'clinic_id' => $this->clinic->id,
            'day_of_week' => 1,
            'start_time' => '08:00:00',
            'end_time' => '12:00:00',
            'quota' => 20,
            'valid_from' => '2026-10-12',
            'valid_until' => '2026-10-26',
            'is_active' => true,
        ]);
        $pendingTime = ['source' => 'template_time', 'schedule_id' => $schedule->id, 'new_start_time' => '09:00', 'new_end_time' => '13:00'];

        $timeReview = $this->withActiveAppointments($this->makeSession('2026-10-12', [
            'doctor_schedule_id' => $schedule->id,
            'status' => SessionStatus::NeedsReview,
            'pending_change' => $pendingTime,
        ]));
        $inactiveReview = $this->withActiveAppointments($this->makeSession('2026-10-19', [
            'doctor_schedule_id' => $schedule->id,
            'status' => SessionStatus::NeedsReview,
            'pending_change' => ['source' => 'doctor_inactive', 'doctor_id' => $this->doctor->id],
        ]));
        $reviewWithoutBookings = $this->makeSession('2026-10-26', [
            'doctor_schedule_id' => $schedule->id,
            'status' => SessionStatus::NeedsReview,
            'pending_change' => $pendingTime,
        ]);
        $closed = $this->withActiveAppointments($this->makeSession('2026-10-20', ['status' => SessionStatus::Closed]), 1);
        $modified = $this->makeSession('2026-10-21', ['is_manually_modified' => true]);
        $leave = $this->leave('2026-10-12', '2026-10-26');

        $report = $this->service->applyException($leave, null, $this->actor);

        $expected = ['source' => 'exception', 'exception_id' => $leave->id];

        foreach ([$timeReview, $inactiveReview, $closed] as $session) {
            $fresh = $session->fresh();

            $this->assertSame(SessionStatus::NeedsReview, $fresh->status);
            $this->assertSame($expected, $fresh->pending_change);
            $this->assertSame($leave->id, (int) $fresh->schedule_exception_id);
        }

        $cleared = $reviewWithoutBookings->fresh();
        $this->assertSame(SessionStatus::Cancelled, $cleared->status);
        $this->assertNull($cleared->pending_change);
        $this->assertSame(SessionStatus::Cancelled, $modified->fresh()->status);

        $this->assertSame(2, $report->cancelled);
        $this->assertSame(3, $report->needsReview);

        $replaced = $this->auditRows('session.pending_replaced');
        $this->assertSame(2, $replaced->count());
        $this->assertEquals(['pending_change' => $pendingTime], $replaced->firstWhere('auditable_id', $timeReview->id)->old_values);
        $this->assertEquals(['pending_change' => $expected], $replaced->firstWhere('auditable_id', $timeReview->id)->new_values);
        $this->assertSame('doctor_inactive', $replaced->firstWhere('auditable_id', $inactiveReview->id)->old_values['pending_change']['source']);
    }

    public function test_sessions_that_already_ended_are_not_touched(): void
    {
        $ended = $this->makeSession('2026-10-05', ['start_time' => '06:00:00', 'end_time' => '07:00:00']);
        $running = $this->makeSession('2026-10-05', ['start_time' => '07:00:00', 'end_time' => '12:00:00']);

        $report = $this->service->applyException($this->leave('2026-10-05', '2026-10-05'), null, $this->actor);

        $this->assertSame(SessionStatus::Open, $ended->fresh()->status);
        $this->assertSame(SessionStatus::Cancelled, $running->fresh()->status);
        $this->assertSame(1, $report->cancelled);
    }

    public function test_sessions_that_do_not_exist_yet_are_generated_as_cancelled(): void
    {
        $schedule = $this->createSchedule([
            'doctor_id' => $this->doctor->id,
            'clinic_id' => $this->clinic->id,
            'day_of_week' => 1,
            'start_time' => '08:00:00',
            'end_time' => '12:00:00',
            'quota' => 20,
            'valid_from' => '2026-10-12',
            'valid_until' => null,
            'is_active' => true,
        ]);
        $leave = $this->leave('2026-10-19', '2026-10-19');

        $this->service->applyException($leave, null, $this->actor);

        $sessions = PracticeSession::query()->where('doctor_schedule_id', $schedule->id)->orderBy('session_date')->get()->keyBy(fn (PracticeSession $s): string => $s->session_date->toDateString());

        $this->assertSame(['2026-10-12', '2026-10-19', '2026-10-26', '2026-11-02'], $sessions->keys()->all());
        $this->assertSame(SessionStatus::Cancelled, $sessions['2026-10-19']->status);
        $this->assertSame($leave->id, (int) $sessions['2026-10-19']->schedule_exception_id);
        $this->assertSame('Cuti tahunan', $sessions['2026-10-19']->status_reason);
        $this->assertSame(SessionStatus::Open, $sessions['2026-10-12']->status);
        $this->assertSame(SessionStatus::Open, $sessions['2026-10-26']->status);
    }

    // ------------------------------------------------------------------ pagar service

    public function test_start_date_must_not_be_in_the_past_unless_an_existing_exception_keeps_it_f3_r21(): void
    {
        $session = $this->makeSession('2026-10-12');
        $past = $this->leave('2026-10-04', '2026-10-14');

        $this->assertRejected(
            fn () => $this->service->applyException($past, null, $this->actor),
            'start_date',
            'Tanggal mulai tidak boleh di masa lalu.',
        );
        $this->assertSame(SessionStatus::Open, $session->fresh()->status);

        $existing = $this->leave('2026-10-01', '2026-10-14');
        $previous = $this->previousOf($existing);
        $existing->update(['end_date' => '2026-10-20']);

        $report = $this->service->applyException($existing, $previous, $this->actor);

        $this->assertSame(SessionStatus::Cancelled, $session->fresh()->status);
        $this->assertSame(1, $report->cancelled);

        $moved = $this->previousOf($existing);
        $existing->update(['start_date' => '2026-10-02']);

        $this->assertRejected(
            fn () => $this->service->applyException($existing, $moved, $this->actor),
            'start_date',
            'Tanggal mulai tidak boleh di masa lalu.',
        );
    }

    public function test_type_and_doctor_combination_is_guarded_by_the_service_jdw18(): void
    {
        $leaveMessage = 'Pilih dokter untuk cuti dokter.';
        $holidayMessage = 'Libur rumah sakit atau libur nasional berlaku untuk semua dokter. Kosongkan pilihan dokter.';
        $make = fn (ExceptionType $type, ?int $doctorId): ScheduleException => new ScheduleException([
            'doctor_id' => $doctorId,
            'type' => $type,
            'start_date' => '2026-10-12',
            'end_date' => '2026-10-12',
            'reason' => 'Uji kombinasi',
        ]);

        $this->assertRejected(fn () => $this->service->applyException($make(ExceptionType::DoctorLeave, null), null, $this->actor), 'doctor_id', $leaveMessage);
        $this->assertRejected(fn () => $this->service->applyException($make(ExceptionType::HospitalHoliday, $this->doctor->id), null, $this->actor), 'doctor_id', $holidayMessage);
        $this->assertRejected(fn () => $this->service->applyException($make(ExceptionType::NationalHoliday, $this->doctor->id), null, $this->actor), 'doctor_id', $holidayMessage);

        $withDoctor = $this->createException(['type' => ExceptionType::Other, 'doctor_id' => $this->doctor->id, 'start_date' => '2026-10-12', 'end_date' => '2026-10-12', 'reason' => 'Lainnya']);
        $withoutDoctor = $this->createException(['type' => ExceptionType::Other, 'doctor_id' => null, 'start_date' => '2026-10-13', 'end_date' => '2026-10-13', 'reason' => 'Lainnya semua']);

        $this->service->applyException($withDoctor, null, $this->actor);
        $this->service->applyException($withoutDoctor, null, $this->actor);

        $this->assertSame(2, $this->auditRows('exception.created')->count());
    }

    public function test_end_date_before_start_date_is_rejected(): void
    {
        $broken = new ScheduleException([
            'doctor_id' => $this->doctor->id,
            'type' => ExceptionType::DoctorLeave,
            'start_date' => '2026-10-14',
            'end_date' => '2026-10-13',
            'reason' => 'Terbalik',
        ]);

        $this->assertRejected(
            fn () => $this->service->applyException($broken, null, $this->actor),
            'end_date',
            'Tanggal selesai tidak boleh sebelum tanggal mulai.',
        );
    }

    // ------------------------------------------------------------------ cabut dan persempit pengecualian

    public function test_removing_an_exception_reopens_its_cancelled_sessions_and_leaves_ended_ones_jdw10(): void
    {
        $leave = $this->leave('2026-10-05', '2026-10-13');
        $ended = $this->makeSession('2026-10-05', [
            'start_time' => '06:00:00',
            'end_time' => '07:00:00',
            'status' => SessionStatus::Cancelled,
            'schedule_exception_id' => $leave->id,
            'status_reason' => 'Cuti tahunan',
        ]);
        $future = [];

        foreach (['2026-10-12', '2026-10-13'] as $date) {
            $future[] = $this->makeSession($date, [
                'status' => SessionStatus::Cancelled,
                'schedule_exception_id' => $leave->id,
                'status_reason' => 'Cuti tahunan',
            ]);
        }

        $report = $this->service->removeException($leave, $this->actor);
        $leave->delete();

        foreach ($future as $session) {
            $fresh = $session->fresh();

            $this->assertSame(SessionStatus::Open, $fresh->status);
            $this->assertNull($fresh->status_reason);
            $this->assertNull($fresh->schedule_exception_id);
            $this->assertTrue($this->query->forSession($fresh)->isBookableOnline);
        }

        $this->assertSame(SessionStatus::Cancelled, $ended->fresh()->status);
        $this->assertSame(2, $report->reopened);
        $this->assertSame('2 sesi dibuka kembali.', $report->message());
    }

    public function test_removing_an_exception_returns_a_needs_review_exception_session_to_open_jdw17e(): void
    {
        $leave = $this->leave('2026-10-12', '2026-10-12');
        $session = $this->withActiveAppointments($this->makeSession('2026-10-12', [
            'status' => SessionStatus::NeedsReview,
            'schedule_exception_id' => $leave->id,
            'pending_change' => ['source' => 'exception', 'exception_id' => $leave->id],
        ]));

        $report = $this->service->removeException($leave, $this->actor);
        $leave->delete();

        $fresh = $session->fresh();
        $this->assertSame(SessionStatus::Open, $fresh->status);
        $this->assertNull($fresh->pending_change);
        $this->assertNull($fresh->schedule_exception_id);
        $this->assertSame(2, $fresh->appointments()->active()->count());
        $this->assertSame(1, $report->reopened);
        $this->assertSame(0, $report->needsReview);
    }

    public function test_narrowing_the_range_reopens_the_released_sessions_jdw10(): void
    {
        $sessions = [];

        foreach (['2026-10-12', '2026-10-13', '2026-10-14'] as $date) {
            $sessions[$date] = $this->makeSession($date);
        }

        $leave = $this->leave('2026-10-12', '2026-10-14');
        $this->service->applyException($leave, null, $this->actor);

        $previous = $this->previousOf($leave);
        $leave->update(['end_date' => '2026-10-12']);
        $report = $this->service->applyException($leave, $previous, $this->actor);

        $this->assertSame(SessionStatus::Cancelled, $sessions['2026-10-12']->fresh()->status);
        $this->assertSame(SessionStatus::Open, $sessions['2026-10-13']->fresh()->status);
        $this->assertNull($sessions['2026-10-13']->fresh()->schedule_exception_id);
        $this->assertSame(SessionStatus::Open, $sessions['2026-10-14']->fresh()->status);
        $this->assertSame(2, $report->reopened);

        $updated = $this->auditRows('exception.updated')->sole();
        $this->assertEquals(['start_date' => '2026-10-12', 'end_date' => '2026-10-14', 'doctor_id' => $this->doctor->id], $updated->old_values);
        $this->assertSame('2026-10-12', $updated->new_values['end_date']);
        $this->assertSame(2, $updated->new_values['impact']['reopened']);
    }

    public function test_moving_the_range_reopens_the_old_days_and_blocks_the_new_ones(): void
    {
        $sessions = [];

        foreach (['2026-10-12', '2026-10-13', '2026-10-14', '2026-10-15'] as $date) {
            $sessions[$date] = $this->makeSession($date);
        }

        $leave = $this->leave('2026-10-12', '2026-10-13');
        $this->service->applyException($leave, null, $this->actor);

        $previous = $this->previousOf($leave);
        $leave->update(['start_date' => '2026-10-13', 'end_date' => '2026-10-15']);
        $report = $this->service->applyException($leave, $previous, $this->actor);

        $this->assertSame(SessionStatus::Open, $sessions['2026-10-12']->fresh()->status);

        foreach (['2026-10-13', '2026-10-14', '2026-10-15'] as $date) {
            $this->assertSame(SessionStatus::Cancelled, $sessions[$date]->fresh()->status, $date);
            $this->assertSame($leave->id, (int) $sessions[$date]->fresh()->schedule_exception_id, $date);
        }

        $this->assertSame(1, $report->reopened);
        $this->assertSame(2, $report->cancelled);
    }

    public function test_changing_the_scope_to_another_doctor_releases_the_previous_doctors_sessions(): void
    {
        $other = Doctor::factory()->withSpecialties()->create(['clinic_id' => $this->clinic->id]);
        $mine = $this->makeSession('2026-10-12');
        $theirs = $this->makeSession('2026-10-12', [], $other);

        $leave = $this->createException([
            'type' => ExceptionType::Other,
            'doctor_id' => null,
            'start_date' => '2026-10-12',
            'end_date' => '2026-10-12',
            'reason' => 'Semua dokter',
        ]);
        $this->service->applyException($leave, null, $this->actor);
        $this->assertSame(SessionStatus::Cancelled, $theirs->fresh()->status);

        $previous = $this->previousOf($leave);
        $leave->update(['doctor_id' => $this->doctor->id]);
        $report = $this->service->applyException($leave, $previous, $this->actor);

        $this->assertSame(SessionStatus::Cancelled, $mine->fresh()->status);
        $this->assertSame(SessionStatus::Open, $theirs->fresh()->status);
        $this->assertSame(1, $report->reopened);
    }

    public function test_another_covering_exception_takes_over_before_a_session_is_reopened_f3_r10(): void
    {
        $holiday = $this->holiday('2026-10-12', '2026-10-12', 'Libur RS');
        $first = $this->leave('2026-10-12', '2026-10-12', null, 'Cuti A');
        $second = $this->leave('2026-10-12', '2026-10-12', null, 'Cuti B');
        $session = $this->makeSession('2026-10-12', [
            'status' => SessionStatus::Cancelled,
            'schedule_exception_id' => $first->id,
            'status_reason' => 'Cuti A',
        ]);

        $report = $this->service->removeException($first, $this->actor);
        $first->delete();

        $fresh = $session->fresh();
        $this->assertSame(SessionStatus::Cancelled, $fresh->status);
        $this->assertSame($second->id, (int) $fresh->schedule_exception_id, 'khusus dokter lebih dulu');
        $this->assertSame('Cuti B', $fresh->status_reason);
        $this->assertSame(0, $report->reopened);

        $this->service->removeException($second, $this->actor);
        $second->delete();

        $fresh = $session->fresh();
        $this->assertSame(SessionStatus::Cancelled, $fresh->status);
        $this->assertSame($holiday->id, (int) $fresh->schedule_exception_id);
        $this->assertSame('Libur RS', $fresh->status_reason);

        $final = $this->service->removeException($holiday, $this->actor);
        $holiday->delete();

        $this->assertSame(SessionStatus::Open, $session->fresh()->status);
        $this->assertSame(1, $final->reopened);
    }

    public function test_a_needs_review_session_moves_to_the_other_covering_exception_with_an_audit_row(): void
    {
        $first = $this->leave('2026-10-12', '2026-10-12', null, 'Cuti A');
        $second = $this->holiday('2026-10-12', '2026-10-12', 'Libur RS');
        $session = $this->withActiveAppointments($this->makeSession('2026-10-12', [
            'status' => SessionStatus::NeedsReview,
            'schedule_exception_id' => $first->id,
            'pending_change' => ['source' => 'exception', 'exception_id' => $first->id],
        ]));

        $this->service->removeException($first, $this->actor);
        $first->delete();

        $fresh = $session->fresh();
        $this->assertSame(SessionStatus::NeedsReview, $fresh->status);
        $this->assertSame(['source' => 'exception', 'exception_id' => $second->id], $fresh->pending_change);
        $this->assertSame($second->id, (int) $fresh->schedule_exception_id);
        $this->assertSame(1, $this->auditRows('session.pending_replaced')->count());
    }

    public function test_released_sessions_stay_held_when_the_doctor_is_inactive(): void
    {
        $leave = $this->leave('2026-10-12', '2026-10-13');
        $empty = $this->makeSession('2026-10-12', [
            'status' => SessionStatus::Cancelled,
            'schedule_exception_id' => $leave->id,
            'status_reason' => 'Cuti tahunan',
        ]);
        $booked = $this->withActiveAppointments($this->makeSession('2026-10-13', [
            'status' => SessionStatus::NeedsReview,
            'schedule_exception_id' => $leave->id,
            'pending_change' => ['source' => 'exception', 'exception_id' => $leave->id],
        ]));
        $this->doctor->forceFill(['status' => DoctorStatus::Inactive])->save();

        $report = $this->service->removeException($leave, $this->actor);
        $leave->delete();

        $heldEmpty = $empty->fresh();
        $this->assertSame(SessionStatus::Cancelled, $heldEmpty->status);
        $this->assertSame('Dokter tidak aktif', $heldEmpty->status_reason);
        $this->assertNull($heldEmpty->schedule_exception_id);

        $heldBooked = $booked->fresh();
        $this->assertSame(SessionStatus::NeedsReview, $heldBooked->status);
        $this->assertSame(['source' => 'doctor_inactive', 'doctor_id' => $this->doctor->id], $heldBooked->pending_change);

        $this->assertSame(0, $report->reopened);
        $this->assertSame(1, $report->needsReview);
    }

    public function test_released_sessions_follow_the_template_that_changed_while_the_exception_applied(): void
    {
        $schedule = $this->createSchedule([
            'doctor_id' => $this->doctor->id,
            'clinic_id' => $this->clinic->id,
            'day_of_week' => 1,
            'start_time' => '09:00:00',
            'end_time' => '13:00:00',
            'quota' => 20,
            'valid_from' => '2026-10-12',
            'valid_until' => '2026-10-26',
            'is_active' => true,
        ]);
        $leave = $this->leave('2026-10-12', '2026-10-26');
        $timeChanged = $this->withActiveAppointments($this->makeSession('2026-10-12', [
            'doctor_schedule_id' => $schedule->id,
            'status' => SessionStatus::NeedsReview,
            'schedule_exception_id' => $leave->id,
            'pending_change' => ['source' => 'exception', 'exception_id' => $leave->id],
        ]));
        $outOfRange = $this->makeSession('2026-10-27', [
            'doctor_schedule_id' => $schedule->id,
            'status' => SessionStatus::Cancelled,
            'schedule_exception_id' => $leave->id,
            'status_reason' => 'Cuti tahunan',
        ]);

        $report = $this->service->removeException($leave, $this->actor);
        $leave->delete();

        $fresh = $timeChanged->fresh();
        $this->assertSame(SessionStatus::NeedsReview, $fresh->status);
        $this->assertSame(
            ['source' => 'template_time', 'schedule_id' => $schedule->id, 'new_start_time' => '09:00', 'new_end_time' => '13:00'],
            $fresh->pending_change,
        );
        $this->assertNull($fresh->schedule_exception_id);

        $held = $outOfRange->fresh();
        $this->assertSame(SessionStatus::Cancelled, $held->status);
        $this->assertSame('Jadwal berubah', $held->status_reason);

        $this->assertSame(0, $report->reopened);
        $this->assertSame(1, $report->needsReview);
    }

    // ------------------------------------------------------------------ audit dan atomisitas

    public function test_exception_audit_rows_carry_the_values_and_the_impact_without_patient_data(): void
    {
        $session = $this->withActiveAppointments($this->makeSession('2026-10-12'));
        $this->makeSession('2026-10-13');
        $leave = $this->leave('2026-10-12', '2026-10-13');

        $this->service->applyException($leave, null, $this->actor);

        $created = $this->auditRows('exception.created')->sole();
        $this->assertSame($leave->id, (int) $created->auditable_id);
        $this->assertSame($this->actor->userId, (int) $created->user_id);
        $this->assertNull($created->old_values);
        $this->assertSame('doctor_leave', $created->new_values['type']);
        $this->assertSame($this->doctor->id, $created->new_values['doctor_id']);
        $this->assertSame('2026-10-12', $created->new_values['start_date']);
        $this->assertSame('Cuti tahunan', $created->new_values['reason']);
        $this->assertSame(1, $created->new_values['impact']['cancelled']);
        $this->assertSame(1, $created->new_values['impact']['needs_review']);
        $this->assertSame(SessionStatus::NeedsReview, $session->fresh()->status);

        $this->service->removeException($leave, $this->actor);

        $deleted = $this->auditRows('exception.deleted')->sole();
        $this->assertSame('doctor_leave', $deleted->old_values['type']);
        $this->assertSame(2, $deleted->new_values['impact']['reopened']);
        $this->assertSame(0, $deleted->new_values['impact']['needs_review']);
    }

    public function test_exception_change_is_rolled_back_when_the_audit_row_cannot_be_written(): void
    {
        $session = $this->makeSession('2026-10-12');
        $leave = $this->leave('2026-10-12', '2026-10-12');

        AuditLog::creating(fn () => throw new RuntimeException('audit gagal'));

        $thrown = false;

        try {
            $this->service->applyException($leave, null, $this->actor);
        } catch (RuntimeException) {
            $thrown = true;
        }

        $this->assertTrue($thrown);
        $this->assertSame(SessionStatus::Open, $session->fresh()->status);
        $this->assertNull($session->fresh()->schedule_exception_id);
    }
}
