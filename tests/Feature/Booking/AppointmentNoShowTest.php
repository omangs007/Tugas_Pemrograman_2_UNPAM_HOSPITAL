<?php

namespace Tests\Feature\Booking;

use App\Enums\ActorType;
use App\Enums\AppointmentStatus;
use App\Exceptions\BookingException;
use App\Exceptions\InvalidTransitionException;
use App\Models\Appointment;
use App\Models\AppointmentStatusHistory;
use App\Models\AuditLog;
use App\Models\PatientProfile;
use App\Models\PracticeSession;
use App\Models\User;
use App\Notifications\AppointmentStatusChangedNotification;
use App\Services\Booking\AppointmentStatusService;
use App\Support\Actor;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InteractsWithSchedules;
use Tests\TestCase;

/**
 * F7A-R6, BKG-16, Review Focus E5: tandai tidak hadir per sesi dan per tanggal. Waktu dibekukan dengan travelTo():
 * "sekarang" awal = Selasa 2026-10-06 12.00.01 WIB, satu detik sesudah sesi uji (2026-10-06 09.00-12.00, kuota 20).
 * Transaksi tes membungkus service (savepoint): pembuktian SYSTEM_BUSY, retry, dan balapan nyata milik T11.
 */
class AppointmentNoShowTest extends TestCase
{
    use InteractsWithSchedules;
    use RefreshDatabase;

    private AppointmentStatusService $service;

    private PracticeSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-10-06 12:00:01'));
        $this->seed(SettingsSeeder::class);

