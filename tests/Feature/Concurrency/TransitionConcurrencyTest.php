<?php

namespace Tests\Feature\Concurrency;

use App\Enums\AppointmentStatus;
use App\Exceptions\BookingException;
use App\Exceptions\InvalidTransitionException;
use App\Exceptions\SanitizedBookingFault;
use App\Models\Appointment;
use App\Models\AppointmentStatusHistory;
use App\Models\PracticeSession;
use App\Models\User;
use App\Services\Booking\AppointmentStatusService;
use App\Support\Actor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\Concurrency\ConcurrencyTestCase;

/**
 * KON-05 (dua petugas mentransisi appointment yang sama; tiga varian) dan pembuktian `SYSTEM_BUSY`/pengulangan
 * pada AppointmentStatusService::transition() di luar transaksi pembungkus. Proses anak memakai jam dinding asli
 * (tidak berbagi travelTo). Hanya KON-05a/05b yang butuh jendela check-in: sesinya HARI INI 00.00-23.59, dan kedua
 * tes itu dilewati (markTestSkipped) pada 23.55-00.05 WIB karena jendela/tanggal bisa berganti di tengah balapan.
 * Tes lain memakai sesi besok (pembatalan staf tidak bergantung pada jam).
 */
#[Group('concurrency')]
class TransitionConcurrencyTest extends ConcurrencyTestCase
{
    /**
     * @return array{0: PracticeSession, 1: Appointment}
     */
    private function scenario(int $appointments = 1, bool $needsCheckInWindow = false): array
    {
        if ($needsCheckInWindow) {
            $this->skipAroundMidnight();
        }

        $session = $this->makeSession(5, $needsCheckInWindow ? [
            'session_date' => today()->toDateString(),
            'start_time' => '00:00:00',
            'end_time' => '23:59:00',
        ] : []);

        $first = null;

        for ($i = 0; $i < $appointments; $i++) {
            $created = $this->bookAppointment($session, AppointmentStatus::Confirmed, ['patient_profile_id' => $this->makePatient()->id]);
            $first ??= $created;
        }

        return [$session, $first];
    }

    /** Check-in butuh sesi hari ini pada jam asli proses anak: hindari pergantian hari di tengah balapan. */
    private function skipAroundMidnight(): void
    {
        $minutes = intdiv(now()->setTimezone((string) config('app.timezone'))->secondsSinceMidnight(), 60);

        if ($minutes >= 23 * 60 + 55 || $minutes < 5) {
            $this->markTestSkipped('Dilewati pada 23.55-00.05: jendela check-in sesi hari ini dan tanggal dapat berganti di tengah balapan.');
        }
    }

    /**
     * @param  array<string, scalar>  $arguments
     */
    private function transitionArguments(Appointment $appointment, string $to, User $staff, array $arguments = []): array
    {
        return ['appointment' => $appointment->id, 'to' => $to, 'as' => 'staff', 'actor-user' => $staff->id] + $arguments;
    }

    private function statusChangedAudits(): int
    {
        return $this->rowsSince('audit_logs', 'appointment.status_changed');
    }

    // ------------------------------------------------------------------ KON-05

    public function test_kon_05a_two_staff_racing_with_expected_status_one_wins_and_the_other_is_stale(): void
    {
        [$session, $appointment] = $this->scenario(needsCheckInWindow: true);
        $first = $this->makeStaff();
        $second = $this->makeStaff();
        $startAt = $this->barrier(2);

        $checkIn = $this->spawn('transition', $this->transitionArguments($appointment, 'checked_in', $first, ['expected' => 'confirmed']), $startAt);
        $cancel = $this->spawn('transition', $this->transitionArguments($appointment, 'cancelled', $second, ['expected' => 'confirmed', 'reason' => 'Dibatalkan petugas uji']), $startAt);

        [$checkInResult, $cancelResult] = $this->gather([$checkIn, $cancel]);

        $this->assertNoInfrastructureErrors([$checkInResult, $cancelResult]);
        $this->assertCount(1, $this->successes([$checkInResult, $cancelResult]), 'Tepat satu petugas menang: '.json_encode([$checkInResult, $cancelResult]));

        $loser = $checkInResult['ok'] ? $cancelResult : $checkInResult;
        $this->assertSame('STALE_STATUS', $loser['code']);

        $this->assertFinalStateOfTheRace($appointment, $session, $checkInResult['ok'], $first, $second);
    }

