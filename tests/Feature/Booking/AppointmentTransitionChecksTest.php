<?php

namespace Tests\Feature\Booking;

use App\Enums\AppointmentStatus;
use App\Exceptions\BookingException;
use App\Exceptions\InvalidTransitionException;
use App\Exceptions\SanitizedBookingFault;
use App\Models\Appointment;
use App\Models\AppointmentStatusHistory;
use App\Models\AuditLog;
use App\Models\PatientProfile;
use App\Models\PracticeSession;
use App\Models\User;
use App\Services\Booking\AppointmentStatusService;
use App\Services\Booking\TransitionCheck;
use App\Support\Actor;
use Carbon\CarbonImmutable;
use Database\Seeders\SettingsSeeder;
use Illuminate\Database\DeadlockException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Notification;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InteractsWithSchedules;
use Tests\TestCase;

/**
 * F7A-R5, Review Focus E1: tombol (transitionChecks/availableTransitions) tidak pernah berbeda dari aturan
 * server (transition()). Waktu dibekukan dengan travelTo(): "sekarang" awal = Senin 2026-10-05 08.00 WIB; sesi
 * uji = Selasa 2026-10-06 09.00-12.00, kuota 20, sehingga batas pembatalan pasien (120 menit) = 07.00, check-in
 * dibuka 08.00 dan ditutup tepat 12.00.
 */
class AppointmentTransitionChecksTest extends TestCase
{
    use InteractsWithSchedules;
    use RefreshDatabase;

    /** Saat-saat uji kesetaraan: di sekitar batas batal pasien (07.00), buka check-in (08.00), dan akhir sesi (12.00). */
    private const MOMENTS = [
        '2026-10-05 08:00:00',
        '2026-10-05 20:00:00',
        '2026-10-06 07:00:00',
        '2026-10-06 07:01:00',
        '2026-10-06 07:59:00',
        '2026-10-06 08:00:00',
        '2026-10-06 10:00:00',
        '2026-10-06 11:59:59',
        '2026-10-06 12:00:00',
        '2026-10-06 12:00:01',
        '2026-10-07 10:00:00',
    ];

    private AppointmentStatusService $service;

    private PracticeSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-10-05 08:00:00'));
        $this->seed(SettingsSeeder::class);

