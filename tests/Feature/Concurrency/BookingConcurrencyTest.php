<?php

namespace Tests\Feature\Concurrency;

use App\Enums\PaymentType;
use App\Exceptions\BookingException;
use App\Exceptions\SanitizedBookingFault;
use App\Models\Appointment;
use App\Models\PatientProfile;
use App\Models\PracticeSession;
use App\Models\User;
use App\Notifications\AppointmentStatusChangedNotification;
use App\Services\Booking\BookingData;
use App\Services\Booking\BookingService;
use App\Support\Actor;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\Concurrency\ConcurrencyTestCase;

/**
 * KON-01a-e (dua koneksi: PDO mentah A menahan kunci, proses anak B menjalankan BookingService::book()),
 * BKG-21 (= KON-01c, SYSTEM_BUSY) dan BKG-18 (galat driver tersimulasi di tengah book(), tanpa transaksi
 * pembungkus agar `attempts: 3` benar-benar mengulang). Tes ini ikut suite bawaan; `--exclude-group=concurrency`
 * melewatinya untuk putaran cepat.
 */
#[Group('concurrency')]
class BookingConcurrencyTest extends ConcurrencyTestCase
{
    private function service(): BookingService
    {
        return $this->app->make(BookingService::class);
    }

    private function actorOf(PatientProfile $profile): Actor
    {
        return Actor::patient(User::query()->findOrFail($profile->user_id));
    }

    private function attemptBooking(PatientProfile $profile, int $sessionId): Appointment
    {
        return $this->service()->book($profile, $sessionId, new BookingData(PaymentType::Umum), $this->actorOf($profile));
    }

    private function bookingFailure(PatientProfile $profile, int $sessionId): BookingException
    {
        try {
            $this->attemptBooking($profile, $sessionId);
        } catch (BookingException $e) {
            return $e;
        }

        $this->fail('BookingException tidak dilempar.');
    }

    /** Tidak ada appointment, riwayat, audit `appointment.created`, dan pencacah sesi tetap 0. */
    private function assertNoPartialWrites(PracticeSession $session): void
    {
        $fresh = PracticeSession::query()->findOrFail($session->id);

        $this->assertSame(0, (int) $fresh->booked_count);
        $this->assertSame(0, (int) $fresh->last_queue_number);
        $this->assertSame(0, Appointment::query()->where('practice_session_id', $session->id)->count());
        $this->assertSame(0, $this->rowsSince('appointment_status_histories'));
        $this->assertSame(0, $this->rowsSince('audit_logs', 'appointment.created'));
    }

    // ------------------------------------------------------------------ KON-01: dua koneksi

    public function test_kon_01a_a_waiting_booking_gets_quota_full_after_the_lock_holder_takes_the_last_seat(): void
    {
        $session = $this->makeSession(1);
        $holder = $this->makePatient();
        $waiter = $this->makePatient();
        $a = $this->openConnection(1);
        $startAt = $this->barrier();

        $locked = $this->beginAndLock($a, $holder->id, $session->id);
        $process = $this->spawn('book', ['patient' => $waiter->id, 'session' => $session->id], $startAt);

        $this->waitUntil($startAt + 1.5);
        $this->assertTrue($process->isRunning(), 'Proses B harus masih menunggu kunci sesi yang ditahan A.');

        $this->rawBook($a, $holder->id, $locked);
        $releaseAt = microtime(true);
        $a->commit();

        $result = $this->gather([$process])[0];

        $this->assertFalse($result['ok'], json_encode($result));
        $this->assertSame('QUOTA_FULL', $result['code']);
        $this->assertLessThan($releaseAt, $result['begin'], 'Proses B terlambat boot: naikkan BARRIER_BASE.');
        $this->assertGreaterThanOrEqual($releaseAt - 0.05, $result['end'], 'B selesai sebelum A melepas kunci: kunci sesi tidak menyerialisasi.');

        $this->assertSame(1, Appointment::query()->where('practice_session_id', $session->id)->count());
        $this->assertSame($holder->id, (int) Appointment::query()->where('practice_session_id', $session->id)->value('patient_profile_id'));
        $this->assertSessionIsConsistent($session);
    }

