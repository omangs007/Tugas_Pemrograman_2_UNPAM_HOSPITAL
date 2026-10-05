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
use App\Models\User;
use App\Services\Scheduling\DoctorAvailabilityQuery;
use App\Services\Scheduling\PracticeSessionGenerator;
use App\Services\Scheduling\ScheduleChangeService;
use App\Support\Actor;
use Carbon\CarbonImmutable;
use Database\Seeders\SettingsSeeder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use RuntimeException;
use Tests\Support\InteractsWithSchedules;
use Tests\TestCase;

/**
 * T8: sesi tambahan manual, edit satu sesi, status sesi, hapus sesi manual, dan dokter nonaktif. "Sekarang" =
 * Senin 2026-10-05 08:00. Aturan mutlak diuji di service, sehingga berlaku untuk semua peran (termasuk Super Admin).
 */
class ScheduleChangeSessionTest extends TestCase
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
    private function makeSession(string $date = '2026-10-12', array $overrides = [], ?Doctor $doctor = null): PracticeSession
    {
        $doctor ??= $this->doctor;

        return $this->createSession(array_merge([
            'doctor_id' => $doctor->id,
            'clinic_id' => $this->clinic->id,
            'doctor_schedule_id' => null,
            'session_date' => $date,
            'start_time' => '08:00:00',
            'end_time' => '12:00:00',
            'quota' => 10,
            'status' => SessionStatus::Open,
            'is_manually_modified' => false,
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
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function manualInput(array $overrides = []): array
    {
        return array_merge([
            'doctor_id' => $this->doctor->id,
            'clinic_id' => $this->clinic->id,
            'session_date' => '2026-10-06',
            'start_time' => '10:00',
            'end_time' => '12:00',
            'quota' => 10,
        ], $overrides);
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

    // ------------------------------------------------------------------ createManualSession (JDW-13)

    public function test_manual_session_is_created_open_and_manual_and_survives_regeneration_jdw13(): void
    {
        $first = $this->service->createManualSession($this->manualInput(), $this->actor);
        $second = $this->service->createManualSession($this->manualInput(['start_time' => '13:00', 'end_time' => '15:00']), $this->actor);

        $this->assertNull($first->doctor_schedule_id);
        $this->assertSame(SessionStatus::Open, $first->status);
        $this->assertTrue($first->is_manually_modified);
        $this->assertSame(0, $first->booked_count);
        $this->assertSame(0, $first->last_queue_number);
        $this->assertSame('2026-10-06', $first->session_date->toDateString());
        $this->assertSame('10:00:00', $first->start_time);
        $this->assertSame('12:00:00', $first->end_time);
        $this->assertSame(10, $first->quota);
        $this->assertNotSame($first->id, $second->id);

        $this->createSchedule([
            'doctor_id' => $this->doctor->id,
            'clinic_id' => $this->clinic->id,
            'day_of_week' => 2,
            'start_time' => '08:00:00',
            'end_time' => '09:00:00',
            'quota' => 5,
            'valid_from' => '2026-10-05',
            'valid_until' => null,
            'is_active' => true,
        ]);
        $this->app->make(PracticeSessionGenerator::class)->ensureRange(CarbonImmutable::today(), CarbonImmutable::today()->addDays(30));

        $this->assertModelExists($first);
        $this->assertModelExists($second);
        $this->assertSame(2, PracticeSession::query()->whereNull('doctor_schedule_id')->count());
        $this->assertTrue($this->query->forSession($first->fresh())->isBookableOnline);
        $this->assertSame(Availability::Available, $this->query->forSession($first->fresh())->availability);

        $log = $this->auditRows('session.created')->first();
        $this->assertSame($first->id, (int) $log->auditable_id);
        $this->assertSame($this->actor->userId, (int) $log->user_id);
        $this->assertNull($log->old_values);
        $this->assertEquals([
            'doctor_id' => $this->doctor->id,
            'clinic_id' => $this->clinic->id,
            'session_date' => '2026-10-06',
            'start_time' => '10:00',
            'end_time' => '12:00',
            'quota' => 10,
            'status' => 'open',
        ], $log->new_values);
    }

    public function test_manual_session_requires_an_active_doctor_an_open_clinic_valid_times_and_quota(): void
    {
        $inactive = Doctor::factory()->withSpecialties()->inactive()->create(['clinic_id' => $this->clinic->id]);
        $archived = Doctor::factory()->withSpecialties()->archived()->create(['clinic_id' => $this->clinic->id]);
        $dormant = Clinic::factory()->inactive()->create();
        $closed = Clinic::factory()->create(['is_bookable' => false]);
        $doctorMessage = 'Dokter yang dipilih tidak tersedia.';
        $clinicMessage = 'Poliklinik yang dipilih tidak tersedia atau tidak dibuka untuk pendaftaran.';

        $this->assertRejected(fn () => $this->service->createManualSession($this->manualInput(['doctor_id' => $inactive->id]), $this->actor), 'doctor_id', $doctorMessage);
        $this->assertRejected(fn () => $this->service->createManualSession($this->manualInput(['doctor_id' => $archived->id]), $this->actor), 'doctor_id', $doctorMessage);
        $this->assertRejected(fn () => $this->service->createManualSession($this->manualInput(['doctor_id' => 999999]), $this->actor), 'doctor_id', $doctorMessage);
        $this->assertRejected(fn () => $this->service->createManualSession($this->manualInput(['clinic_id' => $dormant->id]), $this->actor), 'clinic_id', $clinicMessage);
        $this->assertRejected(fn () => $this->service->createManualSession($this->manualInput(['clinic_id' => $closed->id]), $this->actor), 'clinic_id', $clinicMessage);
        $this->assertRejected(fn () => $this->service->createManualSession($this->manualInput(['start_time' => '25:00']), $this->actor), 'start_time', 'Format jam mulai tidak valid. Gunakan format JJ:MM.');
        $this->assertRejected(fn () => $this->service->createManualSession($this->manualInput(['end_time' => 'siang']), $this->actor), 'end_time', 'Format jam selesai tidak valid. Gunakan format JJ:MM.');
        $this->assertRejected(fn () => $this->service->createManualSession($this->manualInput(['start_time' => '12:00', 'end_time' => '12:00']), $this->actor), 'end_time', 'Jam selesai harus setelah jam mulai.');
        $this->assertRejected(fn () => $this->service->createManualSession($this->manualInput(['quota' => 0]), $this->actor), 'quota', 'Kuota minimal 1 dan maksimal 200.');
        $this->assertRejected(fn () => $this->service->createManualSession($this->manualInput(['quota' => 201]), $this->actor), 'quota', 'Kuota minimal 1 dan maksimal 200.');

        $this->assertSame(0, PracticeSession::query()->count());

        $this->service->createManualSession($this->manualInput(['quota' => 200]), $this->actor);
        $this->assertSame(1, PracticeSession::query()->count());
    }

    public function test_manual_session_dates_must_be_between_today_and_365_days_ahead_and_not_ended(): void
    {
        $past = 'Tanggal sesi tidak boleh di masa lalu.';

        $this->assertRejected(fn () => $this->service->createManualSession($this->manualInput(['session_date' => '2026-10-04']), $this->actor), 'session_date', $past);
        $this->assertRejected(fn () => $this->service->createManualSession($this->manualInput(['session_date' => '2026-10-05', 'start_time' => '06:00', 'end_time' => '07:00']), $this->actor), 'session_date', $past);
        $this->assertRejected(fn () => $this->service->createManualSession($this->manualInput(['session_date' => '2027-10-06']), $this->actor), 'session_date', 'Tanggal sesi maksimal 365 hari ke depan.');
        $this->assertRejected(fn () => $this->service->createManualSession($this->manualInput(['session_date' => null]), $this->actor), 'session_date', 'Tanggal sesi wajib diisi.');

        $this->assertSame(0, PracticeSession::query()->count());

        $this->service->createManualSession($this->manualInput(['session_date' => '2026-10-05', 'start_time' => '09:00', 'end_time' => '11:00']), $this->actor);
        $this->service->createManualSession($this->manualInput(['session_date' => '2027-10-05']), $this->actor);

        $this->assertSame(2, PracticeSession::query()->count());
    }

    public function test_manual_session_is_refused_inside_an_exception_or_when_it_overlaps_another_session(): void
    {
        $this->createException([
            'type' => ExceptionType::DoctorLeave,
            'doctor_id' => $this->doctor->id,
            'start_date' => '2026-10-07',
            'end_date' => '2026-10-07',
            'reason' => 'Cuti tahunan',
        ]);
        $overlap = 'Jam sesi bertabrakan dengan sesi lain milik dokter ini pada tanggal tersebut.';

        $this->assertRejected(
            fn () => $this->service->createManualSession($this->manualInput(['session_date' => '2026-10-07']), $this->actor),
            'session_date',
            'Tanggal ini berada dalam pengecualian jadwal (Cuti tahunan). Hapus atau ubah pengecualian terlebih dahulu.',
        );

        $this->service->createManualSession($this->manualInput(), $this->actor);

        $this->assertRejected(fn () => $this->service->createManualSession($this->manualInput(['start_time' => '11:00', 'end_time' => '13:00']), $this->actor), 'start_time', $overlap);
        $this->assertRejected(fn () => $this->service->createManualSession($this->manualInput(['start_time' => '09:00', 'end_time' => '10:30']), $this->actor), 'start_time', $overlap);

        // Bersebelahan (12:00 = akhir sesi lain) dan sesi batal tidak dihitung bentrok; dokter lain bebas.
        $this->service->createManualSession($this->manualInput(['start_time' => '12:00', 'end_time' => '14:00']), $this->actor);
        $this->service->createManualSession($this->manualInput(['start_time' => '08:00', 'end_time' => '10:00']), $this->actor);
        $this->makeSession('2026-10-06', ['start_time' => '15:00:00', 'end_time' => '17:00:00', 'status' => SessionStatus::Cancelled]);
        $this->service->createManualSession($this->manualInput(['start_time' => '16:00', 'end_time' => '17:00']), $this->actor);
        $other = Doctor::factory()->withSpecialties()->create(['clinic_id' => $this->clinic->id]);
        $this->service->createManualSession($this->manualInput(['doctor_id' => $other->id]), $this->actor);

        $this->assertSame(5, PracticeSession::query()->where('status', SessionStatus::Open->value)->count());
    }

    public function test_manual_session_creation_is_rolled_back_when_the_audit_row_cannot_be_written(): void
    {
        AuditLog::creating(fn () => throw new RuntimeException('audit gagal'));

        $thrown = false;

        try {
            $this->service->createManualSession($this->manualInput(), $this->actor);
        } catch (RuntimeException) {
            $thrown = true;
        }

        $this->assertTrue($thrown);
        $this->assertSame(0, PracticeSession::query()->count());
    }

    // ------------------------------------------------------------------ updateSession (JDW-07, JDW-15)

    public function test_quota_cannot_go_below_booked_count_but_equal_is_accepted_jdw07(): void
    {
        $session = $this->withAppointments($this->makeSession(), 5);

        $this->assertRejected(
            fn () => $this->service->updateSession($session, ['quota' => 4], $this->actor),
            'quota',
            'Kuota tidak boleh kurang dari jumlah pendaftaran yang sudah ada (5).',
        );
        $this->assertSame(10, $session->fresh()->quota);
        $this->assertFalse($session->fresh()->is_manually_modified);
        $this->assertSame(0, $this->auditRows('session.updated')->count());

        $this->assertRejected(fn () => $this->service->updateSession($session, ['quota' => 0], $this->actor), 'quota', 'Kuota minimal 1 dan maksimal 200.');
        $this->assertRejected(fn () => $this->service->updateSession($session, ['quota' => 201], $this->actor), 'quota', 'Kuota minimal 1 dan maksimal 200.');

        $updated = $this->service->updateSession($session, ['quota' => 5], $this->actor);

        $this->assertSame(5, $updated->quota);
        $this->assertSame(5, $session->fresh()->quota);
        $this->assertTrue($session->fresh()->is_manually_modified);

        $log = $this->auditRows('session.updated')->sole();
        $this->assertEquals(['quota' => 10], $log->old_values);
        $this->assertEquals(['quota' => 5], $log->new_values);
    }

    public function test_quota_increase_on_a_session_with_active_appointments_applies_immediately_jdw15(): void
    {
        $session = $this->withAppointments($this->makeSession(), 3);

        $this->service->updateSession($session, ['quota' => 25], $this->actor);

        $fresh = $session->fresh();
        $this->assertSame(25, $fresh->quota);
        $this->assertSame(SessionStatus::Open, $fresh->status);
        $this->assertNull($fresh->pending_change);
        $this->assertTrue($fresh->is_manually_modified);
    }

    public function test_time_change_applies_directly_without_active_appointments_jdw15(): void
    {
        $session = $this->withAppointments($this->makeSession(), 1, AppointmentStatus::Cancelled);

        $this->service->updateSession($session, ['start_time' => '09:00', 'end_time' => '13:00'], $this->actor);

        $fresh = $session->fresh();
        $this->assertSame('09:00:00', $fresh->start_time);
        $this->assertSame('13:00:00', $fresh->end_time);
        $this->assertSame(SessionStatus::Open, $fresh->status);
        $this->assertNull($fresh->pending_change);
        $this->assertTrue($fresh->is_manually_modified);

        $log = $this->auditRows('session.updated')->sole();
        $this->assertEquals(['start_time' => '08:00', 'end_time' => '12:00'], $log->old_values);
        $this->assertEquals(['start_time' => '09:00', 'end_time' => '13:00'], $log->new_values);
    }

    public function test_time_change_with_active_appointments_waits_for_review_as_manual_time_jdw15(): void
    {
        $session = $this->withAppointments($this->makeSession(), 2);

        $this->service->updateSession($session, ['quota' => 15, 'start_time' => '09:00', 'end_time' => '13:00'], $this->actor);

        $fresh = $session->fresh();
        $this->assertSame(SessionStatus::NeedsReview, $fresh->status);
        $this->assertSame(['source' => 'manual_time', 'new_start_time' => '09:00', 'new_end_time' => '13:00'], $fresh->pending_change);
        $this->assertSame('08:00:00', $fresh->start_time);
        $this->assertSame('12:00:00', $fresh->end_time);
        $this->assertSame(15, $fresh->quota);
        $this->assertTrue($fresh->is_manually_modified);
        $this->assertSame(2, $fresh->appointments()->active()->count());
        $this->assertSame(2, $fresh->booked_count);

        $log = $this->auditRows('session.updated')->sole();
        $this->assertSame('open', $log->old_values['status']);
        $this->assertSame('needs_review', $log->new_values['status']);
        $this->assertSame('manual_time', $log->new_values['pending_change']['source']);
        $this->assertSame(0, $this->auditRows('session.pending_replaced')->count());
    }

    public function test_a_manual_time_replaces_a_template_time_but_never_an_exception_jdw17c(): void
    {
        $schedule = $this->createSchedule([
            'doctor_id' => $this->doctor->id,
            'clinic_id' => $this->clinic->id,
            'day_of_week' => 1,
            'start_time' => '08:00:00',
            'end_time' => '12:00:00',
            'quota' => 10,
            'valid_from' => '2026-10-12',
            'valid_until' => '2026-10-19',
            'is_active' => true,
        ]);
        $templateTime = $this->withAppointments($this->makeSession('2026-10-12', [
            'doctor_schedule_id' => $schedule->id,
            'status' => SessionStatus::NeedsReview,
            'pending_change' => ['source' => 'template_time', 'schedule_id' => $schedule->id, 'new_start_time' => '09:00', 'new_end_time' => '13:00'],
        ]), 2);
        $exception = $this->createException([
            'type' => ExceptionType::DoctorLeave,
            'doctor_id' => $this->doctor->id,
            'start_date' => '2026-10-19',
            'end_date' => '2026-10-19',
            'reason' => 'Cuti tahunan',
        ]);
        $underException = $this->withAppointments($this->makeSession('2026-10-19', [
            'doctor_schedule_id' => $schedule->id,
            'status' => SessionStatus::NeedsReview,
            'schedule_exception_id' => $exception->id,
            'pending_change' => ['source' => 'exception', 'exception_id' => $exception->id],
        ]), 1);

        $this->service->updateSession($templateTime, ['start_time' => '10:00', 'end_time' => '14:00'], $this->actor);
        $this->service->updateSession($underException, ['start_time' => '10:00', 'end_time' => '14:00'], $this->actor);

        $this->assertSame(
            ['source' => 'manual_time', 'new_start_time' => '10:00', 'new_end_time' => '14:00'],
            $templateTime->fresh()->pending_change,
        );
        $this->assertSame(['source' => 'exception', 'exception_id' => $exception->id], $underException->fresh()->pending_change);

        $log = $this->auditRows('session.pending_replaced')->sole();
        $this->assertSame($templateTime->id, (int) $log->auditable_id);
        $this->assertSame('template_time', $log->old_values['pending_change']['source']);
        $this->assertSame('manual_time', $log->new_values['pending_change']['source']);
    }

    public function test_session_edit_validates_times_overlap_and_ended_sessions(): void
    {
        $session = $this->makeSession('2026-10-12', ['start_time' => '08:00:00', 'end_time' => '10:00:00']);
        $this->makeSession('2026-10-12', ['start_time' => '11:00:00', 'end_time' => '13:00:00']);

        $this->assertRejected(fn () => $this->service->updateSession($session, ['start_time' => '10:30', 'end_time' => '11:30'], $this->actor), 'start_time', 'Jam sesi bertabrakan dengan sesi lain milik dokter ini pada tanggal tersebut.');
        $this->assertRejected(fn () => $this->service->updateSession($session, ['end_time' => '08:00'], $this->actor), 'end_time', 'Jam selesai harus setelah jam mulai.');
        $this->assertRejected(fn () => $this->service->updateSession($session, ['start_time' => '8 pagi'], $this->actor), 'start_time', 'Format jam mulai tidak valid. Gunakan format JJ:MM.');
        $this->assertSame('08:00:00', $session->fresh()->start_time);

        $this->service->updateSession($session, ['end_time' => '11:00'], $this->actor);
        $this->assertSame('11:00:00', $session->fresh()->end_time);

        $ended = $this->makeSession('2026-10-05', ['start_time' => '06:00:00', 'end_time' => '07:00:00']);
        $this->assertRejected(fn () => $this->service->updateSession($ended, ['quota' => 3], $this->actor), 'session', 'Sesi yang sudah lewat tidak dapat diubah.');
        $this->assertSame(10, $ended->fresh()->quota);
    }

    public function test_an_update_that_changes_nothing_writes_no_audit_row_but_marks_the_session_manual(): void
    {
        $session = $this->makeSession();

        $this->service->updateSession($session, ['quota' => 10, 'start_time' => '08:00', 'end_time' => '12:00'], $this->actor);

        $this->assertTrue($session->fresh()->is_manually_modified);
        $this->assertSame(0, $this->auditRows('session.updated')->count());
    }

    // ------------------------------------------------------------------ setSessionStatus (JDW-15)

    public function test_open_and_closed_switch_directly_and_keep_existing_appointments(): void
    {
        $session = $this->withAppointments($this->makeSession(), 2);

        $this->service->setSessionStatus($session, SessionStatus::Closed, $this->actor, 'Ruang dipakai kegiatan lain');

        $closed = $session->fresh();
        $this->assertSame(SessionStatus::Closed, $closed->status);
        $this->assertSame('Ruang dipakai kegiatan lain', $closed->status_reason);
        $this->assertTrue($closed->is_manually_modified);
        $this->assertSame(2, $closed->appointments()->active()->count());
        $this->assertSame(Availability::Closed, $this->query->forSession($closed)->availability);

        $this->service->setSessionStatus($session, SessionStatus::Open, $this->actor);

        $open = $session->fresh();
        $this->assertSame(SessionStatus::Open, $open->status);
        $this->assertNull($open->status_reason);

        $logs = $this->auditRows('session.status_changed');
        $this->assertSame(2, $logs->count());
        $this->assertEquals(['status' => 'open', 'status_reason' => null], $logs[0]->old_values);
        $this->assertEquals(['status' => 'closed', 'status_reason' => 'Ruang dipakai kegiatan lain'], $logs[0]->new_values);
        $this->assertEquals(['status' => 'open', 'status_reason' => null], $logs[1]->new_values);
    }

    public function test_cancelling_without_active_appointments_cancels_immediately_jdw15(): void
    {
        $empty = $this->makeSession('2026-10-12');
        $history = $this->withAppointments($this->makeSession('2026-10-13'), 1, AppointmentStatus::Cancelled);

        foreach ([$empty, $history] as $session) {
            $this->service->setSessionStatus($session, SessionStatus::Cancelled, $this->actor, 'Dokter berhalangan');

            $fresh = $session->fresh();
            $this->assertSame(SessionStatus::Cancelled, $fresh->status);
            $this->assertSame('Dokter berhalangan', $fresh->status_reason);
            $this->assertNull($fresh->pending_change);
            $this->assertTrue($fresh->is_manually_modified);
            $this->assertSame(Availability::Cancelled, $this->query->forSession($fresh)->availability);
        }
    }

    public function test_cancelling_with_active_appointments_waits_for_review_as_manual_cancel_jdw15(): void
    {
        $session = $this->withAppointments($this->makeSession(), 2);

        $this->service->setSessionStatus($session, SessionStatus::Cancelled, $this->actor, 'Dokter sakit');

        $fresh = $session->fresh();
        $this->assertSame(SessionStatus::NeedsReview, $fresh->status);
        $this->assertSame(['source' => 'manual_cancel', 'reason' => 'Dokter sakit'], $fresh->pending_change);
        $this->assertTrue($fresh->is_manually_modified);
        $this->assertSame(2, $fresh->appointments()->active()->count());
        $this->assertSame(Availability::UnderReview, $this->query->forSession($fresh)->availability);

        $log = $this->auditRows('session.status_changed')->sole();
        $this->assertSame('open', $log->old_values['status']);
        $this->assertSame('needs_review', $log->new_values['status']);
        $this->assertSame('manual_cancel', $log->new_values['pending_change']['source']);
    }

    public function test_cancellation_requires_a_reason(): void
    {
        $session = $this->makeSession();

        $this->assertRejected(fn () => $this->service->setSessionStatus($session, SessionStatus::Cancelled, $this->actor), 'reason', 'Alasan pembatalan sesi wajib diisi.');
        $this->assertRejected(fn () => $this->service->setSessionStatus($session, SessionStatus::Cancelled, $this->actor, '   '), 'reason', 'Alasan pembatalan sesi wajib diisi.');
        $this->assertRejected(fn () => $this->service->setSessionStatus($session, SessionStatus::Cancelled, $this->actor, str_repeat('x', 256)), 'reason', 'Alasan maksimal 255 karakter.');

        $this->assertSame(SessionStatus::Open, $session->fresh()->status);
        $this->assertSame(0, $this->auditRows('session.status_changed')->count());
    }

    public function test_a_cancelled_session_reopens_only_when_no_exception_covers_it_and_the_doctor_is_active(): void
    {
        $session = $this->makeSession('2026-10-12', ['status' => SessionStatus::Cancelled, 'status_reason' => 'Dokter berhalangan']);
        $exception = $this->createException([
            'type' => ExceptionType::DoctorLeave,
            'doctor_id' => $this->doctor->id,
            'start_date' => '2026-10-12',
            'end_date' => '2026-10-12',
            'reason' => 'Cuti tahunan',
        ]);

        $this->assertRejected(
            fn () => $this->service->setSessionStatus($session, SessionStatus::Open, $this->actor),
            'status',
            'Tanggal ini berada dalam pengecualian jadwal (Cuti tahunan). Hapus atau ubah pengecualian terlebih dahulu.',
        );

        $exception->delete();
        $this->doctor->forceFill(['status' => DoctorStatus::Inactive])->save();

        $this->assertRejected(
            fn () => $this->service->setSessionStatus($session, SessionStatus::Open, $this->actor),
            'status',
            'Sesi tidak dapat dibuka karena dokter belum aktif kembali.',
        );
        $this->assertSame(SessionStatus::Cancelled, $session->fresh()->status);

        $this->doctor->forceFill(['status' => DoctorStatus::Active])->save();
        $this->service->setSessionStatus($session, SessionStatus::Open, $this->actor);

        $fresh = $session->fresh();
        $this->assertSame(SessionStatus::Open, $fresh->status);
        $this->assertNull($fresh->status_reason);
        $this->assertNull($fresh->schedule_exception_id);
        $this->assertTrue($fresh->is_manually_modified);
    }

    public function test_status_changes_are_refused_for_ended_review_and_nonsensical_transitions(): void
    {
        $ended = $this->makeSession('2026-10-05', ['start_time' => '06:00:00', 'end_time' => '07:00:00']);
        $review = $this->makeSession('2026-10-12', [
            'status' => SessionStatus::NeedsReview,
            'pending_change' => ['source' => 'doctor_inactive', 'doctor_id' => $this->doctor->id],
        ]);
        $cancelled = $this->makeSession('2026-10-13', ['status' => SessionStatus::Cancelled, 'status_reason' => 'Dokter berhalangan']);
        $open = $this->makeSession('2026-10-14');

        $this->assertRejected(fn () => $this->service->setSessionStatus($ended, SessionStatus::Closed, $this->actor), 'session', 'Sesi yang sudah lewat tidak dapat diubah.');
        $this->assertRejected(fn () => $this->service->setSessionStatus($ended, SessionStatus::Cancelled, $this->actor, 'Alasan'), 'session', 'Sesi yang sudah lewat tidak dapat diubah.');
        $this->assertRejected(fn () => $this->service->setSessionStatus($review, SessionStatus::Open, $this->actor), 'status', 'Sesi ini sedang menunggu penyelesaian di layar Sesi terdampak.');
        $this->assertRejected(fn () => $this->service->setSessionStatus($cancelled, SessionStatus::Closed, $this->actor), 'status', 'Sesi yang dibatalkan hanya dapat dibuka kembali.');

        $same = $this->service->setSessionStatus($open, SessionStatus::Open, $this->actor);
        $this->assertSame(SessionStatus::Open, $same->status);
        $this->assertFalse($open->fresh()->is_manually_modified);
        $this->assertSame(0, $this->auditRows('session.status_changed')->count());

        $this->expectException(InvalidArgumentException::class);
        $this->service->setSessionStatus($open, SessionStatus::NeedsReview, $this->actor);
    }

    // ------------------------------------------------------------------ deleteManualSession (JDW-19 c, d, e; CRD-13)

    public function test_a_manual_session_without_appointments_is_deleted_and_audited_jdw19c(): void
    {
        $manual = $this->service->createManualSession($this->manualInput(), $this->actor);

        $this->service->deleteManualSession($manual, $this->actor);

        $this->assertModelMissing($manual);

        $log = $this->auditRows('session.deleted')->sole();
        $this->assertSame($manual->id, (int) $log->auditable_id);
        $this->assertSame($this->actor->userId, (int) $log->user_id);
        $this->assertSame('2026-10-06', $log->old_values['session_date']);
        $this->assertSame('10:00', $log->old_values['start_time']);
        $this->assertNull($log->new_values);
    }

    public function test_a_manual_session_that_ever_had_an_appointment_cannot_be_deleted_jdw19d(): void
    {
        $message = 'Sesi ini sudah memiliki pendaftaran. Tutup atau batalkan sesi ini.';
        $active = $this->withAppointments($this->makeSession('2026-10-12'), 1);
        $history = $this->withAppointments($this->makeSession('2026-10-13'), 1, AppointmentStatus::Cancelled);

        $this->assertRejected(fn () => $this->service->deleteManualSession($active, $this->actor), 'session', $message);
        $this->assertRejected(fn () => $this->service->deleteManualSession($history, $this->actor), 'session', $message);

        $this->assertModelExists($active);
        $this->assertModelExists($history);
        $this->assertSame(0, $this->auditRows('session.deleted')->count());
    }

    public function test_a_session_generated_from_a_template_cannot_be_deleted_jdw19e(): void
    {
        $schedule = $this->createSchedule([
            'doctor_id' => $this->doctor->id,
            'clinic_id' => $this->clinic->id,
            'day_of_week' => 1,
            'start_time' => '08:00:00',
            'end_time' => '12:00:00',
            'quota' => 10,
            'valid_from' => '2026-10-12',
            'valid_until' => '2026-10-12',
            'is_active' => true,
        ]);
        $session = $this->makeSession('2026-10-12', ['doctor_schedule_id' => $schedule->id]);

        $this->assertRejected(
            fn () => $this->service->deleteManualSession($session, $this->actor),
            'session',
            'Sesi dari templat tidak dapat dihapus. Tutup atau batalkan sesi ini.',
        );

        $this->assertModelExists($session);
    }

    public function test_an_ended_manual_session_cannot_be_deleted(): void
    {
        $ended = $this->makeSession('2026-10-05', ['start_time' => '06:00:00', 'end_time' => '07:00:00']);

        $this->assertRejected(fn () => $this->service->deleteManualSession($ended, $this->actor), 'session', 'Sesi yang sudah lewat tidak dapat diubah.');

        $this->assertModelExists($ended);
    }

    // ------------------------------------------------------------------ applyDoctorInactive (JDW-16, JDW-17 d)

    public function test_inactive_doctor_sessions_are_cancelled_or_held_for_review_jdw16(): void
    {
        $other = Doctor::factory()->withSpecialties()->create(['clinic_id' => $this->clinic->id]);
        $schedule = $this->createSchedule([
            'doctor_id' => $this->doctor->id,
            'clinic_id' => $this->clinic->id,
            'day_of_week' => 1,
            'start_time' => '08:00:00',
            'end_time' => '12:00:00',
            'quota' => 10,
            'valid_from' => '2026-10-12',
            'valid_until' => '2026-10-12',
            'is_active' => true,
        ]);
        $empty = $this->makeSession('2026-10-12', ['doctor_schedule_id' => $schedule->id]);
        $booked = $this->withAppointments($this->makeSession('2026-10-13'), 2);
        $closed = $this->makeSession('2026-10-14', ['status' => SessionStatus::Closed]);
        $history = $this->withAppointments($this->makeSession('2026-10-15'), 1, AppointmentStatus::Cancelled);
        $alreadyCancelled = $this->makeSession('2026-10-16', ['status' => SessionStatus::Cancelled, 'status_reason' => 'Cuti tahunan']);
        $ended = $this->withAppointments($this->makeSession('2026-10-05', ['start_time' => '06:00:00', 'end_time' => '07:00:00']), 1);
        $review = $this->withAppointments($this->makeSession('2026-10-17', [
            'status' => SessionStatus::NeedsReview,
            'pending_change' => ['source' => 'template_time', 'schedule_id' => $schedule->id, 'new_start_time' => '09:00', 'new_end_time' => '13:00'],
        ]), 1);
        $theirs = $this->makeSession('2026-10-12', [], $other);
        $this->doctor->forceFill(['status' => DoctorStatus::Inactive])->save();

        $report = $this->service->applyDoctorInactive($this->doctor, $this->actor);

        foreach ([$empty, $closed, $history] as $session) {
            $fresh = $session->fresh();

            $this->assertSame(SessionStatus::Cancelled, $fresh->status);
            $this->assertSame('Dokter tidak aktif', $fresh->status_reason);
            $this->assertNull($fresh->pending_change);
        }

        foreach ([$booked, $review] as $session) {
            $fresh = $session->fresh();

            $this->assertSame(SessionStatus::NeedsReview, $fresh->status);
            $this->assertSame(['source' => 'doctor_inactive', 'doctor_id' => $this->doctor->id], $fresh->pending_change);
            $this->assertGreaterThan(0, $fresh->appointments()->active()->count());
        }

        $this->assertSame('Cuti tahunan', $alreadyCancelled->fresh()->status_reason);
        $this->assertSame(SessionStatus::Open, $ended->fresh()->status);
        $this->assertSame(SessionStatus::Open, $theirs->fresh()->status);

        $this->assertSame(3, $report->cancelled);
        $this->assertSame(2, $report->needsReview);
        $this->assertSame('3 sesi dibatalkan, 2 sesi perlu ditindaklanjuti petugas pendaftaran.', $report->message());

        $replaced = $this->auditRows('session.pending_replaced')->sole();
        $this->assertSame($review->id, (int) $replaced->auditable_id);
        $this->assertSame('template_time', $replaced->old_values['pending_change']['source']);
        $this->assertSame('doctor_inactive', $replaced->new_values['pending_change']['source']);
    }

    public function test_doctor_inactive_replaces_manual_cancel_with_an_audit_row_jdw17d(): void
    {
        $session = $this->withAppointments($this->makeSession(), 2);
        $this->service->setSessionStatus($session, SessionStatus::Cancelled, $this->actor, 'Dokter sakit');
        $this->assertSame('manual_cancel', $session->fresh()->pending_change['source']);

        $this->service->applyDoctorInactive($this->doctor, $this->actor);

        $fresh = $session->fresh();
        $this->assertSame(SessionStatus::NeedsReview, $fresh->status);
        $this->assertSame(['source' => 'doctor_inactive', 'doctor_id' => $this->doctor->id], $fresh->pending_change);

        $log = $this->auditRows('session.pending_replaced')->sole();
        $this->assertSame('manual_cancel', $log->old_values['pending_change']['source']);
        $this->assertSame('doctor_inactive', $log->new_values['pending_change']['source']);
    }

    public function test_applying_doctor_inactive_twice_is_idempotent(): void
    {
        $booked = $this->withAppointments($this->makeSession('2026-10-12'), 1);
        $empty = $this->makeSession('2026-10-13');

        $this->service->applyDoctorInactive($this->doctor, $this->actor);
        $second = $this->service->applyDoctorInactive($this->doctor, $this->actor);

        $this->assertSame(SessionStatus::NeedsReview, $booked->fresh()->status);
        $this->assertSame(SessionStatus::Cancelled, $empty->fresh()->status);
        $this->assertSame(0, $second->cancelled);
        $this->assertSame(1, $second->needsReview);
        $this->assertSame(0, $this->auditRows('session.pending_replaced')->count());
    }
}
