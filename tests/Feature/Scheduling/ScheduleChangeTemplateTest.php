<?php

namespace Tests\Feature\Scheduling;

use App\Enums\AppointmentStatus;
use App\Enums\ExceptionType;
use App\Enums\SessionStatus;
use App\Models\AuditLog;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\PracticeSession;
use App\Models\User;
use App\Services\Scheduling\ScheduleChangeReport;
use App\Services\Scheduling\ScheduleChangeService;
use App\Support\Actor;
use Carbon\CarbonImmutable;
use Database\Seeders\SettingsSeeder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\InteractsWithSchedules;
use Tests\TestCase;

/**
 * T6: propagasi perubahan templat (00 F.4). "Sekarang" = Senin 2026-10-05 08:00. Templat dasar: Senin 08.00–12.00,
 * kuota 20, berlaku 2026-10-12 s.d. 2026-10-18, sehingga hanya satu sesi (Senin 2026-10-12) yang mungkin ada.
 */
class ScheduleChangeTemplateTest extends TestCase
{
    use InteractsWithSchedules;
    use RefreshDatabase;

    private ScheduleChangeService $service;

    private Actor $actor;

    private Clinic $clinic;

    private Doctor $doctor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-10-05 08:00:00'));
        $this->seed(SettingsSeeder::class);