    public function test_kon_01b_a_waiting_booking_succeeds_when_the_lock_holder_rolls_back(): void
    {
        $session = $this->makeSession(1);
        $holder = $this->makePatient();
        $waiter = $this->makePatient();
        $a = $this->openConnection(1);
        $startAt = $this->barrier();

        $locked = $this->beginAndLock($a, $holder->id, $session->id);
        $process = $this->spawn('book', ['patient' => $waiter->id, 'session' => $session->id], $startAt);

        $this->waitUntil($startAt + 1.5);
        $this->assertTrue($process->isRunning(), 'Proses B harus masih menunggu kunci sesi yang ditahan A.');

        $this->rawBook($a, $holder->id, $locked);
        $releaseAt = microtime(true);
        $a->rollBack();

        $result = $this->gather([$process])[0];

        $this->assertTrue($result['ok'], json_encode($result));
        $this->assertSame(1, $result['queue']);
        $this->assertLessThan($releaseAt, $result['begin'], 'Proses B terlambat boot: naikkan BARRIER_BASE.');
        $this->assertGreaterThanOrEqual($releaseAt - 0.05, $result['end'], 'B selesai sebelum A melepas kunci.');

        $this->assertSame($waiter->id, (int) Appointment::query()->where('practice_session_id', $session->id)->value('patient_profile_id'));
        $this->assertSessionIsConsistent($session);
        $this->assertSame(1, (int) PracticeSession::query()->findOrFail($session->id)->booked_count);
    }

    public function test_kon_01d_the_patient_lock_is_the_first_statement_and_serialises_the_same_patient(): void
    {
        $session = $this->makeSession(3);
        $patient = $this->makePatient();
        $a = $this->openConnection(1);
        $c = $this->openConnection(1);
        $startAt = $this->barrier();

        $a->beginTransaction();
        $this->lockRow($a, 'patient_profiles', $patient->id);
        $process = $this->spawn('book', ['patient' => $patient->id, 'session' => $session->id], $startAt);

        $this->waitUntil($startAt + 1.5);
        $this->assertTrue($process->isRunning(), 'Proses B harus menunggu kunci profil pasien.');

        // B menunggu kunci PROFIL dan belum menyentuh baris sesi: koneksi lain masih dapat mengunci sesi tanpa menunggu.
        $c->beginTransaction();
        $this->lockRow($c, 'practice_sessions', $session->id);
        $c->rollBack();

        $releaseAt = microtime(true);
        $a->rollBack();

        $result = $this->gather([$process])[0];

        $this->assertTrue($result['ok'], json_encode($result));
        $this->assertSame(1, $result['queue']);
        $this->assertLessThan($releaseAt, $result['begin'], 'Proses B terlambat boot: naikkan BARRIER_BASE.');
        $this->assertGreaterThanOrEqual($releaseAt - 0.05, $result['end'], 'B selesai sebelum A melepas kunci profil.');
        $this->assertSessionIsConsistent($session);
    }

    public function test_kon_01e_a_waiting_booking_sees_the_session_status_written_by_the_lock_holder(): void
    {
        $session = $this->makeSession(3);
        $holder = $this->makePatient();
        $waiter = $this->makePatient();
        $a = $this->openConnection(1);
        $startAt = $this->barrier();

        $this->beginAndLock($a, $holder->id, $session->id);
        $process = $this->spawn('book', ['patient' => $waiter->id, 'session' => $session->id], $startAt);

        $this->waitUntil($startAt + 1.5);
        $this->assertTrue($process->isRunning(), 'Proses B harus masih menunggu kunci sesi yang ditahan A.');

        // Seperti ScheduleChangeService: sesi dibatalkan sementara A menahan kunci.
        $a->prepare("UPDATE practice_sessions SET status = 'cancelled', status_reason = 'Uji konkurensi' WHERE id = ?")->execute([$session->id]);
        $a->commit();

        $result = $this->gather([$process])[0];

        $this->assertFalse($result['ok'], json_encode($result));
        $this->assertSame('SESSION_UNAVAILABLE', $result['code']);
        $this->assertNoPartialWrites($session);
    }

    // ------------------------------------------------------------------ BKG-21 = KON-01c

    public function test_kon_01c_and_bkg_21_system_busy_when_the_session_lock_outlasts_three_attempts(): void
    {
        Notification::fake();
        $session = $this->makeSession(3);
        $profile = $this->makePatient();
        $holder = $this->openConnection(1);

        $this->beginAndLock($holder, $this->makePatient()->id, $session->id);
        DB::statement('SET SESSION innodb_lock_wait_timeout = 1');

        // precheck() tidak mengunci dan tidak menulis: ia selesai walau baris sesi sedang terkunci. Bukti strukturalnya
        // ada pada innodb_lock_wait_timeout = 1: precheck yang mengunci akan melempar QueryException 1205 dan tes gagal
        // (tanpa asersi ambang waktu, yang dapat gagal acak pada DB bersama).
        $this->service()->precheck($profile, PracticeSession::query()->findOrFail($session->id), new BookingData(PaymentType::Umum), $this->actorOf($profile));

        $startedAt = microtime(true);
        $e = $this->bookingFailure($profile, $session->id);
        $elapsed = microtime(true) - $startedAt;

        $this->assertSame('SYSTEM_BUSY', $e->errorCode);
        $this->assertSame('Sistem sedang sibuk, silakan coba lagi beberapa saat.', $e->getMessage());
        $this->assertGreaterThanOrEqual(2.0, $elapsed, 'Tiga percobaan x timeout 1 detik: terlalu cepat berarti tidak diulang.');
        $this->assertLessThan(20.0, $elapsed);

        $this->assertNoPartialWrites($session);
        Notification::assertNothingSent();

        // Setelah kunci dilepas, booking yang sama berhasil.
        $holder->rollBack();
        $appointment = $this->attemptBooking($profile, $session->id);

        $this->assertSame(1, $appointment->queue_number);
        $this->assertSessionIsConsistent($session);
        Notification::assertSentToTimes(User::query()->findOrFail($profile->user_id), AppointmentStatusChangedNotification::class, 1);
    }