        $this->service = $this->app->make(AppointmentStatusService::class);
        $this->session = $this->makeSession();
    }

    // ------------------------------------------------------------------ pembantu

    private function at(string $moment): void
    {
        $this->travelTo(CarbonImmutable::parse($moment));
    }

    /**
     * Sesi tambahan manual (default 2026-10-06 09.00-12.00, kuota 20) dalam status `open`, `closed`, `cancelled`,
     * atau `needs_review`.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function makeSession(array $overrides = [], string $state = 'open'): PracticeSession
    {
        $factory = PracticeSession::factory()->manual();
        $factory = match ($state) {
            'closed' => $factory->closed(),
            'cancelled' => $factory->cancelled(),
            'needs_review' => $factory->needsReview(['source' => PracticeSession::SOURCE_EXCEPTION, 'exception_id' => 1]),
            default => $factory,
        };

        return $factory->create($overrides + [
            'session_date' => '2026-10-06',
            'start_time' => '09:00:00',
            'end_time' => '12:00:00',
            'quota' => 20,
        ]);
    }

    /** Appointment pada sesi (bawaan sesi uji); pasien berakun kecuali $withAccount = false (walk-in). */
    private function appointment(AppointmentStatus $status = AppointmentStatus::Confirmed, bool $withAccount = true, ?PracticeSession $session = null): Appointment
    {
        $profile = $withAccount
            ? PatientProfile::factory()->withAccount()->create()
            : PatientProfile::factory()->create();

        return $this->bookAppointment($session ?? $this->session, $status, ['patient_profile_id' => $profile->id]);
    }

    /**
     * Sesi dengan appointment berstatus tertentu (satu per elemen).
     *
     * @param  list<AppointmentStatus>  $statuses
     * @param  array<string, mixed>  $overrides
     */
    private function sessionWith(array $statuses, array $overrides = [], string $state = 'open'): PracticeSession
    {
        $session = $this->makeSession($overrides, $state);

        foreach ($statuses as $status) {
            $this->appointment($status, true, $session);
        }

        return $session;
    }

    private function failure(callable $call): BookingException
    {
        try {
            $call();
        } catch (BookingException $e) {
            return $e;
        }

        $this->fail('BookingException tidak dilempar.');
    }

    private function assertFails(string $code, callable $call): InvalidTransitionException
    {
        $e = $this->failure($call);

        $this->assertInstanceOf(InvalidTransitionException::class, $e);
        $this->assertSame($code, $e->errorCode);

        return $e;
    }

    private function statusOf(Appointment $appointment): AppointmentStatus
    {
        return $appointment->fresh()->status;
    }

    private function historyCount(?Appointment $appointment = null): int
    {
        $query = AppointmentStatusHistory::query();

        return $appointment === null ? $query->count() : $query->where('appointment_id', $appointment->id)->count();
    }

    // ------------------------------------------------------------------ BKG-16: efek per appointment

    public function test_bkg_16_staff_marks_pending_and_confirmed_with_the_right_actor_history_and_audit_per_appointment(): void
    {
        $pending = $this->appointment(AppointmentStatus::Pending);
        $confirmed = $this->appointment(AppointmentStatus::Confirmed);
        $staff = User::factory()->staff()->create();

        $count = $this->service->markNoShowForSession($this->session, Actor::staff($staff), 'Pasien tidak datang sampai sesi berakhir');

        $this->assertSame(2, $count);

        foreach ([[$pending, AppointmentStatus::Pending], [$confirmed, AppointmentStatus::Confirmed]] as [$appointment, $from]) {
            $fresh = $appointment->fresh();
            $this->assertSame(AppointmentStatus::NoShow, $fresh->status);
            $this->assertSame('2026-10-06 12:00:01', $fresh->no_show_at->toDateTimeString());
            $this->assertNull($fresh->active_flag);
            $this->assertNull($fresh->cancelled_at);

            $rows = AppointmentStatusHistory::query()->where('appointment_id', $appointment->id)->get();
            $this->assertCount(1, $rows);
            $this->assertSame($from, $rows[0]->from_status);
            $this->assertSame(AppointmentStatus::NoShow, $rows[0]->to_status);
            $this->assertSame(ActorType::Staff, $rows[0]->actor_type);
            $this->assertSame($staff->id, (int) $rows[0]->actor_user_id);
            $this->assertSame('Pasien tidak datang sampai sesi berakhir', $rows[0]->reason);
        }

        $logs = AuditLog::query()->where('action', 'appointment.status_changed')->orderBy('id')->get();
        $this->assertCount(2, $logs);

        $oldStatus = [$pending->id => 'pending', $confirmed->id => 'confirmed'];

        foreach ($logs as $log) {
            $this->assertSame(ActorType::Staff, $log->actor_type);
            $this->assertSame($staff->id, (int) $log->user_id);
            $this->assertSame($pending->getMorphClass(), $log->auditable_type);
            $this->assertSame(['status' => $oldStatus[(int) $log->auditable_id]], $log->old_values);
            $this->assertSame(['status' => 'no_show', 'reason' => 'Pasien tidak datang sampai sesi berakhir'], $log->new_values);
        }

        $this->assertEqualsCanonicalizing(array_keys($oldStatus), $logs->map(fn (AuditLog $log): int => (int) $log->auditable_id)->all());
    }

    public function test_the_system_actor_leaves_no_user_and_no_reason_in_history_and_audit(): void
    {
        $appointment = $this->appointment(AppointmentStatus::Confirmed);

        $count = $this->service->markNoShowForSession($this->session, Actor::system());

        $this->assertSame(1, $count);

        $row = AppointmentStatusHistory::query()->where('appointment_id', $appointment->id)->sole();
        $this->assertSame(ActorType::System, $row->actor_type);
        $this->assertNull($row->actor_user_id);
        $this->assertNull($row->reason);

        $log = AuditLog::query()->where('action', 'appointment.status_changed')->sole();
        $this->assertSame(ActorType::System, $log->actor_type);
        $this->assertNull($log->user_id);
        $this->assertSame(['status' => 'no_show'], $log->new_values);
    }

    public function test_only_pending_and_confirmed_are_marked_and_the_count_is_honest(): void
    {
        $pending = $this->appointment(AppointmentStatus::Pending);
        $checkedIn = $this->appointment(AppointmentStatus::CheckedIn);
        $completed = $this->appointment(AppointmentStatus::Completed);
        $cancelled = $this->appointment(AppointmentStatus::Cancelled);

        $count = $this->service->markNoShowForSession($this->session, Actor::system());

        $this->assertSame(1, $count);
        $this->assertSame(AppointmentStatus::NoShow, $this->statusOf($pending));
        $this->assertSame(AppointmentStatus::CheckedIn, $this->statusOf($checkedIn));
        $this->assertSame(AppointmentStatus::Completed, $this->statusOf($completed));
        $this->assertSame(AppointmentStatus::Cancelled, $this->statusOf($cancelled));
        $this->assertSame(1, $this->historyCount());
        $this->assertSame(0, $this->historyCount($checkedIn));
    }

    public function test_an_ended_session_without_pending_or_confirmed_appointments_returns_zero_without_error(): void
    {
        $this->appointment(AppointmentStatus::Completed);
        $this->appointment(AppointmentStatus::CheckedIn);

        $this->assertSame(0, $this->service->markNoShowForSession($this->session, Actor::system()));
        $this->assertSame(0, $this->historyCount());
    }

    public function test_a_closed_session_that_has_ended_is_processed_like_an_open_one(): void
    {
        $session = $this->sessionWith([AppointmentStatus::Confirmed], [], 'closed');

        $this->assertSame(1, $this->service->markNoShowForSession($session, Actor::system()));
        $this->assertSame(1, AppointmentStatusHistory::query()->where('to_status', 'no_show')->count());
    }

    public function test_no_show_keeps_booked_count_and_last_queue_number(): void
    {
        $this->appointment(AppointmentStatus::Pending);
        $this->appointment(AppointmentStatus::Confirmed);
        $this->appointment(AppointmentStatus::CheckedIn);

        $this->service->markNoShowForSession($this->session, Actor::system());

        $fresh = PracticeSession::query()->findOrFail($this->session->id);
        $this->assertSame(3, $fresh->booked_count);
        $this->assertSame(3, $fresh->last_queue_number);
    }

    public function test_the_second_call_is_idempotent_and_writes_nothing_more(): void
    {
        $this->appointment(AppointmentStatus::Pending);
        $this->appointment(AppointmentStatus::Confirmed);

        $this->assertSame(2, $this->service->markNoShowForSession($this->session, Actor::system()));

        $audits = AuditLog::query()->count();

        $this->assertSame(0, $this->service->markNoShowForSession($this->session, Actor::system()));
        $this->assertSame(2, $this->historyCount());
        $this->assertSame($audits, AuditLog::query()->count());
        $this->assertSame(2, Appointment::query()->where('status', 'no_show')->count());
    }

    public function test_the_reason_is_trimmed_and_blank_becomes_null(): void
    {
        $appointment = $this->appointment(AppointmentStatus::Confirmed);

        $this->assertSame(1, $this->service->markNoShowForSession($this->session, Actor::staff(User::factory()->staff()->create()), '   '));

        $this->assertNull(AppointmentStatusHistory::query()->where('appointment_id', $appointment->id)->value('reason'));
    }

    // ------------------------------------------------------------------ E5: batas waktu

    public function test_e5_marking_is_rejected_until_after_the_exact_end_and_accepted_one_second_later(): void
    {
        $appointment = $this->appointment(AppointmentStatus::Confirmed);
        $staff = Actor::staff(User::factory()->staff()->create());

        foreach (['2026-10-06 11:59:59', '2026-10-06 12:00:00'] as $moment) {
            $this->at($moment);

            $e = $this->assertFails('SESSION_NOT_ENDED', fn () => $this->service->markNoShowForSession($this->session, $staff));

            $this->assertSame('Tandai tidak hadir hanya dapat dilakukan setelah sesi berakhir (pukul 12.00).', $e->getMessage());
            $this->assertSame(['practice_session_id' => $this->session->id], $e->context);
            $this->assertSame(AppointmentStatus::Confirmed, $this->statusOf($appointment), $moment);
            $this->assertSame(0, $this->historyCount(), $moment);
        }

        $this->at('2026-10-06 12:00:01');

        $this->assertSame(1, $this->service->markNoShowForSession($this->session, $staff));
        $this->assertSame(AppointmentStatus::NoShow, $this->statusOf($appointment));
    }

    public function test_a_session_on_a_later_day_has_not_ended(): void
    {
        $session = $this->sessionWith([AppointmentStatus::Confirmed], ['session_date' => '2026-10-07']);

        $this->assertFails('SESSION_NOT_ENDED', fn () => $this->service->markNoShowForSession($session, Actor::system()));
        $this->assertSame(0, $this->historyCount());
    }

    // ------------------------------------------------------------------ E5: sesi needs_review

    public function test_e5_a_needs_review_session_is_rejected_with_the_review_message_even_after_it_ended(): void
    {
        $session = $this->sessionWith([AppointmentStatus::Confirmed, AppointmentStatus::Pending], ['session_date' => '2026-10-05'], 'needs_review');

        $e = $this->assertFails('INVALID_TRANSITION', fn () => $this->service->markNoShowForSession($session, Actor::staff(User::factory()->staff()->create())));

        $this->assertSame('Sesi ini sedang menunggu penyelesaian di layar Sesi terdampak.', $e->getMessage());
        $this->assertSame(['practice_session_id' => $session->id], $e->context);
        $this->assertSame(0, Appointment::query()->where('status', 'no_show')->count());
        $this->assertSame(0, $this->historyCount());
    }

    public function test_the_review_check_comes_before_the_time_check(): void
    {
        $session = $this->sessionWith([AppointmentStatus::Confirmed], ['session_date' => '2026-10-07'], 'needs_review');

        $e = $this->assertFails('INVALID_TRANSITION', fn () => $this->service->markNoShowForSession($session, Actor::system()));

        $this->assertSame('Sesi ini sedang menunggu penyelesaian di layar Sesi terdampak.', $e->getMessage());
    }

    public function test_a_stale_session_instance_does_not_hide_a_needs_review_status(): void
    {
        $appointment = $this->appointment(AppointmentStatus::Confirmed);
        $stale = PracticeSession::query()->findOrFail($this->session->id);

        PracticeSession::query()->whereKey($this->session->id)->toBase()->update([
            'status' => 'needs_review',
            'pending_change' => json_encode(['source' => 'exception', 'exception_id' => 1]),
        ]);

        $this->assertFails('INVALID_TRANSITION', fn () => $this->service->markNoShowForSession($stale, Actor::system()));
        $this->assertSame(AppointmentStatus::Confirmed, $this->statusOf($appointment));
    }

    // ------------------------------------------------------------------ perubahan oleh pihak lain di tengah proses

    /** @return array<string, array{0: array<string, string>, 1: AppointmentStatus}> */
    public static function changedByAnotherUser(): array
    {
        return [
            'check-in oleh petugas lain (INVALID_TRANSITION)' => [['status' => 'checked_in', 'checked_in_at' => '2026-10-06 11:00:00'], AppointmentStatus::CheckedIn],
            'sudah ditandai oleh petugas lain (ALREADY_FINAL)' => [['status' => 'no_show', 'no_show_at' => '2026-10-06 12:00:00'], AppointmentStatus::NoShow],
        ];
    }

    /** @param array<string, string> $change */
    #[DataProvider('changedByAnotherUser')]
    public function test_an_appointment_changed_by_someone_else_in_the_meantime_is_skipped_without_error_and_not_counted(array $change, AppointmentStatus $expectedStatus): void
    {
        $first = $this->appointment(AppointmentStatus::Confirmed);
        $second = $this->appointment(AppointmentStatus::Confirmed);
        $firstProfileId = (int) $first->patient_profile_id;
        $secondId = $second->id;
        $fired = false;

        // Saat transaksi appointment PERTAMA mengunci profilnya (transaksinya berhasil dan dilepas), "petugas lain" mengubah
        // appointment kedua; jangan memicu di dalam transaksi kedua sendiri: savepoint-nya digulung dan perubahan ikut hilang.
        DB::connection()->beforeExecuting(function (string $query, array $bindings) use (&$fired, $firstProfileId, $secondId, $change): void {
            $sql = strtolower($query);

            if ($fired || ! str_contains($sql, 'from `patient_profiles`') || ! str_contains($sql, 'for update') || (int) ($bindings[0] ?? 0) !== $firstProfileId) {
                return;
            }

            $fired = true;
            DB::table('appointments')->where('id', $secondId)->update($change);
        });

        $count = $this->service->markNoShowForSession($this->session, Actor::system());

        $this->assertTrue($fired, 'Penyisipan perubahan pihak lain tidak terpicu.');
        $this->assertSame(1, $count);
        $this->assertSame(AppointmentStatus::NoShow, $this->statusOf($first));
        $this->assertSame($expectedStatus, $this->statusOf($second));
        $this->assertSame(1, $this->historyCount($first));
        $this->assertSame(0, $this->historyCount($second));
    }

    // ------------------------------------------------------------------ notifikasi (NTF-03, NTF-07)

    public function test_a_patient_with_an_account_gets_a_database_only_notification_and_a_walk_in_gets_none(): void
    {
        $withAccount = $this->appointment(AppointmentStatus::Confirmed);
        $walkIn = $this->appointment(AppointmentStatus::Pending, false);
        $recipient = User::query()->findOrFail(PatientProfile::query()->whereKey($withAccount->patient_profile_id)->value('user_id'));
        Notification::fake();

        $count = $this->service->markNoShowForSession($this->session, Actor::system());

        $this->assertSame(2, $count);
        $this->assertSame(AppointmentStatus::NoShow, $this->statusOf($walkIn));

        Notification::assertSentToTimes($recipient, AppointmentStatusChangedNotification::class, 1);
        Notification::assertSentTo(
            $recipient,
            AppointmentStatusChangedNotification::class,
            fn (AppointmentStatusChangedNotification $notification, array $channels): bool => $channels === ['database']
                && $notification->from === AppointmentStatus::Confirmed
                && $notification->to === AppointmentStatus::NoShow,
        );
        Notification::assertCount(1);
    }

    public function test_the_stored_notification_has_the_no_show_title_and_no_sensitive_data_and_the_walk_in_creates_no_row(): void
    {
        $withAccount = $this->appointment(AppointmentStatus::Confirmed);
        $this->appointment(AppointmentStatus::Pending, false);
        $recipient = User::query()->findOrFail(PatientProfile::query()->whereKey($withAccount->patient_profile_id)->value('user_id'));

        $this->service->markNoShowForSession($this->session, Actor::system());

        $this->assertSame(1, DB::table('notifications')->count());

        $data = $recipient->notifications()->sole()->data;
        $this->assertSame('no_show', $data['status']);
        $this->assertSame('Ditandai tidak hadir', $data['title']);
        $this->assertSame($withAccount->booking_code, $data['booking_code']);
    }

    // ------------------------------------------------------------------ sessionsAwaitingNoShow

    public function test_sessions_awaiting_no_show_lists_ended_open_and_closed_sessions_with_pending_or_confirmed_in_order(): void
    {
        $a = $this->sessionWith([AppointmentStatus::Confirmed], ['session_date' => '2026-10-05', 'start_time' => '13:00:00', 'end_time' => '16:00:00']);
        $h = $this->sessionWith([AppointmentStatus::Confirmed], ['start_time' => '06:00:00', 'end_time' => '08:00:00'], 'closed');
        $c = $this->sessionWith([AppointmentStatus::Pending], ['start_time' => '07:00:00', 'end_time' => '10:00:00']);
        $b = $this->session;
        $this->appointment(AppointmentStatus::Pending);

        // Tidak masuk: belum berakhir, tanggal depan, needs_review, cancelled, hanya status lain, tanpa appointment.
        $this->sessionWith([AppointmentStatus::Confirmed], ['start_time' => '13:00:00', 'end_time' => '15:00:00']);
        $this->sessionWith([AppointmentStatus::Confirmed], ['session_date' => '2026-10-07']);
        $this->sessionWith([AppointmentStatus::Confirmed], ['session_date' => '2026-10-05'], 'needs_review');
        $this->sessionWith([AppointmentStatus::Confirmed], ['session_date' => '2026-10-05'], 'cancelled');
        $this->sessionWith([AppointmentStatus::CheckedIn, AppointmentStatus::Completed, AppointmentStatus::Cancelled, AppointmentStatus::NoShow], ['session_date' => '2026-10-05']);
        $this->makeSession(['session_date' => '2026-10-05']);

        $sessions = $this->service->sessionsAwaitingNoShow();

        $this->assertSame([$a->id, $h->id, $c->id, $b->id], $sessions->map(fn (PracticeSession $session): int => (int) $session->id)->all());
        $this->assertTrue($sessions->first()->relationLoaded('clinic'));
        $this->assertTrue($sessions->first()->relationLoaded('doctor'));
    }

    public function test_sessions_awaiting_no_show_can_be_limited_to_one_date(): void
    {
        $yesterday = $this->sessionWith([AppointmentStatus::Confirmed], ['session_date' => '2026-10-05']);
        $today = $this->sessionWith([AppointmentStatus::Pending]);

        $this->assertSame([$today->id], $this->service->sessionsAwaitingNoShow(CarbonImmutable::parse('2026-10-06'))->pluck('id')->map(fn ($id): int => (int) $id)->all());
        $this->assertSame([$yesterday->id], $this->service->sessionsAwaitingNoShow(Carbon::parse('2026-10-05'))->pluck('id')->map(fn ($id): int => (int) $id)->all());
        $this->assertCount(0, $this->service->sessionsAwaitingNoShow(CarbonImmutable::parse('2026-10-04')));
    }

    public function test_sessions_awaiting_no_show_uses_a_strict_end_boundary_and_honours_the_now_argument(): void
    {
        $session = $this->sessionWith([AppointmentStatus::Confirmed]);

        $this->at('2026-10-06 12:00:00');
        $this->assertCount(0, $this->service->sessionsAwaitingNoShow());

        $this->at('2026-10-06 12:00:01');
        $this->assertSame([$session->id], $this->service->sessionsAwaitingNoShow()->map(fn (PracticeSession $found): int => (int) $found->id)->all());

        // Parameter $now menggantikan jam dinding (kini 11.00): tepat 12.00.00 belum berakhir, 12.00.01 sudah
        // (05.00.01 UTC = 12.00.01 WIB, dibaca dalam zona aplikasi).
        $this->at('2026-10-06 11:00:00');
        $this->assertCount(0, $this->service->sessionsAwaitingNoShow());
        $this->assertCount(0, $this->service->sessionsAwaitingNoShow(null, CarbonImmutable::parse('2026-10-06 12:00:00')));
        $this->assertCount(1, $this->service->sessionsAwaitingNoShow(null, CarbonImmutable::parse('2026-10-06 12:00:01')));
        $this->assertCount(1, $this->service->sessionsAwaitingNoShow(null, CarbonImmutable::parse('2026-10-06 05:00:01', 'UTC')));
    }

    // ------------------------------------------------------------------ pendingNoShowCount

    public function test_pending_no_show_count_counts_only_pending_and_confirmed_of_that_session_regardless_of_time(): void
    {
        $this->appointment(AppointmentStatus::Pending);
        $this->appointment(AppointmentStatus::Confirmed);
        $this->appointment(AppointmentStatus::CheckedIn);
        $this->appointment(AppointmentStatus::Completed);
        $this->appointment(AppointmentStatus::Cancelled);
        $this->appointment(AppointmentStatus::NoShow);
        $other = $this->sessionWith([AppointmentStatus::Confirmed, AppointmentStatus::Confirmed]);
        $future = $this->sessionWith([AppointmentStatus::Pending], ['session_date' => '2026-10-07']);
        $review = $this->sessionWith([AppointmentStatus::Confirmed], ['session_date' => '2026-10-05'], 'needs_review');

        $this->assertSame(2, $this->service->pendingNoShowCount($this->session));
        $this->assertSame(2, $this->service->pendingNoShowCount($other));
        $this->assertSame(1, $this->service->pendingNoShowCount($future));
        $this->assertSame(1, $this->service->pendingNoShowCount($review));

        $this->service->markNoShowForSession($this->session, Actor::system());

        $this->assertSame(0, $this->service->pendingNoShowCount($this->session));
    }

    // ------------------------------------------------------------------ markNoShowForDate

    public function test_mark_no_show_for_date_processes_ended_sessions_of_that_date_or_all_and_skips_review_and_running_sessions(): void
    {
        $yesterday = $this->sessionWith([AppointmentStatus::Pending, AppointmentStatus::Confirmed], ['session_date' => '2026-10-05']);
        $today = $this->sessionWith([AppointmentStatus::Confirmed, AppointmentStatus::CheckedIn]);
        $running = $this->sessionWith([AppointmentStatus::Confirmed], ['start_time' => '13:00:00', 'end_time' => '15:00:00']);
        $review = $this->sessionWith([AppointmentStatus::Confirmed], ['session_date' => '2026-10-05'], 'needs_review');
        $system = Actor::system();

        $this->assertSame(1, $this->service->markNoShowForDate(CarbonImmutable::parse('2026-10-06'), $system));
        $this->assertSame(1, Appointment::query()->where('practice_session_id', $today->id)->where('status', 'no_show')->count());
        $this->assertSame(0, Appointment::query()->where('practice_session_id', $yesterday->id)->where('status', 'no_show')->count());

        $this->assertSame(2, $this->service->markNoShowForDate(null, $system));
        $this->assertSame(2, Appointment::query()->where('practice_session_id', $yesterday->id)->where('status', 'no_show')->count());

        $this->assertSame(0, $this->service->markNoShowForDate(null, $system));

        $this->assertSame(0, Appointment::query()->where('practice_session_id', $running->id)->where('status', 'no_show')->count());
        $this->assertSame(0, Appointment::query()->where('practice_session_id', $review->id)->where('status', 'no_show')->count());
        $this->assertSame(3, AppointmentStatusHistory::query()->where('to_status', 'no_show')->where('actor_type', 'system')->count());
    }

    // ------------------------------------------------------------------ teks lang

    public function test_the_no_show_texts_are_the_documented_ones(): void
    {
        $this->assertSame('3 pendaftaran ditandai tidak hadir.', __('booking.no_show.done', ['count' => 3]));
        $this->assertSame('Tidak ada pendaftaran yang perlu ditandai tidak hadir pada sesi ini.', __('booking.no_show.nothing'));
        $this->assertSame('Centang konfirmasi untuk menandai seluruh pendaftaran yang belum hadir sebagai tidak hadir.', __('booking.no_show.confirm_required'));
        $this->assertSame('Sesi ini sedang menunggu penyelesaian di layar Sesi terdampak.', __('booking.transitions.SESSION_UNDER_REVIEW'));
    }
}
