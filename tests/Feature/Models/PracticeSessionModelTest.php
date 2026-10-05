<?php

namespace Tests\Feature\Models;

use App\Enums\AppointmentStatus;
use App\Enums\SessionResolution;
use App\Enums\SessionStatus;
use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\PracticeSession;
use App\Models\ScheduleException;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InteractsWithSchedules;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

/**
 * 11 §5.3, 00 F.4, F3-R14 (sumber pending_change), F3-R15, F3-R20, F3-R27.
 */
class PracticeSessionModelTest extends TestCase
{
    use InteractsWithSchedules;
    use InteractsWithStaff;
    use RefreshDatabase;

    /** Senin 5 Oktober 2026, 10.00 WIB. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-05 10:00:00');
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function manualSession(array $overrides = []): PracticeSession
    {
        return $this->createSession(array_merge(['doctor_schedule_id' => null], $overrides));
    }

    public function test_pending_change_source_constants(): void
    {
        $this->assertSame('exception', PracticeSession::SOURCE_EXCEPTION);
        $this->assertSame('template_time', PracticeSession::SOURCE_TEMPLATE_TIME);
        $this->assertSame('template_removed', PracticeSession::SOURCE_TEMPLATE_REMOVED);
        $this->assertSame('manual_time', PracticeSession::SOURCE_MANUAL_TIME);
        $this->assertSame('manual_cancel', PracticeSession::SOURCE_MANUAL_CANCEL);
        $this->assertSame('doctor_inactive', PracticeSession::SOURCE_DOCTOR_INACTIVE);
        $this->assertSame(['exception', 'template_removed', 'manual_cancel', 'doctor_inactive'], PracticeSession::CANCEL_SOURCES);
        $this->assertSame(['template_time', 'manual_time'], PracticeSession::TIME_SOURCES);
        $this->assertSame([], array_intersect(PracticeSession::CANCEL_SOURCES, PracticeSession::TIME_SOURCES));
    }

    public function test_every_source_is_known_to_the_session_resolution_enum(): void
    {
        foreach ([...PracticeSession::CANCEL_SOURCES, ...PracticeSession::TIME_SOURCES] as $source) {
            $this->assertNotEmpty(SessionResolution::allowedFor($source), $source);
        }
    }

    public function test_casts_and_defaults(): void
    {
        $session = $this->createSession(['quota' => 12, 'session_date' => '2026-10-12'])->fresh();

        $this->assertSame('2026-10-12', $session->session_date->toDateString());
        $this->assertSame(12, $session->quota);
        $this->assertSame(0, $session->booked_count);
        $this->assertSame(0, $session->last_queue_number);
        $this->assertSame(SessionStatus::Open, $session->status);
        $this->assertFalse($session->is_manually_modified);
        $this->assertNull($session->pending_change);
        $this->assertNull($session->reviewed_at);
        $this->assertSame('08:00:00', $session->start_time);
    }

    public function test_counters_are_not_mass_assignable(): void
    {
        $this->expectException(MassAssignmentException::class);

        new PracticeSession(['booked_count' => 3]);
    }

    public function test_pending_change_round_trips_as_an_array(): void
    {
        $payload = ['source' => 'template_time', 'schedule_id' => 3, 'new_start_time' => '09:00', 'new_end_time' => '12:00'];
        $session = PracticeSession::factory()->needsReview($payload)->create()->fresh();

        $this->assertSame(SessionStatus::NeedsReview, $session->status);
        $this->assertSame($payload, $session->pending_change);
        $this->assertSame('template_time', $session->pendingSource());
    }

    public function test_pending_source_is_null_without_a_pending_change(): void
    {
        $this->assertNull($this->createSession()->pendingSource());
    }

    public function test_relations(): void
    {
        $schedule = $this->createSchedule();
        $exception = $this->createException(['doctor_id' => $schedule->doctor_id]);
        $session = $this->createSession([
            'doctor_id' => $schedule->doctor_id, 'clinic_id' => $schedule->clinic_id, 'doctor_schedule_id' => $schedule->id,
            'schedule_exception_id' => $exception->id,
        ]);
        $appointment = $this->bookAppointment($session);
        $reviewer = $this->createStaff('registration-officer');
        $session->forceFill(['reviewed_by' => $reviewer->id, 'reviewed_at' => now()])->save();

        $loaded = PracticeSession::query()->with(['doctor', 'clinic', 'schedule', 'exception', 'appointments', 'reviewer'])->findOrFail($session->id);

        $this->assertInstanceOf(Doctor::class, $loaded->doctor);
        $this->assertInstanceOf(Clinic::class, $loaded->clinic);
        $this->assertInstanceOf(DoctorSchedule::class, $loaded->schedule);
        $this->assertInstanceOf(ScheduleException::class, $loaded->exception);
        $this->assertSame([$appointment->id], $loaded->appointments->pluck('id')->all());
        $this->assertSame($reviewer->id, $loaded->reviewer->id);
        $this->assertSame([$session->id], $loaded->doctor->sessions()->pluck('id')->all());
        $this->assertSame([$session->id], $loaded->clinic->sessions()->pluck('id')->all());
        $this->assertSame([$appointment->id], $loaded->doctor->appointments()->pluck('id')->all());
        $this->assertSame([$appointment->id], $loaded->clinic->appointments()->pluck('id')->all());
    }

    // --- Scope ---

    public function test_between_and_on_date_scopes_include_both_ends_and_accept_strings(): void
    {
        $day = fn (string $date): PracticeSession => $this->manualSession(['session_date' => $date]);
        $a = $day('2026-10-11');
        $b = $day('2026-10-12');
        $c = $day('2026-10-14');
        $d = $day('2026-10-15');

        $between = PracticeSession::query()->between(CarbonImmutable::parse('2026-10-12'), '2026-10-14')->orderBy('id')->pluck('id')->all();

        $this->assertSame([$b->id, $c->id], $between);
        $this->assertSame([$d->id], PracticeSession::query()->onDate(CarbonImmutable::parse('2026-10-15'))->pluck('id')->all());
        $this->assertSame([$a->id], PracticeSession::query()->onDate('2026-10-11')->pluck('id')->all());
    }

    public function test_upcoming_scope_keeps_today_and_later_dates(): void
    {
        $yesterday = $this->manualSession(['session_date' => '2026-10-04']);
        $today = $this->manualSession(['session_date' => '2026-10-05']);
        $tomorrow = $this->manualSession(['session_date' => '2026-10-06']);

        $ids = PracticeSession::query()->upcoming()->orderBy('id')->pluck('id')->all();

        $this->assertSame([$today->id, $tomorrow->id], $ids);
        $this->assertNotContains($yesterday->id, $ids);
    }

    public function test_status_scopes(): void
    {
        $open = $this->manualSession();
        $review = PracticeSession::factory()->manual()->needsReview(['source' => 'exception', 'exception_id' => 1])->create();
        $closed = PracticeSession::factory()->manual()->closed()->create();
        $cancelled = PracticeSession::factory()->manual()->cancelled()->create();

        $this->assertSame([$open->id], PracticeSession::query()->open()->pluck('id')->all());
        $this->assertSame([$review->id], PracticeSession::query()->needsReview()->pluck('id')->all());
        $this->assertSame(SessionStatus::Closed, $closed->status);
        $this->assertSame(SessionStatus::Cancelled, $cancelled->status);
    }

    public function test_for_doctor_and_for_clinic_scopes(): void
    {
        $first = $this->manualSession();
        $second = $this->manualSession();

        $this->assertSame([$first->id], PracticeSession::query()->forDoctor($first->doctor_id)->pluck('id')->all());
        $this->assertSame([$second->id], PracticeSession::query()->forClinic($second->clinic_id)->pluck('id')->all());
    }

    public function test_not_ended_scope_boundaries_on_the_same_day(): void
    {
        $yesterday = $this->manualSession(['session_date' => '2026-10-04', 'start_time' => '08:00:00', 'end_time' => '23:00:00']);
        $today = $this->manualSession(['session_date' => '2026-10-05', 'start_time' => '08:00:00', 'end_time' => '12:00:00']);
        $tomorrow = $this->manualSession(['session_date' => '2026-10-06', 'start_time' => '08:00:00', 'end_time' => '09:00:00']);

        $ids = fn (string $now): array => PracticeSession::query()->notEnded(CarbonImmutable::parse($now))->orderBy('id')->pluck('id')->all();

        $this->assertSame([$today->id, $tomorrow->id], $ids('2026-10-05 11:59:59'), 'Satu detik sebelum selesai: belum berakhir.');
        $this->assertSame([$tomorrow->id], $ids('2026-10-05 12:00:00'), 'Tepat pada end_time: sudah berakhir.');
        $this->assertSame([$tomorrow->id], $ids('2026-10-05 12:00:01'));
        $this->assertSame([$today->id, $tomorrow->id], $ids('2026-10-05 00:00:00'), 'Pergantian hari 00:00: sesi hari itu belum berakhir.');
        $this->assertSame([$tomorrow->id], $ids('2026-10-05 23:59:59'));
        $this->assertSame([$tomorrow->id], $ids('2026-10-06 08:30:00'));
        $this->assertSame([], $ids('2026-10-06 09:00:00'));
        $this->assertNotContains($yesterday->id, $ids('2026-10-05 00:00:00'));
    }

    public function test_not_ended_scope_defaults_to_the_current_time(): void
    {
        $today = $this->manualSession(['session_date' => '2026-10-05', 'start_time' => '08:00:00', 'end_time' => '10:00:01']);
        $ended = $this->manualSession(['session_date' => '2026-10-05', 'start_time' => '07:00:00', 'end_time' => '10:00:00']);

        $ids = PracticeSession::query()->notEnded()->pluck('id')->all();

        $this->assertSame([$today->id], $ids);
        $this->assertNotContains($ended->id, $ids);
    }

    public function test_not_ended_scope_converts_other_time_zones_to_the_application_zone(): void
    {
        $session = $this->manualSession(['session_date' => '2026-10-05', 'start_time' => '08:00:00', 'end_time' => '12:00:00']);

        // 11:59 WIB = 04:59 UTC: belum berakhir; 12:00 WIB = 05:00 UTC: berakhir.
        $this->assertSame([$session->id], PracticeSession::query()->notEnded(CarbonImmutable::parse('2026-10-05 04:59:00', 'UTC'))->pluck('id')->all());
        $this->assertSame([], PracticeSession::query()->notEnded(CarbonImmutable::parse('2026-10-05 05:00:00', 'UTC'))->pluck('id')->all());
    }

    // --- Accessor dan method ---

    public function test_remaining_never_goes_negative(): void
    {
        $session = $this->manualSession(['quota' => 5]);
        $this->assertSame(5, $session->remaining);

        $this->bookAppointment($session);
        $this->bookAppointment($session);
        $this->assertSame(3, $session->remaining);

        $session->forceFill(['quota' => 1]);
        $this->assertSame(0, $session->remaining);
    }

    public function test_starts_at_and_ends_at_are_immutable_carbon_in_the_application_zone(): void
    {
        $session = $this->manualSession(['session_date' => '2026-10-12', 'start_time' => '08:30:00', 'end_time' => '12:15:00']);

        $this->assertInstanceOf(CarbonImmutable::class, $session->starts_at);
        $this->assertSame('2026-10-12 08:30:00', $session->starts_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-12 12:15:00', $session->ends_at->format('Y-m-d H:i:s'));
        $this->assertSame('Asia/Jakarta', $session->starts_at->timezoneName);

        $session->start_time = '09:00:00';
        $this->assertSame('09:00:00', $session->starts_at->format('H:i:s'), 'Tanpa cache objek: mengikuti perubahan jam.');
    }

    public function test_time_range_accessor(): void
    {
        $this->assertSame('08.30–12.15', $this->manualSession(['start_time' => '08:30:00', 'end_time' => '12:15:00'])->time_range);
    }

    public function test_is_ended_uses_the_end_time_inclusively(): void
    {
        $session = $this->manualSession(['session_date' => '2026-10-05', 'start_time' => '08:00:00', 'end_time' => '12:00:00']);

        $this->assertFalse($session->isEnded(CarbonImmutable::parse('2026-10-05 11:59:59')));
        $this->assertTrue($session->isEnded(CarbonImmutable::parse('2026-10-05 12:00:00')));
        $this->assertTrue($session->isEnded(CarbonImmutable::parse('2026-10-05 12:00:01')));
        $this->assertFalse($session->isEnded(), 'Bawaan memakai waktu sekarang (10.00).');

        $this->travelTo('2026-10-05 12:00:00');
        $this->assertTrue($session->isEnded());
    }

    public function test_is_manual_follows_the_schedule_link(): void
    {
        $this->assertTrue($this->manualSession()->isManual());
        $this->assertFalse($this->createSession()->isManual());
    }

    public function test_has_active_appointments(): void
    {
        $session = $this->manualSession();
        $this->assertFalse($session->hasActiveAppointments());

        $this->bookAppointment($session, AppointmentStatus::Cancelled);
        $this->bookAppointment($session, AppointmentStatus::Completed);
        $this->assertFalse($session->hasActiveAppointments());

        $this->bookAppointment($session, AppointmentStatus::CheckedIn);
        $this->assertTrue($session->hasActiveAppointments());
    }

    // --- deletionBlockedReason (F3-R20) ---

    public function test_a_template_session_cannot_be_deleted(): void
    {
        $this->assertSame(
            'Sesi dari templat tidak dapat dihapus. Tutup atau batalkan sesi ini.',
            $this->createSession()->deletionBlockedReason(),
        );
    }

    public function test_a_manual_session_with_any_appointment_cannot_be_deleted(): void
    {
        $session = $this->manualSession();
        $this->assertNull($session->deletionBlockedReason());

        $this->bookAppointment($session, AppointmentStatus::Cancelled);

        $this->assertSame(
            'Sesi ini sudah memiliki pendaftaran. Tutup atau batalkan sesi ini.',
            $session->deletionBlockedReason(),
        );
    }

    public function test_a_manual_session_without_appointments_can_be_deleted(): void
    {
        $this->assertNull($this->manualSession()->deletionBlockedReason());
    }

    public function test_appointment_upcoming_active_scope_follows_the_session_end_time(): void
    {
        $ended = $this->manualSession(['session_date' => '2026-10-05', 'start_time' => '06:00:00', 'end_time' => '09:00:00']);
        $running = $this->manualSession(['session_date' => '2026-10-05', 'start_time' => '09:00:00', 'end_time' => '12:00:00']);
        $future = $this->manualSession(['session_date' => '2026-10-06']);

        $this->bookAppointment($ended);
        $this->bookAppointment($running);
        $this->bookAppointment($future);

        $this->assertSame(
            [$running->id, $future->id],
            Appointment::query()->upcomingActive()->orderBy('practice_session_id')->pluck('practice_session_id')->all(),
        );
    }
}
