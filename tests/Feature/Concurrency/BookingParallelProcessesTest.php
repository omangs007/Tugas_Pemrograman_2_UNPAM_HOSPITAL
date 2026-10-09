<?php

namespace Tests\Feature\Concurrency;

use App\Enums\AppointmentStatus;
use App\Enums\ExceptionType;
use App\Enums\SessionStatus;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\PracticeSession;
use App\Models\ScheduleException;
use App\Services\Doctors\DoctorService;
use App\Services\Scheduling\ScheduleChangeReport;
use App\Services\Scheduling\ScheduleChangeService;
use App\Support\Actor;
use Illuminate\Contracts\Database\ConcurrencyErrorDetector;
use Illuminate\Database\DeadlockException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\Concurrency\ConcurrencyTestCase;

/**
 * KON-02a/b/c, KON-03, KON-04 (N proses PHP paralel memanggil BookingService/AppointmentStatusService lewat
 * tests/Support/Concurrency/book-session.php) dan carry fase 3 (booking versus ScheduleChangeService dan
 * DoctorService::archive bersamaan). Semua baris ter-commit dan dibersihkan ConcurrencyTestCase.
 */
#[Group('concurrency')]
class BookingParallelProcessesTest extends ConcurrencyTestCase
{
    /** Pesan T4 saat `DoctorService::archive()` kehabisan percobaan (F7A-R26). */
    private const BUSY_MESSAGE = 'Sistem sedang sibuk, silakan coba lagi beberapa saat.';

    /**
     * @param  list<array<string, mixed>>  $results
     */
    private function assertEverythingWrittenExactlyForTheWinners(PracticeSession $session, array $results): void
    {
        $winners = $this->successes($results);
        $count = count($winners);
        $queues = array_map(fn (array $result): int => (int) $result['queue'], $winners);
        sort($queues);

        $this->assertSame($count === 0 ? [] : range(1, $count), $queues, 'Antrean yang diberikan harus 1..sukses tanpa duplikat.');
        $this->assertSame($count, Appointment::query()->where('practice_session_id', $session->id)->count());
        $this->assertSame($count, (int) PracticeSession::query()->findOrFail($session->id)->booked_count);
        $this->assertSame($count, (int) PracticeSession::query()->findOrFail($session->id)->last_queue_number);
        $this->assertSame($count, $this->rowsSince('appointment_status_histories'));
        $this->assertSame($count, $this->rowsSince('audit_logs', 'appointment.created'));
        $this->assertSame($count, $this->notificationsSince());
        $this->assertSessionIsConsistent($session);
    }

    // ------------------------------------------------------------------ KON-02

    public function test_kon_02a_many_patients_on_one_session_never_exceed_the_quota(): void
    {
        $quota = 3;
        $count = 8;
        $session = $this->makeSession($quota);
        $startAt = $this->barrier($count);

        $processes = [];

        foreach ($this->makePatients($count) as $patient) {
            $processes[] = $this->spawn('book', ['patient' => $patient->id, 'session' => $session->id], $startAt);
        }

        $results = $this->gather($processes);

        $this->assertNoInfrastructureErrors($results);
        $this->assertCount($quota, $this->successes($results));
        $this->assertSame(array_fill(0, $count - $quota, 'QUOTA_FULL'), $this->failureCodes($results));
        $this->assertEverythingWrittenExactlyForTheWinners($session, $results);
    }

    public function test_kon_02b_the_last_seat_goes_to_exactly_one_of_many(): void
    {
        $count = 6;
        $session = $this->makeSession(1);
        $startAt = $this->barrier($count);

        $processes = [];

        foreach ($this->makePatients($count) as $patient) {
            $processes[] = $this->spawn('book', ['patient' => $patient->id, 'session' => $session->id], $startAt);
        }

        $results = $this->gather($processes);

        $this->assertNoInfrastructureErrors($results);
        $this->assertCount(1, $this->successes($results));
        $this->assertSame(1, $this->successes($results)[0]['queue']);
        $this->assertSame(array_fill(0, $count - 1, 'QUOTA_FULL'), $this->failureCodes($results));
        $this->assertEverythingWrittenExactlyForTheWinners($session, $results);
    }

    public function test_kon_02c_stress_is_opt_in_and_still_never_exceeds_the_quota(): void
    {
        if (getenv('KON_STRESS') !== '1') {
            $this->markTestSkipped('KON-02c adalah uji beban manual: jalankan dengan $env:KON_STRESS = "1".');
        }

        $quota = 10;
        $count = 50;
        $session = $this->makeSession($quota);
        $startAt = $this->barrier($count);

        $processes = [];

        foreach ($this->makePatients($count) as $patient) {
            $processes[] = $this->spawn('book', ['patient' => $patient->id, 'session' => $session->id], $startAt);
        }

        $results = $this->gather($processes);

        $this->assertNoInfrastructureErrors($results);
        $this->assertCount($quota, $this->successes($results));
        $this->assertSame(array_fill(0, $count - $quota, 'QUOTA_FULL'), $this->failureCodes($results));
        $this->assertEverythingWrittenExactlyForTheWinners($session, $results);
    }