    // ------------------------------------------------------------------ BKG-18: galat driver dipetakan

    public function test_bkg_18_unique_active_violation_becomes_duplicate_booking_without_partial_writes(): void
    {
        Notification::fake();
        $session = $this->makeSession(3);
        $profile = $this->makePatient();
        $fault = $this->injectFault(
            'insert into `appointments`',
            1062,
            '23000',
            "SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry '1-1-1' for key 'appointments_patient_session_active_unique'",
        );

        $e = $this->bookingFailure($profile, $session->id);

        $this->assertSame('DUPLICATE_BOOKING', $e->errorCode);
        $this->assertSame(1, $fault->hits);
        $this->assertNoPartialWrites($session);
        Notification::assertNothingSent();
    }

    public function test_bkg_18_booking_code_collision_regenerates_the_code_and_retries(): void
    {
        Notification::fake();
        $session = $this->makeSession(3);
        $profile = $this->makePatient();
        $fault = $this->injectFault(
            'insert into `appointments`',
            1062,
            '23000',
            "SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry 'UPH-AAAA-AAAA' for key 'appointments_booking_code_unique'",
            1,
        );

        $appointment = $this->attemptBooking($profile, $session->id);

        $this->assertSame(2, $fault->hits, 'INSERT harus diulang tepat satu kali.');
        $this->assertSame(1, $appointment->queue_number);
        $this->assertSame(1, Appointment::query()->where('practice_session_id', $session->id)->count());
        $this->assertSessionIsConsistent($session);
        $this->assertSame(1, $this->rowsSince('appointment_status_histories'));
        $this->assertSame(1, $this->rowsSince('audit_logs', 'appointment.created'));
        Notification::assertSentToTimes(User::query()->findOrFail($profile->user_id), AppointmentStatusChangedNotification::class, 1);
    }

    public function test_bkg_18_queue_number_collision_retries_the_whole_transaction(): void
    {
        Notification::fake();
        $session = $this->makeSession(3);
        $profile = $this->makePatient();
        $fault = $this->injectFault(
            'insert into `appointments`',
            1062,
            '23000',
            "SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry '1-1' for key 'appointments_practice_session_id_queue_number_unique'",
            1,
        );

        $appointment = $this->attemptBooking($profile, $session->id);

        $this->assertSame(2, $fault->hits);
        $this->assertSame(1, $appointment->queue_number);
        $this->assertSessionIsConsistent($session);
        $this->assertSame(1, (int) PracticeSession::query()->findOrFail($session->id)->booked_count, 'Percobaan pertama harus digulung balik sebelum diulang.');
        Notification::assertSentToTimes(User::query()->findOrFail($profile->user_id), AppointmentStatusChangedNotification::class, 1);
    }

    /** @return array<string, array{0: int, 1: string, 2: string}> */
    public static function quotaViolations(): array
    {
        return [
            'MariaDB 4025' => [4025, '23000', 'SQLSTATE[23000]: Integrity constraint violation: 4025 CONSTRAINT `chk_sessions_quota` failed for `unpam_hospital_test`.`practice_sessions`'],
            'MySQL 3819' => [3819, 'HY000', "SQLSTATE[HY000]: General error: 3819 Check constraint 'chk_sessions_quota' is violated."],
        ];
    }

    #[DataProvider('quotaViolations')]
    public function test_bkg_18_the_quota_check_violation_becomes_quota_full(int $errno, string $sqlState, string $message): void
    {
        Notification::fake();
        $session = $this->makeSession(3);
        $profile = $this->makePatient();
        $this->injectFault('update `practice_sessions`', $errno, $sqlState, $message);

        $e = $this->bookingFailure($profile, $session->id);

        $this->assertSame('QUOTA_FULL', $e->errorCode);
        $this->assertNoPartialWrites($session);
        Notification::assertNothingSent();
    }