        $this->service = $this->app->make(ScheduleChangeService::class);
        $this->actor = Actor::staff(User::factory()->staff()->create());
        $this->clinic = Clinic::factory()->create();
        $this->doctor = Doctor::factory()->withSpecialties()->create(['clinic_id' => $this->clinic->id]);
    }

    // ------------------------------------------------------------------ pembantu

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function template(array $overrides = []): DoctorSchedule
    {
        return $this->createSchedule(array_merge([
            'doctor_id' => $this->doctor->id,
            'clinic_id' => $this->clinic->id,
            'day_of_week' => 1,
            'start_time' => '08:00:00',
            'end_time' => '12:00:00',
            'quota' => 20,
            'valid_from' => '2026-10-12',
            'valid_until' => '2026-10-18',
            'is_active' => true,
        ], $overrides))->fresh();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function templateSession(DoctorSchedule $schedule, string $date = '2026-10-12', array $overrides = []): PracticeSession
    {
        return $this->createSession(array_merge([
            'doctor_id' => $schedule->doctor_id,
            'clinic_id' => $schedule->clinic_id,
            'doctor_schedule_id' => $schedule->id,
            'session_date' => $date,
            'start_time' => '08:00:00',
            'end_time' => '12:00:00',
            'quota' => 20,
            'status' => SessionStatus::Open,
            'is_manually_modified' => false,
        ], $overrides));
    }

    /**
     * @param  'none'|'cancelled'|'active'  $kind
     */
    private function prepareSession(DoctorSchedule $schedule, string $kind, string $date = '2026-10-12'): PracticeSession
    {
        $session = $this->templateSession($schedule, $date);

        if ($kind === 'cancelled') {
            $this->bookAppointment($session->fresh(), AppointmentStatus::Cancelled);
        }

        if ($kind === 'active') {
            foreach (range(1, 3) as $ignored) {
                $this->bookAppointment($session->fresh(), AppointmentStatus::Confirmed);
            }
        }

        return $session->fresh();
    }

    /**
     * @return array<string, mixed>
     */
    private function changeAttributes(string $change): array
    {
        return match ($change) {
            'quota' => ['quota' => 30],
            'time' => ['start_time' => '09:00:00', 'end_time' => '13:00:00'],
            'day' => ['day_of_week' => 2],
            'deactivate' => ['is_active' => false],
            'range' => ['valid_from' => '2026-10-13'],
        };
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function change(DoctorSchedule $schedule, array $attributes): ScheduleChangeReport
    {
        $before = $schedule->getOriginal();
        $schedule->update($attributes);

        return $this->service->applyTemplateChange($schedule, $before, $this->actor);
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

    // ------------------------------------------------------------------ tabel keputusan F.4 (satu kasus per baris)

    /**
     * Kolom: jenis sesi (none = tanpa appointment sama sekali, cancelled = hanya riwayat batal, active = 3 appointment
     * aktif), jenis perubahan, akibat pada sesi, jumlah sesi templat sesudahnya, dan laporan
     * [regenerated, updated, quotaAdjusted, needsReview, cancelled, reopened].
     *
     * @return array<string, array{string, string, string, int, array<int, int>}>
     */
    public static function decisionTable(): array
    {
        return [
            'tanpa appointment × kuota' => ['none', 'quota', 'recreated', 1, [1, 0, 0, 0, 0, 0]],
            'tanpa appointment × jam' => ['none', 'time', 'recreated', 1, [1, 0, 0, 0, 0, 0]],
            'tanpa appointment × hari' => ['none', 'day', 'moved', 1, [1, 0, 0, 0, 0, 0]],
            'tanpa appointment × nonaktif' => ['none', 'deactivate', 'removed', 0, [0, 0, 0, 0, 0, 0]],
            'tanpa appointment × rentang menyempit' => ['none', 'range', 'removed', 0, [0, 0, 0, 0, 0, 0]],
            'hanya batal × kuota' => ['cancelled', 'quota', 'updated', 1, [0, 1, 0, 0, 0, 0]],
            'hanya batal × jam' => ['cancelled', 'time', 'updated', 1, [0, 1, 0, 0, 0, 0]],
            'hanya batal × hari' => ['cancelled', 'day', 'cancelled', 2, [1, 0, 0, 0, 1, 0]],
            'hanya batal × nonaktif' => ['cancelled', 'deactivate', 'cancelled', 1, [0, 0, 0, 0, 1, 0]],
            'hanya batal × rentang menyempit' => ['cancelled', 'range', 'cancelled', 1, [0, 0, 0, 0, 1, 0]],
            'appointment aktif × kuota' => ['active', 'quota', 'quota', 1, [0, 0, 1, 0, 0, 0]],
            'appointment aktif × jam' => ['active', 'time', 'pending_time', 1, [0, 0, 0, 1, 0, 0]],
            'appointment aktif × hari' => ['active', 'day', 'pending_removed', 2, [1, 0, 0, 1, 0, 0]],
            'appointment aktif × nonaktif' => ['active', 'deactivate', 'pending_removed', 1, [0, 0, 0, 1, 0, 0]],
            'appointment aktif × rentang menyempit' => ['active', 'range', 'pending_removed', 1, [0, 0, 0, 1, 0, 0]],
        ];
    }

    /**
     * @param  array<int, int>  $expectedReport
     */
    #[DataProvider('decisionTable')]
    public function test_f4_decision_table_row(string $kind, string $change, string $effect, int $sessionCount, array $expectedReport): void
    {
        $schedule = $this->template();
        $original = $this->prepareSession($schedule, $kind);

        $report = $this->change($schedule, $this->changeAttributes($change));

        $this->assertSame(
            $expectedReport,
            [$report->regenerated, $report->updated, $report->quotaAdjusted, $report->needsReview, $report->cancelled, $report->reopened],
        );
        $this->assertSame($sessionCount, PracticeSession::query()->where('doctor_schedule_id', $schedule->id)->count());

        $current = PracticeSession::query()->find($original->id);

        match ($effect) {
            'recreated' => $this->assertRecreated($original, $schedule, $change),
            'moved' => $this->assertMoved($original, $schedule),
            'removed' => $this->assertNull($current),
            'updated' => $this->assertUpdatedInPlace($current, $change),
            'cancelled' => $this->assertCancelledForTemplate($current),
            'quota' => $this->assertQuotaAdjusted($current, 30),
            'pending_time' => $this->assertPending($current, [
                'source' => 'template_time',
                'schedule_id' => $schedule->id,
                'new_start_time' => '09:00',
                'new_end_time' => '13:00',
            ]),
            'pending_removed' => $this->assertPending($current, ['source' => 'template_removed', 'schedule_id' => $schedule->id]),
        };

        if ($kind === 'active') {
            $this->assertSame(3, $original->appointments()->active()->count());
            $this->assertSame(3, $current->booked_count);
        }
    }

    private function assertRecreated(PracticeSession $original, DoctorSchedule $schedule, string $change): void
    {
        $this->assertModelMissing($original);

        $new = PracticeSession::query()->where('doctor_schedule_id', $schedule->id)->sole();

        $this->assertNotSame($original->id, $new->id);
        $this->assertSame('2026-10-12', $new->session_date->toDateString());
        $this->assertSame($change === 'quota' ? 30 : 20, $new->quota);
        $this->assertSame($change === 'time' ? '09:00:00' : '08:00:00', $new->start_time);
        $this->assertSame($change === 'time' ? '13:00:00' : '12:00:00', $new->end_time);
        $this->assertSame(SessionStatus::Open, $new->status);
    }

    private function assertMoved(PracticeSession $original, DoctorSchedule $schedule): void
    {
        $this->assertModelMissing($original);
        $this->assertSame('2026-10-13', PracticeSession::query()->where('doctor_schedule_id', $schedule->id)->sole()->session_date->toDateString());
    }

    private function assertUpdatedInPlace(?PracticeSession $session, string $change): void
    {
        $this->assertNotNull($session);
        $this->assertSame(SessionStatus::Open, $session->status);
        $this->assertSame($change === 'quota' ? 30 : 20, $session->quota);
        $this->assertSame($change === 'time' ? '09:00:00' : '08:00:00', $session->start_time);
        $this->assertSame($change === 'time' ? '13:00:00' : '12:00:00', $session->end_time);
        $this->assertSame(1, $session->appointments()->count());
    }

    private function assertCancelledForTemplate(?PracticeSession $session): void
    {
        $this->assertNotNull($session);
        $this->assertSame(SessionStatus::Cancelled, $session->status);
        $this->assertSame('Jadwal berubah', $session->status_reason);
        $this->assertNull($session->pending_change);
        $this->assertSame(1, $session->appointments()->count());
    }

    private function assertQuotaAdjusted(?PracticeSession $session, int $quota): void
    {
        $this->assertNotNull($session);
        $this->assertSame(SessionStatus::Open, $session->status);
        $this->assertNull($session->pending_change);
        $this->assertSame($quota, $session->quota);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function assertPending(?PracticeSession $session, array $payload): void
    {
        $this->assertNotNull($session);
        $this->assertSame(SessionStatus::NeedsReview, $session->status);
        $this->assertSame($payload, $session->pending_change);
        $this->assertSame('08:00:00', $session->start_time);
        $this->assertSame('12:00:00', $session->end_time);
    }

    // ------------------------------------------------------------------ kuota (JDW-07 bagian templat)

    public function test_quota_never_drops_below_booked_count_when_the_template_quota_is_lowered(): void
    {
        $schedule = $this->template();
        $session = $this->prepareSession($schedule, 'active');

        $report = $this->change($schedule, ['quota' => 2]);

        $fresh = $session->fresh();
        $this->assertSame(3, $fresh->quota);
        $this->assertSame(3, $fresh->booked_count);
        $this->assertSame(SessionStatus::Open, $fresh->status);
        $this->assertSame(1, $report->quotaAdjusted);
        $this->assertSame('1 sesi disesuaikan kuotanya.', $report->message());
    }

    public function test_time_and_quota_changed_together_adjust_the_quota_now_and_hold_the_time_for_review(): void
    {
        $schedule = $this->template();
        $session = $this->prepareSession($schedule, 'active');

        $report = $this->change($schedule, ['quota' => 30, 'start_time' => '09:00:00', 'end_time' => '13:00:00']);

        $fresh = $session->fresh();
        $this->assertSame(30, $fresh->quota);
        $this->assertSame('08:00:00', $fresh->start_time);
        $this->assertSame(SessionStatus::NeedsReview, $fresh->status);
        $this->assertSame('template_time', $fresh->pending_change['source']);
        $this->assertSame(1, $report->quotaAdjusted);
        $this->assertSame(1, $report->needsReview);
    }

    // ------------------------------------------------------------------ sesi lampau dan sesi manual tidak tersentuh

    /**
     * @return array<string, array{string, bool}>
     */
    public static function endBoundary(): array
    {
        return [
            'satu detik sebelum selesai' => ['2026-10-12 11:59:59', true],
            'tepat saat selesai' => ['2026-10-12 12:00:00', false],
            'hari berikutnya' => ['2026-10-13 00:00:00', false],
        ];
    }

    #[DataProvider('endBoundary')]
    public function test_only_sessions_that_have_not_ended_are_processed(string $now, bool $processed): void
    {
        $schedule = $this->template();
        $session = $this->prepareSession($schedule, 'none');
        $this->travelTo(CarbonImmutable::parse($now));

        $report = $this->change($schedule, ['quota' => 30]);

        if ($processed) {
            $this->assertModelMissing($session);
            $this->assertSame(30, PracticeSession::query()->where('doctor_schedule_id', $schedule->id)->sole()->quota);
            $this->assertSame(1, $report->regenerated);

            return;
        }

        $this->assertModelExists($session);
        $this->assertSame(20, $session->fresh()->quota);
        $this->assertSame(1, PracticeSession::query()->where('doctor_schedule_id', $schedule->id)->count());
        $this->assertSame('', $report->message());
    }

    public function test_past_sessions_with_active_appointments_are_never_flagged_for_review(): void
    {
        $schedule = $this->template();
        $session = $this->prepareSession($schedule, 'active');
        $this->travelTo(CarbonImmutable::parse('2026-10-12 13:00:00'));

        $report = $this->change($schedule, ['is_active' => false]);

        $fresh = $session->fresh();
        $this->assertSame(SessionStatus::Open, $fresh->status);
        $this->assertNull($fresh->pending_change);
        $this->assertSame('', $report->message());
    }

    public function test_manual_sessions_modified_sessions_and_other_templates_are_untouched(): void
    {
        $schedule = $this->template(['valid_until' => '2026-10-25']);
        $modified = $this->templateSession($schedule, '2026-10-12', ['is_manually_modified' => true, 'quota' => 15]);
        $modifiedActive = $this->templateSession($schedule, '2026-10-19', ['is_manually_modified' => true]);
        $this->bookAppointment($modifiedActive->fresh(), AppointmentStatus::Confirmed);
        $manual = $this->createSession([
            'doctor_id' => $this->doctor->id,
            'clinic_id' => $this->clinic->id,
            'doctor_schedule_id' => null,
            'session_date' => '2026-10-12',
            'start_time' => '13:00:00',
            'end_time' => '15:00:00',
            'quota' => 8,
            'status' => SessionStatus::Open,
        ]);
        $other = $this->template(['day_of_week' => 2, 'valid_from' => '2026-10-13', 'valid_until' => '2026-10-13']);
        $otherSession = $this->templateSession($other, '2026-10-13');

        $report = $this->change($schedule, ['is_active' => false]);

        $this->assertSame(15, $modified->fresh()->quota);
        $this->assertSame(SessionStatus::Open, $modified->fresh()->status);
        $this->assertSame(SessionStatus::Open, $modifiedActive->fresh()->status);
        $this->assertNull($modifiedActive->fresh()->pending_change);
        $this->assertSame(8, $manual->fresh()->quota);
        $this->assertSame(SessionStatus::Open, $manual->fresh()->status);
        $this->assertModelExists($otherSession);
        $this->assertSame('', $report->message());
    }

    public function test_nothing_is_churned_when_no_relevant_field_changed(): void
    {
        $schedule = $this->template();
        $session = $this->prepareSession($schedule, 'none');

        $report = $this->change($schedule, ['notes' => 'Catatan baru']);

        $this->assertModelExists($session);
        $this->assertSame($session->id, PracticeSession::query()->where('doctor_schedule_id', $schedule->id)->sole()->id);
        $this->assertSame('', $report->message());
    }

    public function test_extending_the_validity_generates_the_new_days(): void
    {
        $schedule = $this->template();
        $this->prepareSession($schedule, 'none');

        $report = $this->change($schedule, ['valid_until' => '2026-10-25']);

        $this->assertSame(1, $report->regenerated);
        $this->assertSame(2, PracticeSession::query()->where('doctor_schedule_id', $schedule->id)->count());
    }

    // ------------------------------------------------------------------ ganti poliklinik (JDW-08 d)

    public function test_clinic_change_is_rejected_when_an_upcoming_session_has_active_appointments(): void
    {
        $schedule = $this->template();
        $session = $this->prepareSession($schedule, 'active');
        $other = Clinic::factory()->create();

        $this->assertRejected(
            fn () => $this->service->assertClinicChangeAllowed($schedule, $other->id),
            'clinic_id',
            'Poliklinik tidak dapat diganti karena masih ada sesi mendatang dengan pendaftaran aktif. Buat jadwal baru, lalu nonaktifkan jadwal lama.',
        );

        $this->service->assertClinicChangeAllowed($schedule, $this->clinic->id);
        $this->assertSame($this->clinic->id, (int) $session->fresh()->clinic_id);
    }

    public function test_clinic_change_is_allowed_for_history_only_ended_or_absent_sessions(): void
    {
        $other = Clinic::factory()->create();

        $empty = $this->template();
        $this->service->assertClinicChangeAllowed($empty, $other->id);

        $history = $this->template();
        $this->prepareSession($history, 'cancelled');
        $this->service->assertClinicChangeAllowed($history, $other->id);

        $ended = $this->template();
        $this->prepareSession($ended, 'active');
        $this->travelTo(CarbonImmutable::parse('2026-10-12 12:00:00'));
        $this->service->assertClinicChangeAllowed($ended, $other->id);

        $this->addToAssertionCount(1);
    }

    public function test_allowed_clinic_change_regenerates_empty_sessions_and_detaches_history_only_ones(): void
    {
        $schedule = $this->template(['valid_until' => '2026-10-25']);
        $history = $this->templateSession($schedule, '2026-10-12');
        $this->bookAppointment($history->fresh(), AppointmentStatus::Cancelled);
        $empty = $this->templateSession($schedule, '2026-10-19');
        $other = Clinic::factory()->create();

        $report = $this->change($schedule, ['clinic_id' => $other->id]);

        $oldHistory = $history->fresh();
        $this->assertSame(SessionStatus::Cancelled, $oldHistory->status);
        $this->assertSame('Jadwal berubah', $oldHistory->status_reason);
        $this->assertNull($oldHistory->doctor_schedule_id);
        $this->assertSame($this->clinic->id, (int) $oldHistory->clinic_id);

        $this->assertModelMissing($empty);
        $fresh = PracticeSession::query()->where('doctor_schedule_id', $schedule->id)->orderBy('session_date')->get();
        $this->assertSame(['2026-10-12', '2026-10-19'], $fresh->map(fn (PracticeSession $s): string => $s->session_date->toDateString())->all());
        $this->assertSame([$other->id, $other->id], $fresh->map(fn (PracticeSession $s): int => (int) $s->clinic_id)->all());
        $this->assertSame(2, $report->regenerated);
        $this->assertSame(1, $report->cancelled);
    }

    // ------------------------------------------------------------------ templat baru

    public function test_created_template_generates_the_window_and_writes_the_audit_row(): void
    {
        $schedule = $this->template(['valid_from' => '2026-10-05', 'valid_until' => null]);

        $report = $this->service->applyTemplateCreated($schedule, $this->actor);

        $dates = PracticeSession::query()->where('doctor_schedule_id', $schedule->id)->orderBy('session_date')->get()
            ->map(fn (PracticeSession $s): string => $s->session_date->toDateString())->all();

        $this->assertSame(['2026-10-05', '2026-10-12', '2026-10-19', '2026-10-26', '2026-11-02'], $dates);
        $this->assertSame(5, $report->regenerated);
        $this->assertSame('5 sesi dibangkitkan.', $report->message());

        $log = $this->auditRows('schedule.created')->sole();
        $this->assertSame($schedule->id, (int) $log->auditable_id);
        $this->assertSame($this->actor->userId, (int) $log->user_id);
        $this->assertNull($log->old_values);
        $this->assertSame(5, $log->new_values['impact']['regenerated']);
        $this->assertSame($this->doctor->id, $log->new_values['doctor_id']);
        $this->assertSame('08:00', $log->new_values['start_time']);
        $this->assertSame(20, $log->new_values['quota']);
    }

    public function test_created_template_of_an_inactive_doctor_generates_nothing(): void
    {
        $inactive = Doctor::factory()->withSpecialties()->inactive()->create(['clinic_id' => $this->clinic->id]);
        $schedule = $this->template(['doctor_id' => $inactive->id, 'valid_from' => '2026-10-05', 'valid_until' => null]);

        $report = $this->service->applyTemplateCreated($schedule, $this->actor);

        $this->assertSame(0, $report->regenerated);
        $this->assertSame(0, PracticeSession::query()->where('doctor_schedule_id', $schedule->id)->count());
        $this->assertSame(1, $this->auditRows('schedule.created')->count());
    }

    // ------------------------------------------------------------------ audit dan atomisitas

    public function test_template_update_is_audited_with_the_changed_fields_and_the_impact(): void
    {
        $schedule = $this->template();
        $this->prepareSession($schedule, 'none');

        $this->change($schedule, ['quota' => 30, 'start_time' => '09:00:00', 'end_time' => '13:00:00']);

        $log = $this->auditRows('schedule.updated')->sole();

        $this->assertSame($schedule->id, (int) $log->auditable_id);
        $this->assertSame($this->actor->userId, (int) $log->user_id);
        $this->assertEquals(['start_time' => '08:00', 'end_time' => '12:00', 'quota' => 20], $log->old_values);
        $this->assertEquals([
            'start_time' => '09:00',
            'end_time' => '13:00',
            'quota' => 30,
            'impact' => ['regenerated' => 1, 'updated' => 0, 'quota_adjusted' => 0, 'needs_review' => 0, 'cancelled' => 0, 'reopened' => 0],
        ], $log->new_values);
    }

    public function test_template_change_is_rolled_back_when_the_audit_row_cannot_be_written(): void
    {
        $schedule = $this->template();
        $session = $this->prepareSession($schedule, 'none');

        AuditLog::creating(fn () => throw new RuntimeException('audit gagal'));

        $thrown = false;

        try {
            $this->change($schedule, ['quota' => 30]);
        } catch (RuntimeException) {
            $thrown = true;
        }

        $this->assertTrue($thrown);
        $this->assertModelExists($session);
        $this->assertSame(1, PracticeSession::query()->where('doctor_schedule_id', $schedule->id)->count());
    }

    // ------------------------------------------------------------------ prioritas pending_change (JDW-17 b, c)

    private function sessionPendingException(DoctorSchedule $schedule): PracticeSession
    {
        $exception = $this->createException([
            'type' => ExceptionType::DoctorLeave,
            'doctor_id' => $this->doctor->id,
            'start_date' => '2026-10-12',
            'end_date' => '2026-10-12',
            'reason' => 'Cuti tahunan',
        ]);
        $session = $this->templateSession($schedule, '2026-10-12', [
            'status' => SessionStatus::NeedsReview,
            'schedule_exception_id' => $exception->id,
            'pending_change' => ['source' => 'exception', 'exception_id' => $exception->id],
        ]);

        foreach (range(1, 2) as $ignored) {
            $this->bookAppointment($session->fresh(), AppointmentStatus::Confirmed);
        }

        return $session->fresh();
    }

    public function test_a_time_change_does_not_overwrite_a_pending_exception_jdw17b(): void
    {
        $schedule = $this->template();
        $session = $this->sessionPendingException($schedule);
        $pending = $session->pending_change;

        $report = $this->change($schedule, ['start_time' => '09:00:00', 'end_time' => '13:00:00']);

        $fresh = $session->fresh();
        $this->assertSame(SessionStatus::NeedsReview, $fresh->status);
        $this->assertSame($pending, $fresh->pending_change);
        $this->assertSame('exception', $fresh->pending_change['source']);
        $this->assertSame((int) $session->schedule_exception_id, (int) $fresh->schedule_exception_id);
        $this->assertSame(0, $this->auditRows('session.pending_replaced')->count());
        $this->assertSame(1, $report->needsReview);
    }

    public function test_a_newer_template_time_replaces_the_older_one_and_is_audited_jdw17c(): void
    {
        $schedule = $this->template();
        $session = $this->prepareSession($schedule, 'active');

        $this->change($schedule, ['start_time' => '09:00:00', 'end_time' => '13:00:00']);
        $this->assertSame(0, $this->auditRows('session.pending_replaced')->count());

        $this->change($schedule, ['start_time' => '10:00:00', 'end_time' => '14:00:00']);

        $fresh = $session->fresh();
        $this->assertSame(SessionStatus::NeedsReview, $fresh->status);
        $this->assertSame(
            ['source' => 'template_time', 'schedule_id' => $schedule->id, 'new_start_time' => '10:00', 'new_end_time' => '14:00'],
            $fresh->pending_change,
        );

        $log = $this->auditRows('session.pending_replaced')->sole();
        $this->assertSame($session->id, (int) $log->auditable_id);
        $this->assertEquals(
            ['pending_change' => ['source' => 'template_time', 'schedule_id' => $schedule->id, 'new_start_time' => '09:00', 'new_end_time' => '13:00']],
            $log->old_values,
        );
        $this->assertEquals(
            ['pending_change' => ['source' => 'template_time', 'schedule_id' => $schedule->id, 'new_start_time' => '10:00', 'new_end_time' => '14:00']],
            $log->new_values,
        );
    }

    public function test_a_cancel_kind_source_replaces_a_time_kind_source_with_an_audit_row(): void
    {
        $schedule = $this->template();
        $session = $this->prepareSession($schedule, 'active');

        $this->change($schedule, ['start_time' => '09:00:00', 'end_time' => '13:00:00']);
        $this->change($schedule, ['is_active' => false]);

        $this->assertSame(['source' => 'template_removed', 'schedule_id' => $schedule->id], $session->fresh()->pending_change);

        $log = $this->auditRows('session.pending_replaced')->sole();
        $this->assertSame('template_time', $log->old_values['pending_change']['source']);
        $this->assertSame('template_removed', $log->new_values['pending_change']['source']);
    }

    public function test_a_time_kind_source_never_overwrites_a_cancel_kind_source(): void
    {
        $schedule = $this->template();
        $session = $this->prepareSession($schedule, 'active');

        $this->change($schedule, ['is_active' => false]);
        $this->change($schedule, ['is_active' => true, 'start_time' => '09:00:00', 'end_time' => '13:00:00']);

        $this->assertSame(['source' => 'template_removed', 'schedule_id' => $schedule->id], $session->fresh()->pending_change);
        $this->assertSame(SessionStatus::NeedsReview, $session->fresh()->status);
        $this->assertSame(0, $this->auditRows('session.pending_replaced')->count());
    }

    public function test_repeating_the_same_pending_change_writes_no_replacement_audit(): void
    {
        $schedule = $this->template();
        $this->prepareSession($schedule, 'active');

        $this->change($schedule, ['start_time' => '09:00:00', 'end_time' => '13:00:00']);
        $this->change($schedule, ['notes' => 'Tidak berpengaruh pada sesi']);

        $this->assertSame(0, $this->auditRows('session.pending_replaced')->count());
        $this->assertSame(1, PracticeSession::query()->where('status', SessionStatus::NeedsReview->value)->count());
    }

    // ------------------------------------------------------------------ hapus templat (JDW-19 a, b; CRD-13)

    public function test_deleting_a_template_removes_its_sessions_without_appointments_and_leaves_no_orphans(): void
    {
        $schedule = $this->template(['valid_until' => '2026-10-25']);
        $sessions = [
            $this->templateSession($schedule, '2026-10-12'),
            $this->templateSession($schedule, '2026-10-19'),
            $this->templateSession($schedule, '2026-09-28'),
        ];
        $other = $this->template(['day_of_week' => 2, 'valid_from' => '2026-10-13', 'valid_until' => '2026-10-13']);
        $otherSession = $this->templateSession($other, '2026-10-13');

        $this->service->deleteTemplate($schedule, $this->actor);

        $this->assertModelMissing($schedule);

        foreach ($sessions as $session) {
            $this->assertModelMissing($session);
        }

        $this->assertModelExists($other);
        $this->assertModelExists($otherSession);
        $this->assertSame(0, PracticeSession::query()->whereNull('doctor_schedule_id')->count());

        $log = $this->auditRows('schedule.deleted')->sole();
        $this->assertSame($schedule->id, (int) $log->auditable_id);
        $this->assertSame(3, $log->new_values['impact']['deleted_sessions']);
        $this->assertSame('08:00', $log->old_values['start_time']);
    }

    public function test_deleting_a_template_is_refused_when_a_session_ever_had_an_appointment(): void
    {
        $message = 'Jadwal ini sudah memiliki sesi dengan pendaftaran dan hanya dapat dinonaktifkan.';

        $cancelledOnly = $this->template();
        $historySession = $this->prepareSession($cancelledOnly, 'cancelled');

        $this->assertRejected(fn () => $this->service->deleteTemplate($cancelledOnly, $this->actor), 'schedule', $message);
        $this->assertModelExists($cancelledOnly);
        $this->assertModelExists($historySession);

        $withPast = $this->template(['day_of_week' => 2, 'valid_from' => '2026-09-29', 'valid_until' => '2026-10-13']);
        $empty = $this->templateSession($withPast, '2026-10-13');
        $pastDone = $this->templateSession($withPast, '2026-09-29');
        $this->bookAppointment($pastDone->fresh(), AppointmentStatus::Completed);

        $this->assertRejected(fn () => $this->service->deleteTemplate($withPast, $this->actor), 'schedule', $message);
        $this->assertModelExists($withPast);
        $this->assertModelExists($empty);
        $this->assertModelExists($pastDone);
        $this->assertSame(0, $this->auditRows('schedule.deleted')->count());
    }
}