        $this->service = $this->app->make(AppointmentStatusService::class);
        $this->session = $this->createSession([
            'doctor_schedule_id' => null,
            'session_date' => '2026-10-06',
            'start_time' => '09:00:00',
            'end_time' => '12:00:00',
            'quota' => 20,
        ]);
    }

    // ------------------------------------------------------------------ pembantu

    private function at(string $moment): void
    {
        $this->travelTo(CarbonImmutable::parse($moment));
    }

    /** Appointment pada sesi uji milik pasien berakun baru. */
    private function appointment(AppointmentStatus $status = AppointmentStatus::Confirmed): Appointment
    {
        $profile = PatientProfile::factory()->withAccount()->create();

        return $this->bookAppointment($this->session, $status, ['patient_profile_id' => $profile->id]);
    }

    private function ownerOf(Appointment $appointment): Actor
    {
        $userId = PatientProfile::query()->whereKey($appointment->patient_profile_id)->value('user_id');

        return Actor::patient(User::query()->findOrFail($userId));
    }

    /** owner = pasien pemilik; stranger = pasien lain; staff; system. */
    private function actorFor(string $kind, Appointment $appointment): Actor
    {
        return match ($kind) {
            'owner' => $this->ownerOf($appointment),
            'stranger' => Actor::patient(User::query()->findOrFail(PatientProfile::factory()->withAccount()->create()->user_id)),
            'staff' => Actor::staff(User::factory()->staff()->create()),
            default => Actor::system(),
        };
    }

    /**
     * Selalu memakai instance BARU dari DB (relasi belum dimuat) agar `loadMissing` ikut teruji.
     *
     * @return list<TransitionCheck>
     */
    private function checks(Appointment $appointment, Actor $actor, ?CarbonImmutable $now = null): array
    {
        return $this->service->transitionChecks(Appointment::query()->findOrFail($appointment->id), $actor, $now);
    }

    /** @return list<AppointmentStatus> */
    private function available(Appointment $appointment, Actor $actor, ?CarbonImmutable $now = null): array
    {
        return $this->service->availableTransitions(Appointment::query()->findOrFail($appointment->id), $actor, $now);
    }

    /** @param list<TransitionCheck> $checks */
    private function check(array $checks, AppointmentStatus $to): TransitionCheck
    {
        foreach ($checks as $check) {
            if ($check->to === $to) {
                return $check;
            }
        }

        $this->fail("Tidak ada TransitionCheck untuk {$to->value}.");
    }

    /**
     * @param  list<TransitionCheck>  $checks
     * @return list<string>
     */
    private function targets(array $checks): array
    {
        return array_map(fn (TransitionCheck $check): string => $check->to->value, $checks);
    }

    /**
     * @param  list<AppointmentStatus>  $statuses
     * @return list<string>
     */
    private function values(array $statuses): array
    {
        return array_map(fn (AppointmentStatus $status): string => $status->value, $statuses);
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

    /** Menjalankan transition() dengan alasan sah, tanpa expected, lalu menggulung semuanya (savepoint). */
    private function attemptRolledBack(int $appointmentId, AppointmentStatus $to, Actor $actor): ?InvalidTransitionException
    {
        DB::beginTransaction();

        try {
            $this->service->transition(Appointment::query()->findOrFail($appointmentId), $to, $actor, 'Alasan uji kesetaraan');

            return null;
        } catch (InvalidTransitionException $e) {
            return $e;
        } finally {
            DB::rollBack();
        }
    }

    // ------------------------------------------------------------------ bentuk dan kandidat

    public function test_individual_targets_are_the_four_ui_targets_without_no_show_or_pending(): void
    {
        $this->assertSame(
            [AppointmentStatus::Confirmed, AppointmentStatus::CheckedIn, AppointmentStatus::Completed, AppointmentStatus::Cancelled],
            AppointmentStatusService::INDIVIDUAL_TARGETS,
        );
    }

    /** @return array<string, array{0: AppointmentStatus, 1: list<string>}> */
    public static function candidates(): array
    {
        return [
            'pending' => [AppointmentStatus::Pending, ['confirmed', 'checked_in', 'cancelled']],
            'confirmed' => [AppointmentStatus::Confirmed, ['checked_in', 'cancelled']],
            'checked_in' => [AppointmentStatus::CheckedIn, ['confirmed', 'completed']],
        ];
    }

    /** @param list<string> $expected */
    #[DataProvider('candidates')]
    public function test_candidates_are_the_legal_targets_in_individual_targets_order_and_staff_may_use_all_of_them_at_10(AppointmentStatus $from, array $expected): void
    {
        $appointment = $this->appointment($from);
        $staff = $this->actorFor('staff', $appointment);
        $this->at('2026-10-06 10:00:00');

        $checks = $this->checks($appointment, $staff);

        $this->assertSame($expected, $this->targets($checks));
        $this->assertSame($expected, $this->values($this->available($appointment, $staff)));

        foreach ($checks as $check) {
            $this->assertTrue($check->allowed(), $check->to->value.' ditolak: '.$check->errorCode);
            $this->assertNull($check->message);
            $this->assertSame([], $check->replace);
        }
    }

    /** @return array<string, array{0: AppointmentStatus}> */
    public static function finalStatuses(): array
    {
        return [
            'completed' => [AppointmentStatus::Completed],
            'cancelled' => [AppointmentStatus::Cancelled],
            'no_show' => [AppointmentStatus::NoShow],
        ];
    }

    #[DataProvider('finalStatuses')]
    public function test_a_final_appointment_has_no_checks_and_nothing_available_for_every_actor(AppointmentStatus $final): void
    {
        $appointment = $this->appointment($final);
        $this->at('2026-10-06 10:00:00');

        foreach (['owner', 'stranger', 'staff', 'system'] as $kind) {
            $actor = $this->actorFor($kind, $appointment);

            $this->assertSame([], $this->checks($appointment, $actor), $kind);
            $this->assertSame([], $this->available($appointment, $actor), $kind);
        }
    }

    public function test_no_show_is_never_offered_to_any_actor_in_any_state_or_moment(): void
    {
        foreach ([AppointmentStatus::Pending, AppointmentStatus::Confirmed, AppointmentStatus::CheckedIn] as $from) {
            $appointment = $this->appointment($from);

            foreach (['owner', 'stranger', 'staff', 'system'] as $kind) {
                $actor = $this->actorFor($kind, $appointment);

                foreach (['2026-10-06 10:00:00', '2026-10-06 12:30:00', '2026-10-07 09:00:00'] as $moment) {
                    $this->at($moment);

                    $this->assertNotContains('no_show', $this->targets($this->checks($appointment, $actor)), "{$kind} {$from->value} {$moment}");
                    $this->assertNotContains(AppointmentStatus::NoShow, $this->available($appointment, $actor));
                    $this->assertNotContains(AppointmentStatus::Pending, $this->available($appointment, $actor));
                }
            }
        }
    }

    // ------------------------------------------------------------------ E1: check-in

    /** @return array<string, array{0: AppointmentStatus}> */
    public static function checkInSources(): array
    {
        return [
            'pending boleh langsung check-in' => [AppointmentStatus::Pending],
            'confirmed' => [AppointmentStatus::Confirmed],
        ];
    }

    #[DataProvider('checkInSources')]
    public function test_e1_check_in_is_offered_from_the_exact_opening_minute_through_the_exact_end(AppointmentStatus $from): void
    {
        $appointment = $this->appointment($from);
        $staff = $this->actorFor('staff', $appointment);

        // Satu menit sebelum buka (08.00) dan satu detik sebelum buka: belum dibuka, jam buka 08.00.
        foreach (['2026-10-06 07:59:00', '2026-10-06 07:59:59'] as $moment) {
            $this->at($moment);
            $check = $this->check($this->checks($appointment, $staff), AppointmentStatus::CheckedIn);

            $this->assertFalse($check->allowed(), $moment);
            $this->assertSame('CHECKIN_NOT_OPEN', $check->errorCode);
            $this->assertSame('08.00', $check->opensAt());
            $this->assertSame(['time' => '08.00'], $check->replace);
            $this->assertSame('Check-in belum dibuka. Check-in dibuka pukul 08.00.', $check->message);
            $this->assertNotContains(AppointmentStatus::CheckedIn, $this->available($appointment, $staff));
        }

        // Tepat jam buka dan tepat akhir sesi: diterima.
        foreach (['2026-10-06 08:00:00', '2026-10-06 10:30:00', '2026-10-06 12:00:00'] as $moment) {
            $this->at($moment);
            $check = $this->check($this->checks($appointment, $staff), AppointmentStatus::CheckedIn);

            $this->assertTrue($check->allowed(), $moment);
            $this->assertNull($check->errorCode);
            $this->assertNull($check->opensAt());
            $this->assertContains(AppointmentStatus::CheckedIn, $this->available($appointment, $staff));
        }

        // Sesudah akhir: tidak ada jam buka, hanya sesi sudah berakhir.
        foreach (['2026-10-06 12:00:01', '2026-10-06 12:01:00'] as $moment) {
            $this->at($moment);
            $check = $this->check($this->checks($appointment, $staff), AppointmentStatus::CheckedIn);

            $this->assertFalse($check->allowed(), $moment);
            $this->assertSame('CHECKIN_CLOSED', $check->errorCode);
            $this->assertNull($check->opensAt());
            $this->assertSame('Sesi sudah berakhir sehingga check-in tidak dapat dilakukan.', $check->message);
            $this->assertNotContains(AppointmentStatus::CheckedIn, $this->available($appointment, $staff));
        }

        // Hari lain (sebelum dan sesudah tanggal sesi).
        $date = $this->session->session_date->translatedFormat('l, j F Y');

        foreach (['2026-10-05 10:00:00', '2026-10-07 10:00:00'] as $moment) {
            $this->at($moment);
            $check = $this->check($this->checks($appointment, $staff), AppointmentStatus::CheckedIn);

            $this->assertSame('CHECKIN_NOT_TODAY', $check->errorCode, $moment);
            $this->assertSame("Check-in hanya dapat dilakukan pada tanggal kunjungan ({$date}).", $check->message);
            $this->assertNull($check->opensAt());
        }
    }

    public function test_e1_the_check_in_button_state_equals_what_the_server_accepts_at_the_boundaries(): void
    {
        $cases = [
            '2026-10-06 07:59:00' => false,
            '2026-10-06 08:00:00' => true,
            '2026-10-06 12:00:00' => true,
            '2026-10-06 12:01:00' => false,
        ];

        foreach ($cases as $moment => $accepted) {
            $appointment = $this->appointment(AppointmentStatus::Confirmed);
            $staff = $this->actorFor('staff', $appointment);
            $this->at($moment);
            $check = $this->check($this->checks($appointment, $staff), AppointmentStatus::CheckedIn);

            $this->assertSame($accepted, $check->allowed(), $moment);

            if ($accepted) {
                $this->service->transition(Appointment::query()->findOrFail($appointment->id), AppointmentStatus::CheckedIn, $staff);
                $this->assertSame(AppointmentStatus::CheckedIn, $appointment->fresh()->status, $moment);

                continue;
            }

            $e = $this->failure(fn () => $this->service->transition(Appointment::query()->findOrFail($appointment->id), AppointmentStatus::CheckedIn, $staff));

            $this->assertSame($check->errorCode, $e->errorCode, $moment);
            $this->assertSame($check->message, $e->getMessage(), $moment);
            $this->assertSame(AppointmentStatus::Confirmed, $appointment->fresh()->status, $moment);
        }
    }

    public function test_e1_confirming_is_offered_until_the_exact_end_of_the_session(): void
    {
        $appointment = $this->appointment(AppointmentStatus::Pending);
        $staff = $this->actorFor('staff', $appointment);

        $this->at('2026-10-06 11:59:59');
        $this->assertTrue($this->check($this->checks($appointment, $staff), AppointmentStatus::Confirmed)->allowed());

        $this->at('2026-10-06 12:00:00');
        $check = $this->check($this->checks($appointment, $staff), AppointmentStatus::Confirmed);

        $this->assertFalse($check->allowed());
        $this->assertSame('CONFIRM_AFTER_END', $check->errorCode);
        $this->assertSame('Sesi sudah berakhir sehingga pendaftaran tidak dapat dikonfirmasi.', $check->message);
    }

    // ------------------------------------------------------------------ E1: koreksi dan selesai

    public function test_e1_correction_is_offered_only_on_the_session_day_and_completing_has_no_time_window(): void
    {
        $appointment = $this->appointment(AppointmentStatus::CheckedIn);
        $staff = $this->actorFor('staff', $appointment);

        foreach (['2026-10-06 00:00:00', '2026-10-06 09:30:00', '2026-10-06 23:59:59'] as $moment) {
            $this->at($moment);
            $checks = $this->checks($appointment, $staff);

            $this->assertTrue($this->check($checks, AppointmentStatus::Confirmed)->allowed(), $moment);
            $this->assertTrue($this->check($checks, AppointmentStatus::Completed)->allowed(), $moment);
        }

        foreach (['2026-10-05 12:00:00', '2026-10-07 00:00:00'] as $moment) {
            $this->at($moment);
            $checks = $this->checks($appointment, $staff);
            $correction = $this->check($checks, AppointmentStatus::Confirmed);

            $this->assertFalse($correction->allowed(), $moment);
            $this->assertSame('CORRECTION_NOT_SAME_DAY', $correction->errorCode);
            $this->assertSame('Koreksi check-in hanya dapat dilakukan pada hari yang sama.', $correction->message);
            $this->assertNotContains(AppointmentStatus::Confirmed, $this->available($appointment, $staff));
            $this->assertTrue($this->check($checks, AppointmentStatus::Completed)->allowed(), $moment);
        }
    }

    // ------------------------------------------------------------------ pembatalan: staf, pasien, sistem

    public function test_staff_may_cancel_at_any_time_but_the_patient_is_bound_by_the_cutoff(): void
    {
        $appointment = $this->appointment(AppointmentStatus::Pending);
        $staff = $this->actorFor('staff', $appointment);
        $owner = $this->ownerOf($appointment);

        $this->at('2026-10-06 13:00:00');
        $this->assertTrue($this->check($this->checks($appointment, $staff), AppointmentStatus::Cancelled)->allowed());

        $this->at('2026-10-05 20:00:00');
        $this->assertTrue($this->check($this->checks($appointment, $owner), AppointmentStatus::Cancelled)->allowed());

        $this->at('2026-10-06 07:00:00');
        $this->assertTrue($this->check($this->checks($appointment, $owner), AppointmentStatus::Cancelled)->allowed(), 'Tepat start - 120 menit diterima.');

        $this->at('2026-10-06 07:01:00');
        $late = $this->check($this->checks($appointment, $owner), AppointmentStatus::Cancelled);

        $this->assertFalse($late->allowed());
        $this->assertSame('CANCEL_CUTOFF_PASSED', $late->errorCode);
        $this->assertSame(120, $late->replace['minutes']);
        $this->assertStringContainsString('120', (string) $late->message);
        $this->assertStringContainsString('(021) 0000-0000', (string) $late->message);
    }

    public function test_a_patient_only_sees_cancel_on_their_own_pending_or_confirmed_appointment(): void
    {
        $appointment = $this->appointment(AppointmentStatus::Pending);
        $owner = $this->ownerOf($appointment);
        $stranger = $this->actorFor('stranger', $appointment);
        $this->at('2026-10-05 20:00:00');

        $this->assertSame(['cancelled'], $this->values($this->available($appointment, $owner)));

        $checks = $this->checks($appointment, $owner);
        $this->assertSame('FORBIDDEN_TRANSITION', $this->check($checks, AppointmentStatus::Confirmed)->errorCode);
        $this->assertSame('FORBIDDEN_TRANSITION', $this->check($checks, AppointmentStatus::CheckedIn)->errorCode);

        // Pasien lain tidak melihat apa pun, termasuk batal.
        $this->assertSame([], $this->available($appointment, $stranger));

        foreach ($this->checks($appointment, $stranger) as $check) {
            $this->assertSame('FORBIDDEN_TRANSITION', $check->errorCode, $check->to->value);
        }
    }

    public function test_a_patient_sees_nothing_on_a_checked_in_appointment(): void
    {
        $appointment = $this->appointment(AppointmentStatus::CheckedIn);
        $owner = $this->ownerOf($appointment);
        $this->at('2026-10-06 09:30:00');

        $checks = $this->checks($appointment, $owner);

        $this->assertSame(['confirmed', 'completed'], $this->targets($checks));
        $this->assertSame([], $this->available($appointment, $owner));
        $this->assertNotContains('cancelled', $this->targets($checks));
    }

    public function test_the_system_actor_is_only_offered_cancel(): void
    {
        $appointment = $this->appointment(AppointmentStatus::Confirmed);
        $system = $this->actorFor('system', $appointment);
        $this->at('2026-10-06 10:00:00');

        $this->assertSame(['cancelled'], $this->values($this->available($appointment, $system)));

        $checks = $this->checks($appointment, $system);
        $this->assertSame('FORBIDDEN_TRANSITION', $this->check($checks, AppointmentStatus::CheckedIn)->errorCode);
    }

    // ------------------------------------------------------------------ tanpa efek samping, tanpa kunci

    public function test_the_checks_ignore_the_reason_and_never_write_or_lock(): void
    {
        $appointment = $this->appointment(AppointmentStatus::CheckedIn);
        $staff = $this->actorFor('staff', $appointment);
        $this->at('2026-10-06 10:00:00');
        $updatedAt = $appointment->fresh()->updated_at->toDateTimeString();
        $fresh = Appointment::query()->findOrFail($appointment->id);
        $this->assertFalse($fresh->relationLoaded('practiceSession'));
        $this->assertFalse($fresh->relationLoaded('patientProfile'));

        DB::flushQueryLog();
        DB::enableQueryLog();
        $checks = $this->service->transitionChecks($fresh, $staff);
        $queries = array_column(DB::getQueryLog(), 'query');
        DB::disableQueryLog();

        // Koreksi mensyaratkan alasan di server, tetapi tombolnya tetap muncul (modal mengumpulkan alasan).
        $this->assertTrue($this->check($checks, AppointmentStatus::Confirmed)->allowed());

        $this->assertTrue($fresh->relationLoaded('practiceSession'));
        $this->assertTrue($fresh->relationLoaded('patientProfile'));

        foreach ($queries as $query) {
            $this->assertStringNotContainsString('for update', strtolower($query), 'Pemeriksaan tombol tidak boleh mengunci baris.');
            $this->assertStringNotContainsString('insert', strtolower($query));
            $this->assertStringNotContainsString('update `', strtolower($query));
        }

        $this->assertSame(AppointmentStatus::CheckedIn, $appointment->fresh()->status);
        $this->assertSame($updatedAt, $appointment->fresh()->updated_at->toDateTimeString());
        $this->assertSame(0, AppointmentStatusHistory::query()->count());
        $this->assertSame(0, AuditLog::query()->where('action', 'appointment.status_changed')->count());
    }

    public function test_the_now_argument_overrides_the_clock_and_is_read_in_the_application_timezone(): void
    {
        $appointment = $this->appointment(AppointmentStatus::Confirmed);
        $staff = $this->actorFor('staff', $appointment);

        // Jam dinding masih Senin 08.00, tetapi $now = Selasa 08.00 WIB: check-in sudah dibuka.
        $this->assertTrue($this->check($this->checks($appointment, $staff, CarbonImmutable::parse('2026-10-06 08:00:00')), AppointmentStatus::CheckedIn)->allowed());
        $this->assertSame('CHECKIN_NOT_TODAY', $this->check($this->checks($appointment, $staff), AppointmentStatus::CheckedIn)->errorCode);

        // 01.00 UTC = 08.00 WIB (diterima); 00.59 UTC = 07.59 WIB (belum dibuka).
        $this->assertTrue($this->check($this->checks($appointment, $staff, CarbonImmutable::parse('2026-10-06 01:00:00', 'UTC')), AppointmentStatus::CheckedIn)->allowed());
        $this->assertSame('CHECKIN_NOT_OPEN', $this->check($this->checks($appointment, $staff, CarbonImmutable::parse('2026-10-06 00:59:00', 'UTC')), AppointmentStatus::CheckedIn)->errorCode);

        $this->assertSame(
            [AppointmentStatus::CheckedIn, AppointmentStatus::Cancelled],
            $this->available($appointment, $staff, CarbonImmutable::parse('2026-10-06 08:00:00')),
        );
    }

    // ------------------------------------------------------------------ kesetaraan dengan transition()

    /** @return array<string, array{0: string, 1: string}> */
    public static function equivalenceCases(): array
    {
        $cases = [];

        foreach (['pending', 'confirmed', 'checked_in'] as $from) {
            foreach (['owner', 'stranger', 'staff', 'system'] as $kind) {
                $cases["{$kind}: {$from}"] = [$from, $kind];
            }
        }

        return $cases;
    }

    #[DataProvider('equivalenceCases')]
    public function test_every_check_agrees_with_transition_on_allowed_error_code_and_message(string $from, string $kind): void
    {
        Notification::fake();
        $appointment = $this->appointment(AppointmentStatus::from($from));
        $actor = $this->actorFor($kind, $appointment);
        $compared = 0;

        foreach (self::MOMENTS as $moment) {
            $this->at($moment);
            $checks = $this->checks($appointment, $actor);

            $this->assertNotSame([], $checks, "{$kind} {$from} {$moment}");

            foreach ($checks as $check) {
                $label = "{$kind} {$from} -> {$check->to->value} @ {$moment}";
                $thrown = $this->attemptRolledBack($appointment->id, $check->to, $actor);
                $compared++;

                if ($check->allowed()) {
                    $this->assertNull($thrown, "Tombol tampil tetapi server menolak ({$label}): ".($thrown?->errorCode ?? ''));
                    $this->assertNull($check->errorCode, $label);
                    $this->assertNull($check->message, $label);

                    continue;
                }

                $this->assertNotNull($thrown, "Tombol ditolak tetapi server menerima ({$label}).");
                $this->assertSame($check->errorCode, $thrown->errorCode, $label);
                $this->assertSame($check->message, $thrown->getMessage(), $label);
            }

            $allowed = array_values(array_filter($checks, fn (TransitionCheck $check): bool => $check->allowed()));
            $this->assertSame(
                $this->targets($allowed),
                $this->values($this->available($appointment, $actor)),
                "availableTransitions sama dengan pemeriksaan yang allowed(): {$kind} {$from} @ {$moment}",
            );
        }

        $this->assertGreaterThan(count(self::MOMENTS), $compared);
        $this->assertSame(AppointmentStatus::from($from), $appointment->fresh()->status, 'Tidak ada tulisan yang tersisa.');
        $this->assertSame(0, AppointmentStatusHistory::query()->count());
    }

    // ------------------------------------------------------------------ DeadlockException (F7A-R5)

    public function test_a_deadlock_exception_from_a_nested_transaction_becomes_system_busy(): void
    {
        $appointment = $this->appointment(AppointmentStatus::Confirmed);
        $staff = $this->actorFor('staff', $appointment);
        $this->at('2026-10-06 10:00:00');
        Exceptions::fake();

        // Di dalam transaksi tes (level > 1) Laravel tidak mengulang dan melempar DeadlockException.
        DB::connection()->beforeExecuting(function (string $query, array $bindings): void {
            if (str_contains(strtolower($query), 'insert into `appointment_status_histories`')) {
                $driver = new PDOException('SQLSTATE[40001]: Serialization failure: 1213 Deadlock found when trying to get lock; try restarting transaction');
                $driver->errorInfo = ['40001', 1213, 'Deadlock found when trying to get lock; try restarting transaction'];

                throw new QueryException('mariadb', $query, $bindings, $driver);
            }
        });

        $e = $this->failure(fn () => $this->service->transition($appointment, AppointmentStatus::Cancelled, $staff, 'Dibatalkan petugas uji'));

        $this->assertNotInstanceOf(InvalidTransitionException::class, $e);
        $this->assertSame(BookingException::SYSTEM_BUSY, $e->errorCode);
        $this->assertSame('Sistem sedang sibuk, silakan coba lagi beberapa saat.', $e->getMessage());
        $this->assertInstanceOf(DeadlockException::class, $e->getPrevious());

        Exceptions::assertReportedCount(1);
        Exceptions::assertReported(fn (SanitizedBookingFault $fault): bool => str_contains($fault->getMessage(), 'transisi status')
            && str_contains($fault->getMessage(), 'driver=1213')
            && ! str_contains($fault->getMessage(), 'insert into'));
    }
}