    // ------------------------------------------------------------------ KON-03

    public function test_kon_03_the_same_patient_clicking_many_times_creates_exactly_one_active_appointment(): void
    {
        $count = 6;
        $session = $this->makeSession(10);
        $patient = $this->makePatient();
        $startAt = $this->barrier($count);

        $processes = [];

        for ($i = 0; $i < $count; $i++) {
            $processes[] = $this->spawn('book', ['patient' => $patient->id, 'session' => $session->id], $startAt);
        }

        $results = $this->gather($processes);

        $this->assertNoInfrastructureErrors($results);
        $this->assertCount(1, $this->successes($results));
        $this->assertSame(array_fill(0, $count - 1, 'DUPLICATE_BOOKING'), $this->failureCodes($results));
        $this->assertSame(1, Appointment::query()->where('patient_profile_id', $patient->id)->where('practice_session_id', $session->id)->active()->count());
        $this->assertEverythingWrittenExactlyForTheWinners($session, $results);
    }

    // ------------------------------------------------------------------ KON-04

    public function test_kon_04_cancellations_racing_new_bookings_keep_the_counters_exact_and_never_reuse_a_queue_number(): void
    {
        $session = $this->makeSession(2);
        $holders = $this->makePatients(2);
        $first = $this->bookAppointment($session, AppointmentStatus::Confirmed, ['patient_profile_id' => $holders[0]->id]);
        $second = $this->bookAppointment($session, AppointmentStatus::Confirmed, ['patient_profile_id' => $holders[1]->id]);
        $bookers = $this->makePatients(5);
        $startAt = $this->barrier(7);

        $cancels = [];
        $books = [];

        foreach ([[$first, $holders[0]], [$second, $holders[1]]] as [$appointment, $holder]) {
            $cancels[] = $this->spawn('transition', [
                'appointment' => $appointment->id,
                'to' => 'cancelled',
                'as' => 'patient',
                'actor-user' => $holder->user_id,
            ], $startAt);
        }

        foreach ($bookers as $booker) {
            $books[] = $this->spawn('book', ['patient' => $booker->id, 'session' => $session->id], $startAt);
        }

        $cancelResults = $this->gather($cancels);
        $bookResults = $this->gather($books);

        $this->assertNoInfrastructureErrors(array_merge($cancelResults, $bookResults));
        $this->assertCount(2, $this->successes($cancelResults), 'Kedua pembatalan pemilik harus berhasil: '.json_encode($cancelResults));

        $won = $this->successes($bookResults);

        $this->assertLessThanOrEqual(2, count($won), 'Pemesan baru tidak boleh melebihi kuota 2.');
        $this->assertSame(array_fill(0, 5 - count($won), 'QUOTA_FULL'), $this->failureCodes($bookResults));

        foreach ($won as $result) {
            $this->assertGreaterThanOrEqual(3, $result['queue'], 'Nomor antrean 1 dan 2 sudah pernah dipakai dan tidak boleh dipakai ulang.');
        }

        $fresh = PracticeSession::query()->findOrFail($session->id);

        $this->assertSame(count($won), (int) $fresh->booked_count);
        $this->assertSame(2 + count($won), (int) $fresh->last_queue_number);
        $this->assertSame(2, Appointment::query()->where('practice_session_id', $session->id)->where('status', AppointmentStatus::Cancelled->value)->count());
        $this->assertSessionIsConsistent($session);
    }

    // ------------------------------------------------------------------ carry fase 3