    public function test_bkg_18_any_other_check_violation_is_a_generic_database_error_with_full_rollback(): void
    {
        Notification::fake();
        $session = $this->makeSession(3);
        $profile = $this->makePatient();
        $this->injectFault(
            'insert into `appointments`',
            4025,
            '23000',
            'SQLSTATE[23000]: Integrity constraint violation: 4025 CONSTRAINT `chk_appointments_queue` failed for `unpam_hospital_test`.`appointments`',
        );

        try {
            $this->attemptBooking($profile, $session->id);
            $this->fail('QueryException tidak dilempar.');
        } catch (QueryException $e) {
            $this->assertSame(4025, $e->errorInfo[1]);
            $this->assertStringContainsString('chk_appointments_queue', $e->getMessage());
        }

        $this->assertNoPartialWrites($session);
        Notification::assertNothingSent();
    }

    /** @return array<string, array{0: int, 1: string, 2: string}> */
    public static function concurrencyErrors(): array
    {
        return [
            '1205 lock wait' => [1205, 'HY000', 'SQLSTATE[HY000]: General error: 1205 Lock wait timeout exceeded; try restarting transaction'],
            '1213 deadlock' => [1213, '40001', 'SQLSTATE[40001]: Serialization failure: 1213 Deadlock found when trying to get lock; try restarting transaction'],
        ];
    }

    #[DataProvider('concurrencyErrors')]
    public function test_bkg_18_a_concurrency_error_that_persists_for_three_attempts_becomes_system_busy(int $errno, string $sqlState, string $message): void
    {
        Notification::fake();
        $session = $this->makeSession(3);
        $profile = $this->makePatient();
        $fault = $this->injectFault('insert into `appointments`', $errno, $sqlState, $message);

        $e = $this->bookingFailure($profile, $session->id);

        $this->assertSame('SYSTEM_BUSY', $e->errorCode);
        $this->assertSame('Sistem sedang sibuk, silakan coba lagi beberapa saat.', $e->getMessage());
        $this->assertSame(3, $fault->hits, 'attempts: 3 berarti tepat tiga percobaan.');
        $this->assertNoPartialWrites($session);
        Notification::assertNothingSent();
    }

    #[DataProvider('concurrencyErrors')]
    public function test_a_persistent_concurrency_error_is_reported_without_sensitive_data(int $errno, string $sqlState, string $message): void
    {
        Notification::fake();
        $session = $this->makeSession(3);
        $profile = $this->makePatient();
        $this->injectFault('insert into `appointments`', $errno, $sqlState, $message);
        Exceptions::fake();

        try {
            $this->service()->book($profile, $session->id, new BookingData(PaymentType::Umum, complaint: 'KELUHAN-RAHASIA-KON'), $this->actorOf($profile));
            $this->fail('BookingException tidak dilempar.');
        } catch (BookingException $e) {
            $this->assertSame('SYSTEM_BUSY', $e->errorCode);
        }

        Exceptions::assertReportedCount(1);
        Exceptions::assertReported(fn (SanitizedBookingFault $e): bool => str_contains($e->getMessage(), 'SYSTEM_BUSY')
            && str_contains($e->getMessage(), 'driver='.$errno)
            && str_contains($e->getMessage(), 'sesi='.$session->id)
            && str_contains($e->getMessage(), 'profil='.$profile->id)
            && ! str_contains($e->getMessage(), 'KELUHAN-RAHASIA-KON')
            && ! str_contains($e->getMessage(), 'insert into')
            && $e->getPrevious() === null);
    }

    #[DataProvider('concurrencyErrors')]
    public function test_bkg_18_a_concurrency_error_that_clears_within_three_attempts_is_retried_to_success(int $errno, string $sqlState, string $message): void
    {
        Notification::fake();
        $session = $this->makeSession(3);
        $profile = $this->makePatient();
        $fault = $this->injectFault('insert into `appointments`', $errno, $sqlState, $message, 2);

        $appointment = $this->attemptBooking($profile, $session->id);

        $this->assertSame(3, $fault->hits);
        $this->assertSame(1, $appointment->queue_number);
        $this->assertSame(1, Appointment::query()->where('practice_session_id', $session->id)->count());
        $this->assertSessionIsConsistent($session);
        $this->assertSame(1, $this->rowsSince('appointment_status_histories'));
        $this->assertSame(1, $this->rowsSince('audit_logs', 'appointment.created'));
        // Notifikasi hanya untuk percobaan yang ter-commit (tidak ada efek samping di dalam closure transaksi).
        Notification::assertSentToTimes(User::query()->findOrFail($profile->user_id), AppointmentStatusChangedNotification::class, 1);
    }
}