    public function test_kon_05b_two_staff_racing_without_expected_status_get_a_consistent_loser(): void
    {
        [$session, $appointment] = $this->scenario(needsCheckInWindow: true);
        $first = $this->makeStaff();
        $second = $this->makeStaff();
        $startAt = $this->barrier(2);

        $checkIn = $this->spawn('transition', $this->transitionArguments($appointment, 'checked_in', $first), $startAt);
        $cancel = $this->spawn('transition', $this->transitionArguments($appointment, 'cancelled', $second, ['reason' => 'Dibatalkan petugas uji']), $startAt);

        [$checkInResult, $cancelResult] = $this->gather([$checkIn, $cancel]);

        $this->assertNoInfrastructureErrors([$checkInResult, $cancelResult]);
        $this->assertCount(1, $this->successes([$checkInResult, $cancelResult]), 'Tepat satu petugas menang: '.json_encode([$checkInResult, $cancelResult]));

        if ($checkInResult['ok']) {
            // Check-in menang: membatalkan appointment yang sudah check-in tidak sah.
            $this->assertSame('INVALID_TRANSITION', $cancelResult['code']);
        } else {
            // Pembatalan menang: status final, jadi check-in ditolak.
            $this->assertSame('ALREADY_FINAL', $checkInResult['code']);
        }

        $this->assertFinalStateOfTheRace($appointment, $session, $checkInResult['ok'], $first, $second);
    }

    public function test_kon_05c_two_staff_cancelling_the_same_appointment_release_the_seat_only_once(): void
    {
        [$session, $appointment] = $this->scenario(2);
        $first = $this->makeStaff();
        $second = $this->makeStaff();
        $startAt = $this->barrier(2);

        $one = $this->spawn('transition', $this->transitionArguments($appointment, 'cancelled', $first, ['reason' => 'Dibatalkan petugas satu']), $startAt);
        $two = $this->spawn('transition', $this->transitionArguments($appointment, 'cancelled', $second, ['reason' => 'Dibatalkan petugas dua']), $startAt);

        $results = $this->gather([$one, $two]);

        $this->assertNoInfrastructureErrors($results);
        $this->assertCount(1, $this->successes($results));
        $this->assertSame(['ALREADY_FINAL'], $this->failureCodes($results));

        $this->assertSame(AppointmentStatus::Cancelled, $appointment->fresh()->status);
        $this->assertSame(1, (int) PracticeSession::query()->findOrFail($session->id)->booked_count, 'Kuota hanya boleh dilepas satu kali (dua appointment, satu dibatalkan).');
        $this->assertSame(2, (int) PracticeSession::query()->findOrFail($session->id)->last_queue_number);
        $this->assertSame(1, AppointmentStatusHistory::query()->where('appointment_id', $appointment->id)->count());
        $this->assertSame(1, $this->statusChangedAudits());
        $this->assertSessionIsConsistent($session);
    }

    /** Keadaan akhir balapan check-in versus batal: status, kuota, satu riwayat, satu audit, pencatat benar. */
    private function assertFinalStateOfTheRace(Appointment $appointment, PracticeSession $session, bool $checkInWon, User $checkInStaff, User $cancelStaff): void
    {
        $fresh = $appointment->fresh();
        $history = AppointmentStatusHistory::query()->where('appointment_id', $appointment->id)->get();

        $this->assertCount(1, $history, 'Riwayat tidak boleh ganda.');
        $this->assertSame(1, $this->statusChangedAudits());

        if ($checkInWon) {
            $this->assertSame(AppointmentStatus::CheckedIn, $fresh->status);
            $this->assertNotNull($fresh->checked_in_at);
            $this->assertNull($fresh->cancelled_at);
            $this->assertSame(1, (int) PracticeSession::query()->findOrFail($session->id)->booked_count);
            $this->assertSame($checkInStaff->id, (int) $history[0]->actor_user_id);
            $this->assertSame(AppointmentStatus::CheckedIn, $history[0]->to_status);
        } else {
            $this->assertSame(AppointmentStatus::Cancelled, $fresh->status);
            $this->assertNull($fresh->checked_in_at);
            $this->assertSame($cancelStaff->id, (int) $fresh->cancelled_by_user_id);
            $this->assertSame(0, (int) PracticeSession::query()->findOrFail($session->id)->booked_count);
            $this->assertSame($cancelStaff->id, (int) $history[0]->actor_user_id);
            $this->assertSame(AppointmentStatus::Cancelled, $history[0]->to_status);
        }

        $this->assertSessionIsConsistent($session);
    }

    // ------------------------------------------------------------------ SYSTEM_BUSY dan pengulangan pada transition()