    public function test_carry_f3_bookings_racing_a_leave_exception_never_leave_an_active_appointment_on_a_cancelled_session(): void
    {
        $doctor = Doctor::factory()->create();
        $staff = $this->makeStaff();
        $days = range(1, 5);
        $sessions = [];
        $processes = [];
        $startAt = $this->barrier(count($days));

        foreach ($days as $day) {
            $sessions[$day] = $this->makeSession(3, [
                'doctor_id' => $doctor->id,
                'clinic_id' => $doctor->clinic_id,
                'session_date' => today()->addDays($day)->toDateString(),
            ]);
            $processes[$day] = $this->spawn('book', ['patient' => $this->makePatient()->id, 'session' => $sessions[$day]->id], $startAt, 10);
        }

        $this->waitUntil($startAt);

        $report = null;

        try {
            // Seperti ScheduleExceptionController@store: pengecualian dibuat dan diterapkan dalam satu transaksi.
            $report = DB::transaction(function () use ($doctor, $staff): ScheduleChangeReport {
                $exception = ScheduleException::query()->create([
                    'doctor_id' => $doctor->id,
                    'type' => ExceptionType::DoctorLeave,
                    'start_date' => today()->addDay()->toDateString(),
                    'end_date' => today()->addDays(5)->toDateString(),
                    'reason' => 'Uji konkurensi',
                ]);

                return $this->app->make(ScheduleChangeService::class)->applyException($exception, null, Actor::staff($staff));
            });
        } catch (QueryException|DeadlockException $e) {
            // Korban siklus kunci (lihat Catatan teknis no. 7a): sah untuk invarian data, tetapi dicatat sebagai temuan.
            $this->assertTrue($this->app->make(ConcurrencyErrorDetector::class)->causedByConcurrencyError($e), $e->getMessage());
        }

        $results = array_combine($days, $this->gather(array_values($processes)));

        $this->assertNoInfrastructureErrors(array_values($results));

        if ($report !== null) {
            $this->assertSame(5, $report->cancelled + $report->needsReview, 'Kelima sesi tercakup pengecualian.');
        }

        foreach ($days as $day) {
            $fresh = PracticeSession::query()->findOrFail($sessions[$day]->id);
            $active = Appointment::query()->where('practice_session_id', $fresh->id)->active()->count();

            if ($report === null) {
                // Transaksi jadwal dibatalkan seluruhnya: tidak boleh ada sesi cancelled yang memuat appointment aktif.
                $this->assertFalse($fresh->status === SessionStatus::Cancelled && $active > 0, "Sesi hari +{$day} cancelled tetapi memuat appointment aktif.");
            } elseif ($active > 0) {
                $this->assertSame(SessionStatus::NeedsReview, $fresh->status, "Sesi hari +{$day} punya appointment aktif sehingga harus needs_review.");
                $this->assertTrue($results[$day]['ok'], json_encode($results[$day]));
            } else {
                $this->assertSame(SessionStatus::Cancelled, $fresh->status, "Sesi hari +{$day} tanpa appointment aktif harus cancelled.");
                $this->assertSame('SESSION_UNAVAILABLE', $results[$day]['code'] ?? null, json_encode($results[$day]));
            }

            $this->assertSessionIsConsistent($fresh);
        }
    }

    public function test_carry_f3_bookings_racing_a_doctor_archive_never_leave_an_archived_doctor_with_an_active_future_appointment(): void
    {
        $doctor = Doctor::factory()->create();
        $staff = $this->makeStaff();
        $days = range(1, 4);
        $sessions = [];
        $processes = [];
        $startAt = $this->barrier(count($days));

        foreach ($days as $day) {
            $sessions[$day] = $this->makeSession(3, [
                'doctor_id' => $doctor->id,
                'clinic_id' => $doctor->clinic_id,
                'session_date' => today()->addDays($day)->toDateString(),
            ]);
            $processes[$day] = $this->spawn('book', ['patient' => $this->makePatient()->id, 'session' => $sessions[$day]->id], $startAt, 10);
        }

        $this->waitUntil($startAt);

        $outcome = 'archived';
        $busy = false;

        try {
            $this->app->make(DoctorService::class)->archive($doctor, 'Uji konkurensi', Actor::staff($staff));
        } catch (ValidationException $e) {
            // F7A-R26: arsip mengulang deadlock (attempts: 3) dan menerjemahkan sisa galat konkurensi menjadi pesan ramah
            // (ValidationException kunci `status`); QueryException/DeadlockException mentah tidak lagi sah (tidak ada 500).
            $outcome = 'rejected';
            $busy = ($e->errors()['status'][0] ?? null) === self::BUSY_MESSAGE;
        }

        $results = array_combine($days, $this->gather(array_values($processes)));

        $this->assertNoInfrastructureErrors(array_values($results));

        $isArchived = $doctor->fresh()->archived_at !== null;
        $this->assertContains($outcome, ['archived', 'rejected'], 'Hasil sah hanya archived atau rejected (termasuk pesan sibuk).');
        $this->assertSame($outcome === 'archived', $isArchived, "Hasil arsip '{$outcome}' tidak sesuai status dokter.");

        if ($busy) {
            $this->assertFalse($isArchived, 'Pesan sibuk berarti arsip dibatalkan seluruhnya (tanpa tulisan parsial).');
        }

        $upcomingActive = Appointment::query()->where('doctor_id', $doctor->id)->upcomingActive()->count();

        if ($isArchived) {
            $this->assertSame(0, $upcomingActive, 'Dokter terarsip tidak boleh memiliki appointment aktif mendatang.');
            $this->assertSame([], $this->successes(array_values($results)), 'Booking yang sukses berarti arsip seharusnya ditolak.');
        } else {
            $this->assertSame(
                0,
                PracticeSession::query()->where('doctor_id', $doctor->id)->where('status', SessionStatus::Cancelled->value)->count(),
                "Arsip '{$outcome}' harus tidak meninggalkan sesi cancelled.",
            );
            $this->assertCount(4, $this->successes(array_values($results)), 'Tanpa arsip, keempat booking harus berhasil.');
        }

        foreach ($days as $day) {
            $this->assertSessionIsConsistent($sessions[$day]);
        }
    }
}