    public function test_transition_system_busy_when_the_appointment_row_stays_locked_past_three_attempts(): void
    {
        [$session, $appointment] = $this->scenario();
        $staff = $this->makeStaff();
        $holder = $this->openConnection(1);
        $holder->beginTransaction();
        $this->lockRow($holder, 'appointments', $appointment->id);
        DB::statement('SET SESSION innodb_lock_wait_timeout = 1');
        $service = $this->app->make(AppointmentStatusService::class);

        $startedAt = microtime(true);

        try {
            $service->transition($appointment, AppointmentStatus::Cancelled, Actor::staff($staff), 'Dibatalkan petugas uji');
            $this->fail('BookingException SYSTEM_BUSY tidak dilempar.');
        } catch (BookingException $e) {
            $this->assertNotInstanceOf(InvalidTransitionException::class, $e);
            $this->assertSame('SYSTEM_BUSY', $e->errorCode);
            $this->assertSame('Sistem sedang sibuk, silakan coba lagi beberapa saat.', $e->getMessage());
        }

        $this->assertGreaterThanOrEqual(2.0, microtime(true) - $startedAt, 'Tiga percobaan x timeout 1 detik.');

        $fresh = $appointment->fresh();
        $this->assertSame(AppointmentStatus::Confirmed, $fresh->status);
        $this->assertSame(1, (int) PracticeSession::query()->findOrFail($session->id)->booked_count);
        $this->assertSame(0, $this->rowsSince('appointment_status_histories'));
        $this->assertSame(0, $this->statusChangedAudits());

        $holder->rollBack();
        $service->transition($appointment, AppointmentStatus::Cancelled, Actor::staff($staff), 'Dibatalkan petugas uji');

        $this->assertSame(AppointmentStatus::Cancelled, $appointment->fresh()->status);
        $this->assertSame(0, (int) PracticeSession::query()->findOrFail($session->id)->booked_count);
    }

    public function test_transition_retries_a_transient_deadlock_and_gives_up_after_three_attempts(): void
    {
        [$session, $appointment] = $this->scenario(2);
        $staff = $this->makeStaff();
        $service = $this->app->make(AppointmentStatusService::class);

        $retry = $this->injectFault(
            'update `appointments`',
            1213,
            '40001',
            'SQLSTATE[40001]: Serialization failure: 1213 Deadlock found when trying to get lock; try restarting transaction',
            2,
        );

        $service->transition($appointment, AppointmentStatus::Cancelled, Actor::staff($staff), 'Dibatalkan petugas uji');

        $this->assertSame(3, $retry->hits, 'Dua kegagalan lalu sukses pada percobaan ketiga.');
        $this->assertSame(AppointmentStatus::Cancelled, $appointment->fresh()->status);
        $this->assertSame(1, (int) PracticeSession::query()->findOrFail($session->id)->booked_count, 'Kuota dilepas tepat satu kali walau diulang.');
        $this->assertSame(1, AppointmentStatusHistory::query()->where('appointment_id', $appointment->id)->count());
        $this->assertSame(1, $this->statusChangedAudits());
    }

    public function test_transition_with_a_persistent_deadlock_becomes_system_busy_without_partial_writes(): void
    {
        [$session, $appointment] = $this->scenario();
        $staff = $this->makeStaff();
        $service = $this->app->make(AppointmentStatusService::class);

        $fault = $this->injectFault(
            'update `appointments`',
            1213,
            '40001',
            'SQLSTATE[40001]: Serialization failure: 1213 Deadlock found when trying to get lock; try restarting transaction',
        );
        Exceptions::fake();

        try {
            $service->transition($appointment, AppointmentStatus::Cancelled, Actor::staff($staff), 'Dibatalkan petugas uji');
            $this->fail('BookingException SYSTEM_BUSY tidak dilempar.');
        } catch (BookingException $e) {
            $this->assertSame('SYSTEM_BUSY', $e->errorCode);
        }

        // FX1: SYSTEM_BUSY pada pembatalan/transisi tercatat di log (tersanitasi, tanpa bindings).
        Exceptions::assertReportedCount(1);
        Exceptions::assertReported(fn (SanitizedBookingFault $e): bool => str_contains($e->getMessage(), 'transisi status')
            && str_contains($e->getMessage(), 'driver=1213')
            && str_contains($e->getMessage(), 'sesi='.$session->id)
            && ! str_contains($e->getMessage(), 'update `appointments`')
            && $e->getPrevious() === null);

        $this->assertSame(3, $fault->hits);
        $this->assertSame(AppointmentStatus::Confirmed, $appointment->fresh()->status);
        $this->assertSame(1, (int) PracticeSession::query()->findOrFail($session->id)->booked_count);
        $this->assertSame(0, $this->rowsSince('appointment_status_histories'));
        $this->assertSame(0, $this->statusChangedAudits());
    }
}
