<?php

namespace Tests\Feature\Booking;

use App\Enums\ActorType;
use App\Enums\AppointmentStatus;
use App\Enums\PaymentType;
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
use App\Services\Settings\SettingsService;
use App\Support\Actor;
use Carbon\CarbonImmutable;
use Database\Seeders\SettingsSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InteractsWithSchedules;
use Tests\TestCase;

/**
 * F6-R15, tabel F.8 T4-T10 (BKG-10..BKG-13, NTF-01/03/07). Waktu dibekukan dengan travelTo():
 * "sekarang" awal = Senin 2026-10-05 08.00 WIB; sesi uji = Selasa 2026-10-06 09.00-12.00, kuota 20 (cukup untuk 12 appointment pada tes ke-12 kode),
 * sehingga batas pembatalan pasien (120 menit) = 07.00, jendela check-in dibuka 08.00 dan ditutup 12.00.
 * Transaksi tes membungkus service (savepoint): pembuktian pengulangan/SYSTEM_BUSY nyata ada di T6.
 */
class AppointmentStatusServiceTest extends TestCase
{
    use InteractsWithSchedules;
    use RefreshDatabase;

    /** Ke-12 kode InvalidTransitionException (00 F.5). */
    private const ALL_CODES = [
        'INVALID_TRANSITION', 'ALREADY_FINAL', 'FORBIDDEN_TRANSITION', 'STALE_STATUS', 'CANCEL_CUTOFF_PASSED',
        'CONFIRM_AFTER_END', 'CHECKIN_NOT_TODAY', 'CHECKIN_NOT_OPEN', 'CHECKIN_CLOSED', 'CORRECTION_NOT_SAME_DAY',
        'SESSION_NOT_ENDED', 'REASON_REQUIRED',
    ];

    private const BR79 = 'Pendaftaran yang sudah check-in tidak dapat dibatalkan. Silakan hubungi petugas pendaftaran.';

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

    /**
     * Appointment pada sesi uji (pencacah sesi ikut dijaga lewat InteractsWithSchedules::bookAppointment()).
     * Pasien berakun kecuali $withAccount = false (walk-in).
     *
     * @param  array<string, mixed>  $overrides
     */
    private function appointment(
        AppointmentStatus $status = AppointmentStatus::Confirmed,
        array $overrides = [],
        bool $withAccount = true,
        ?PracticeSession $session = null,
    ): Appointment {
        $profile = $withAccount
            ? PatientProfile::factory()->withAccount()->create()
            : PatientProfile::factory()->create();

        return $this->bookAppointment($session ?? $this->session, $status, $overrides + ['patient_profile_id' => $profile->id]);
    }

    private function ownerOf(Appointment $appointment): Actor
    {
        $userId = PatientProfile::query()->whereKey($appointment->patient_profile_id)->value('user_id');

        return Actor::patient(User::query()->findOrFail($userId));
    }

    private function staff(): Actor
    {
        return Actor::staff(User::factory()->staff()->create());
    }

    private function actorFor(string $kind, Appointment $appointment): Actor
    {
        return match ($kind) {
            'patient' => $this->ownerOf($appointment),
            'staff' => $this->staff(),
            default => Actor::system(),
        };
    }

    private function booked(?PracticeSession $session = null): int
    {
        return (int) PracticeSession::query()->whereKey(($session ?? $this->session)->id)->value('booked_count');
    }

    private function lastQueue(?PracticeSession $session = null): int
    {
        return (int) PracticeSession::query()->whereKey(($session ?? $this->session)->id)->value('last_queue_number');
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

    private function assertHistory(Appointment $appointment, ?AppointmentStatus $from, AppointmentStatus $to, ActorType $actor, ?int $userId, ?string $reason): void
    {
        $rows = AppointmentStatusHistory::query()->where('appointment_id', $appointment->id)->get();

        $this->assertCount(1, $rows);
        $this->assertSame($from, $rows[0]->from_status);
        $this->assertSame($to, $rows[0]->to_status);
        $this->assertSame($actor, $rows[0]->actor_type);
        $this->assertSame($userId, $rows[0]->actor_user_id === null ? null : (int) $rows[0]->actor_user_id);
        $this->assertSame($reason, $rows[0]->reason);
    }

    // ------------------------------------------------------------------ transisi sah dan kolom waktunya (BKG-12)

    public function test_t4_staff_confirms_a_pending_appointment_before_the_session_ends(): void
    {
        $appointment = $this->appointment(AppointmentStatus::Pending);
        $staff = User::factory()->staff()->create();
        $this->at('2026-10-05 09:30:00');

        $result = $this->service->transition($appointment, AppointmentStatus::Confirmed, Actor::staff($staff));

        $fresh = $appointment->fresh();
        $this->assertSame(AppointmentStatus::Confirmed, $fresh->status);
        $this->assertSame('2026-10-05 09:30:00', $fresh->confirmed_at->toDateTimeString());
        $this->assertNull($fresh->checked_in_at);
        $this->assertNull($fresh->completed_at);
        $this->assertNull($fresh->cancelled_at);
        $this->assertNull($fresh->no_show_at);
        $this->assertSame(1, $fresh->active_flag);
        $this->assertSame(1, $this->booked());
        $this->assertSame(AppointmentStatus::Confirmed, $result->status);
        $this->assertHistory($appointment, AppointmentStatus::Pending, AppointmentStatus::Confirmed, ActorType::Staff, $staff->id, null);
    }

    public function test_t5_staff_checks_in_a_confirmed_appointment_and_keeps_confirmed_at(): void
    {
        $appointment = $this->appointment(AppointmentStatus::Confirmed);
        $this->at('2026-10-06 08:30:00');

        $this->service->transition($appointment, AppointmentStatus::CheckedIn, $this->staff());

        $fresh = $appointment->fresh();
        $this->assertSame(AppointmentStatus::CheckedIn, $fresh->status);
        $this->assertSame('2026-10-06 08:30:00', $fresh->checked_in_at->toDateTimeString());
        $this->assertSame('2026-10-05 08:00:00', $fresh->confirmed_at->toDateTimeString());
        $this->assertNull($fresh->completed_at);
        $this->assertSame(1, $this->booked());
    }

    public function test_t5_checking_in_a_pending_appointment_also_fills_confirmed_at(): void
    {
        $appointment = $this->appointment(AppointmentStatus::Pending);
        $this->at('2026-10-06 08:30:00');

        $this->service->transition($appointment, AppointmentStatus::CheckedIn, $this->staff());

        $fresh = $appointment->fresh();
        $this->assertSame(AppointmentStatus::CheckedIn, $fresh->status);
        $this->assertSame('2026-10-06 08:30:00', $fresh->checked_in_at->toDateTimeString());
        $this->assertSame('2026-10-06 08:30:00', $fresh->confirmed_at->toDateTimeString());
    }

    public function test_t6_staff_completes_a_checked_in_appointment(): void
    {
        $appointment = $this->appointment(AppointmentStatus::CheckedIn);
        $this->at('2026-10-06 10:30:00');

        $this->service->transition($appointment, AppointmentStatus::Completed, $this->staff());

        $fresh = $appointment->fresh();
        $this->assertSame(AppointmentStatus::Completed, $fresh->status);
        $this->assertSame('2026-10-06 10:30:00', $fresh->completed_at->toDateTimeString());
        $this->assertNotNull($fresh->checked_in_at);
        $this->assertNull($fresh->active_flag);
        $this->assertSame(1, $this->booked());
    }

    public function test_t7_staff_corrects_a_check_in_on_the_session_day_with_a_reason(): void
    {
        $appointment = $this->appointment(AppointmentStatus::CheckedIn);
        $staff = User::factory()->staff()->create();
        $this->at('2026-10-06 09:30:00');

        $this->service->transition($appointment, AppointmentStatus::Confirmed, Actor::staff($staff), 'Salah memilih pasien');

        $fresh = $appointment->fresh();
        $this->assertSame(AppointmentStatus::Confirmed, $fresh->status);
        $this->assertNull($fresh->checked_in_at);
        $this->assertSame('2026-10-05 08:00:00', $fresh->confirmed_at->toDateTimeString());
        $this->assertSame(1, $this->booked());
        $this->assertHistory($appointment, AppointmentStatus::CheckedIn, AppointmentStatus::Confirmed, ActorType::Staff, $staff->id, 'Salah memilih pasien');
    }

    /** @return array<string, array{0: AppointmentStatus}> */
    public static function cancellableStatuses(): array
    {
        return [
            'pending' => [AppointmentStatus::Pending],
            'confirmed' => [AppointmentStatus::Confirmed],
        ];
    }

    #[DataProvider('cancellableStatuses')]
    public function test_t8_patient_cancels_before_the_cutoff_and_the_seat_is_released(AppointmentStatus $from): void
    {
        $appointment = $this->appointment($from);
        $other = $this->appointment(AppointmentStatus::Confirmed);
        $owner = $this->ownerOf($appointment);
        $this->at('2026-10-05 20:00:00');

        $this->service->transition($appointment, AppointmentStatus::Cancelled, $owner, 'Berhalangan hadir');

        $fresh = $appointment->fresh();
        $this->assertSame(AppointmentStatus::Cancelled, $fresh->status);
        $this->assertSame('2026-10-05 20:00:00', $fresh->cancelled_at->toDateTimeString());
        $this->assertSame(ActorType::Patient, $fresh->cancelled_by_type);
        $this->assertSame($owner->userId, (int) $fresh->cancelled_by_user_id);
        $this->assertSame('Berhalangan hadir', $fresh->cancel_reason);
        $this->assertNull($fresh->active_flag);
        $this->assertSame(1, $this->booked());
        $this->assertSame(2, $this->lastQueue());
        $this->assertSame(AppointmentStatus::Confirmed, $other->fresh()->status);
        $this->assertHistory($appointment, $from, AppointmentStatus::Cancelled, ActorType::Patient, $owner->userId, 'Berhalangan hadir');
    }

    /** @return array<string, array{0: string}> */
    public static function staffAndSystem(): array
    {
        return ['staf' => ['staff'], 'sistem' => ['system']];
    }

    #[DataProvider('staffAndSystem')]
    public function test_t9_staff_or_system_cancels_at_any_time_before_check_in_with_a_reason(string $kind): void
    {
        $appointment = $this->appointment(AppointmentStatus::Confirmed);
        $actor = $this->actorFor($kind, $appointment);
        $this->at('2026-10-06 11:00:00');

        $this->service->transition($appointment, AppointmentStatus::Cancelled, $actor, 'Dokter berhalangan hadir');

        $fresh = $appointment->fresh();
        $this->assertSame(AppointmentStatus::Cancelled, $fresh->status);
        $this->assertSame($actor->type, $fresh->cancelled_by_type);
        $this->assertSame($actor->userId, $fresh->cancelled_by_user_id === null ? null : (int) $fresh->cancelled_by_user_id);
        $this->assertSame('Dokter berhalangan hadir', $fresh->cancel_reason);
        $this->assertSame(0, $this->booked());
        $this->assertSame(1, $this->lastQueue());
    }

    /** @return array<string, array{0: AppointmentStatus, 1: string}> */
    public static function noShowCases(): array
    {
        return [
            'pending oleh staf' => [AppointmentStatus::Pending, 'staff'],
            'confirmed oleh staf' => [AppointmentStatus::Confirmed, 'staff'],
            'pending oleh sistem' => [AppointmentStatus::Pending, 'system'],
            'confirmed oleh sistem' => [AppointmentStatus::Confirmed, 'system'],
        ];
    }

    #[DataProvider('noShowCases')]
    public function test_t10_staff_or_system_marks_no_show_after_the_session_ended(AppointmentStatus $from, string $kind): void
    {
        $appointment = $this->appointment($from);
        $actor = $this->actorFor($kind, $appointment);
        $before = $appointment->fresh()->confirmed_at;
        $this->at('2026-10-06 12:00:01');

        $this->service->transition($appointment, AppointmentStatus::NoShow, $actor);

        $fresh = $appointment->fresh();
        $this->assertSame(AppointmentStatus::NoShow, $fresh->status);
        $this->assertSame('2026-10-06 12:00:01', $fresh->no_show_at->toDateTimeString());
        $this->assertSame($before?->toDateTimeString(), $fresh->confirmed_at?->toDateTimeString());
        $this->assertNull($fresh->checked_in_at);
        $this->assertNull($fresh->cancelled_at);
        $this->assertSame(1, $this->booked());
    }

    // ------------------------------------------------------------------ matriks lengkap (BKG-12)

    /** @return \Generator<string, array{0: string, 1: string, 2: string}> */
    public static function matrix(): \Generator
    {
        foreach (['patient', 'staff', 'system'] as $actor) {
            foreach (AppointmentStatus::cases() as $from) {
                foreach (AppointmentStatus::cases() as $to) {
                    yield "{$actor}: {$from->value} -> {$to->value}" => [$from->value, $to->value, $actor];
                }
            }
        }
    }

    /** Oracle tabel F.8 yang ditulis tangan, sengaja TIDAK memakai konstanta service. */
    private static function expectedOutcome(string $from, string $to, string $actor): string
    {
        if (in_array($from, ['completed', 'cancelled', 'no_show'], true)) {
            return 'ALREADY_FINAL';
        }

        $targets = ['patient' => ['cancelled'], 'system' => ['cancelled', 'no_show']];

        if ($actor !== 'staff' && ! in_array($to, $targets[$actor], true)) {
            return 'FORBIDDEN_TRANSITION';
        }

        $legal = [
            'pending' => ['confirmed', 'checked_in', 'cancelled', 'no_show'],
            'confirmed' => ['checked_in', 'cancelled', 'no_show'],
            'checked_in' => ['completed', 'confirmed'],
        ];

        return in_array($to, $legal[$from], true) ? 'ok' : 'INVALID_TRANSITION';
    }

    /** Saat yang memenuhi jendela waktu pasangan itu (agar yang diuji hanya struktur tabel). */
    private static function matrixMoment(string $from, string $to): string
    {
        return match (true) {
            $to === 'cancelled' => '2026-10-05 08:00:00',
            $to === 'no_show' => '2026-10-06 12:30:00',
            $to === 'confirmed' && $from === 'pending' => '2026-10-05 08:00:00',
            default => '2026-10-06 10:00:00',
        };
    }

    #[DataProvider('matrix')]
    public function test_bkg_12_every_from_to_actor_pair_follows_table_f8(string $from, string $to, string $kind): void
    {
        $fromStatus = AppointmentStatus::from($from);
        $toStatus = AppointmentStatus::from($to);
        $appointment = $this->appointment($fromStatus);
        $actor = $this->actorFor($kind, $appointment);
        $bookedBefore = $this->booked();
        $this->at(self::matrixMoment($from, $to));

        $expected = self::expectedOutcome($from, $to, $kind);

        if ($expected === 'ok') {
            $this->service->transition($appointment, $toStatus, $actor, 'Alasan uji matriks');

            $this->assertSame($toStatus, $appointment->fresh()->status);
            $this->assertSame($toStatus === AppointmentStatus::Cancelled ? $bookedBefore - 1 : $bookedBefore, $this->booked());

            return;
        }

        $this->assertFails($expected, fn () => $this->service->transition($appointment, $toStatus, $actor, 'Alasan uji matriks'));

        $this->assertSame($fromStatus, $appointment->fresh()->status);
        $this->assertSame($bookedBefore, $this->booked());
        $this->assertSame(0, AppointmentStatusHistory::query()->count());
    }

    // ------------------------------------------------------------------ transisi tidak sah

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
    public function test_a_final_appointment_cannot_change_again(AppointmentStatus $final): void
    {
        $appointment = $this->appointment($final);
        $bookedBefore = $this->booked();

        $e = $this->assertFails('ALREADY_FINAL', fn () => $this->service->transition($appointment, AppointmentStatus::Confirmed, $this->staff()));

        $this->assertStringContainsString(mb_strtolower($final->label()), $e->getMessage());
        $this->assertSame($final, $appointment->fresh()->status);
        $this->assertSame($bookedBefore, $this->booked());
    }

    /** @return array<string, array{0: AppointmentStatus}> */
    public static function nonCancelTargets(): array
    {
        return [
            'confirmed' => [AppointmentStatus::Confirmed],
            'checked_in' => [AppointmentStatus::CheckedIn],
            'completed' => [AppointmentStatus::Completed],
            'no_show' => [AppointmentStatus::NoShow],
            'pending' => [AppointmentStatus::Pending],
        ];
    }

    #[DataProvider('nonCancelTargets')]
    public function test_a_patient_may_only_cancel(AppointmentStatus $to): void
    {
        $appointment = $this->appointment(AppointmentStatus::Pending);
        $owner = $this->ownerOf($appointment);
        $this->at('2026-10-06 10:00:00');

        $this->assertFails('FORBIDDEN_TRANSITION', fn () => $this->service->transition($appointment, $to, $owner));

        $this->assertSame(AppointmentStatus::Pending, $appointment->fresh()->status);
    }

    public function test_a_patient_who_does_not_own_the_appointment_is_forbidden_even_to_cancel(): void
    {
        $appointment = $this->appointment(AppointmentStatus::Confirmed);
        $stranger = $this->ownerOf($this->appointment(AppointmentStatus::Confirmed));

        $this->assertFails('FORBIDDEN_TRANSITION', fn () => $this->service->transition($appointment, AppointmentStatus::Cancelled, $stranger));

        $this->assertSame(AppointmentStatus::Confirmed, $appointment->fresh()->status);
        $this->assertSame(2, $this->booked());
    }

    /** @return array<string, array{0: AppointmentStatus}> */
    public static function systemForbiddenTargets(): array
    {
        return [
            'confirmed' => [AppointmentStatus::Confirmed],
            'checked_in' => [AppointmentStatus::CheckedIn],
            'completed' => [AppointmentStatus::Completed],
        ];
    }

    #[DataProvider('systemForbiddenTargets')]
    public function test_the_system_actor_may_only_cancel_or_mark_no_show(AppointmentStatus $to): void
    {
        $appointment = $this->appointment(AppointmentStatus::Pending);
        $this->at('2026-10-06 10:00:00');

        $this->assertFails('FORBIDDEN_TRANSITION', fn () => $this->service->transition($appointment, $to, Actor::system(), 'Alasan sistem'));
    }

    public function test_a_patient_cancelling_a_checked_in_appointment_gets_the_br_79_message(): void
    {
        $appointment = $this->appointment(AppointmentStatus::CheckedIn);
        $owner = $this->ownerOf($appointment);
        $this->at('2026-10-05 08:30:00');

        $e = $this->assertFails('INVALID_TRANSITION', fn () => $this->service->transition($appointment, AppointmentStatus::Cancelled, $owner));

        $this->assertSame(self::BR79, $e->getMessage());
        $this->assertSame(AppointmentStatus::CheckedIn, $appointment->fresh()->status);
        $this->assertSame(1, $this->booked());
    }

    public function test_staff_cancelling_a_checked_in_appointment_gets_the_generic_invalid_transition_message(): void
    {
        $appointment = $this->appointment(AppointmentStatus::CheckedIn);
        $this->at('2026-10-06 10:00:00');

        $e = $this->assertFails('INVALID_TRANSITION', fn () => $this->service->transition($appointment, AppointmentStatus::Cancelled, $this->staff(), 'Salah catat'));

        $this->assertNotSame(self::BR79, $e->getMessage());
        $this->assertStringContainsString(AppointmentStatus::CheckedIn->label(), $e->getMessage());
        $this->assertStringContainsString(AppointmentStatus::Cancelled->label(), $e->getMessage());
    }

    /** @return array<string, array{0: AppointmentStatus, 1: AppointmentStatus}> */
    public static function illegalPairs(): array
    {
        return [
            'pending ke completed' => [AppointmentStatus::Pending, AppointmentStatus::Completed],
            'pending ke pending' => [AppointmentStatus::Pending, AppointmentStatus::Pending],
            'confirmed ke completed' => [AppointmentStatus::Confirmed, AppointmentStatus::Completed],
            'confirmed ke pending' => [AppointmentStatus::Confirmed, AppointmentStatus::Pending],
            'checked_in ke no_show' => [AppointmentStatus::CheckedIn, AppointmentStatus::NoShow],
        ];
    }

    #[DataProvider('illegalPairs')]
    public function test_staff_cannot_make_an_illegal_jump(AppointmentStatus $from, AppointmentStatus $to): void
    {
        $appointment = $this->appointment($from);
        $this->at('2026-10-06 12:30:00');

        $e = $this->assertFails('INVALID_TRANSITION', fn () => $this->service->transition($appointment, $to, $this->staff(), 'Alasan uji'));

        $this->assertStringContainsString($from->label(), $e->getMessage());
        $this->assertStringContainsString($to->label(), $e->getMessage());
        $this->assertSame($from, $appointment->fresh()->status);
    }

    // ------------------------------------------------------------------ batas waktu tepat

    public function test_t8_patient_cancel_deadline_is_inclusive_at_start_minus_120_and_exclusive_one_minute_later(): void
    {
        $onTime = $this->appointment(AppointmentStatus::Confirmed);
        $late = $this->appointment(AppointmentStatus::Confirmed);
        $oneSecondLate = $this->appointment(AppointmentStatus::Confirmed);

        $this->at('2026-10-06 07:00:00');
        $this->service->transition($onTime, AppointmentStatus::Cancelled, $this->ownerOf($onTime), 'Berhalangan hadir');
        $this->assertSame(AppointmentStatus::Cancelled, $onTime->fresh()->status);
        $this->assertSame(2, $this->booked());

        $this->at('2026-10-06 07:00:01');
        $this->assertFails('CANCEL_CUTOFF_PASSED', fn () => $this->service->transition($oneSecondLate, AppointmentStatus::Cancelled, $this->ownerOf($oneSecondLate)));

        $this->at('2026-10-06 07:01:00');
        $e = $this->assertFails('CANCEL_CUTOFF_PASSED', fn () => $this->service->transition($late, AppointmentStatus::Cancelled, $this->ownerOf($late), 'Terlambat'));

        $this->assertStringContainsString('120', $e->getMessage());
        $this->assertStringContainsString('(021) 0000-0000', $e->getMessage());
        $this->assertSame(AppointmentStatus::Confirmed, $late->fresh()->status);
        $this->assertNull($late->fresh()->cancelled_at);
        $this->assertSame(2, $this->booked());
        $this->assertSame(1, AppointmentStatusHistory::query()->count());
    }

    /** FX6: tanpa nomor telepon rumah sakit pesan tidak boleh berlubang ("di  atau melalui WhatsApp"). */
    public function test_the_cancel_cutoff_message_names_the_hospital_number_when_the_phone_setting_is_empty(): void
    {
        foreach (['', '   '] as $blank) {
            $this->app->make(SettingsService::class)->setMany(['contact.phone' => $blank]);
            $late = $this->appointment(AppointmentStatus::Confirmed);
            $this->at('2026-10-06 07:01:00');

            $e = $this->assertFails('CANCEL_CUTOFF_PASSED', fn () => $this->service->transition($late, AppointmentStatus::Cancelled, $this->ownerOf($late), 'Terlambat'));

            $this->assertStringContainsString('hubungi rumah sakit di nomor rumah sakit atau melalui WhatsApp.', $e->getMessage());
            $this->assertStringNotContainsString('  ', $e->getMessage());
        }
    }

    public function test_the_patient_cancel_deadline_follows_the_booking_setting(): void
    {
        $this->app->make(SettingsService::class)->setMany(['booking.cancel_cutoff_minutes' => 60]);
        $this->app->make(SettingsService::class)->forget();
        $service = $this->app->make(AppointmentStatusService::class);

        $ok = $this->appointment(AppointmentStatus::Pending);
        $late = $this->appointment(AppointmentStatus::Pending);

        $this->at('2026-10-06 07:59:00');
        $service->transition($ok, AppointmentStatus::Cancelled, $this->ownerOf($ok));
        $this->assertSame(AppointmentStatus::Cancelled, $ok->fresh()->status);

        $this->at('2026-10-06 08:01:00');
        $e = $this->assertFails('CANCEL_CUTOFF_PASSED', fn () => $service->transition($late, AppointmentStatus::Cancelled, $this->ownerOf($late)));
        $this->assertStringContainsString('60', $e->getMessage());
    }

    public function test_t4_confirming_is_rejected_from_the_exact_end_of_the_session(): void
    {
        $early = $this->appointment(AppointmentStatus::Pending);
        $late = $this->appointment(AppointmentStatus::Pending);

        $this->at('2026-10-06 11:59:59');
        $this->service->transition($early, AppointmentStatus::Confirmed, $this->staff());
        $this->assertSame(AppointmentStatus::Confirmed, $early->fresh()->status);

        $this->at('2026-10-06 12:00:00');
        $this->assertFails('CONFIRM_AFTER_END', fn () => $this->service->transition($late, AppointmentStatus::Confirmed, $this->staff()));
        $this->assertSame(AppointmentStatus::Pending, $late->fresh()->status);
        $this->assertNull($late->fresh()->confirmed_at);
    }

    public function test_t5_check_in_window_opens_at_start_minus_60_and_closes_at_the_exact_end(): void
    {
        $opening = $this->appointment(AppointmentStatus::Confirmed);
        $closing = $this->appointment(AppointmentStatus::Confirmed);
        $tooEarly = $this->appointment(AppointmentStatus::Confirmed);
        $tooLate = $this->appointment(AppointmentStatus::Confirmed);
        $wrongDay = $this->appointment(AppointmentStatus::Confirmed);

        $this->at('2026-10-06 07:59:00');
        $e = $this->assertFails('CHECKIN_NOT_OPEN', fn () => $this->service->transition($tooEarly, AppointmentStatus::CheckedIn, $this->staff()));
        $this->assertStringContainsString('08.00', $e->getMessage());

        $this->at('2026-10-06 08:00:00');
        $this->service->transition($opening, AppointmentStatus::CheckedIn, $this->staff());
        $this->assertSame(AppointmentStatus::CheckedIn, $opening->fresh()->status);

        $this->at('2026-10-06 12:00:00');
        $this->service->transition($closing, AppointmentStatus::CheckedIn, $this->staff());
        $this->assertSame(AppointmentStatus::CheckedIn, $closing->fresh()->status);

        $this->at('2026-10-06 12:00:01');
        $this->assertFails('CHECKIN_CLOSED', fn () => $this->service->transition($tooLate, AppointmentStatus::CheckedIn, $this->staff()));

        $this->at('2026-10-05 10:00:00');
        $e = $this->assertFails('CHECKIN_NOT_TODAY', fn () => $this->service->transition($wrongDay, AppointmentStatus::CheckedIn, $this->staff()));
        $this->assertStringContainsString($this->session->session_date->translatedFormat('l, j F Y'), $e->getMessage());

        $this->at('2026-10-07 10:00:00');
        $this->assertFails('CHECKIN_NOT_TODAY', fn () => $this->service->transition($wrongDay, AppointmentStatus::CheckedIn, $this->staff()));

        $this->assertSame(AppointmentStatus::Confirmed, $tooEarly->fresh()->status);
        $this->assertSame(AppointmentStatus::Confirmed, $tooLate->fresh()->status);
        $this->assertSame(AppointmentStatus::Confirmed, $wrongDay->fresh()->status);
    }

    public function test_t7_correction_is_rejected_on_another_day_than_the_session(): void
    {
        $appointment = $this->appointment(AppointmentStatus::CheckedIn);
        $this->at('2026-10-05 12:00:00');

        $this->assertFails('CORRECTION_NOT_SAME_DAY', fn () => $this->service->transition($appointment, AppointmentStatus::Confirmed, $this->staff(), 'Salah check-in'));

        $fresh = $appointment->fresh();
        $this->assertSame(AppointmentStatus::CheckedIn, $fresh->status);
        $this->assertNotNull($fresh->checked_in_at);
    }

    public function test_t10_no_show_is_rejected_until_after_the_exact_end_of_the_session(): void
    {
        $appointment = $this->appointment(AppointmentStatus::Confirmed);

        $this->at('2026-10-05 12:30:00');
        $this->assertFails('SESSION_NOT_ENDED', fn () => $this->service->transition($appointment, AppointmentStatus::NoShow, $this->staff()));

        $this->at('2026-10-06 12:00:00');
        $e = $this->assertFails('SESSION_NOT_ENDED', fn () => $this->service->transition($appointment, AppointmentStatus::NoShow, $this->staff()));
        $this->assertStringContainsString('12.00', $e->getMessage());
        $this->assertSame(AppointmentStatus::Confirmed, $appointment->fresh()->status);
        $this->assertNull($appointment->fresh()->no_show_at);

        $this->at('2026-10-06 12:00:01');
        $this->service->transition($appointment, AppointmentStatus::NoShow, Actor::system());
        $this->assertSame(AppointmentStatus::NoShow, $appointment->fresh()->status);
    }

    // ------------------------------------------------------------------ alasan

    /** @return array<string, array{0: ?string}> */
    public static function invalidStaffReasons(): array
    {
        return [
            'null' => [null],
            'kosong' => [''],
            'hanya spasi' => ['      '],
            'empat karakter' => ['abcd'],
            'empat karakter berspasi' => ['  abcd  '],
            '256 karakter' => [str_repeat('a', 256)],
        ];
    }

    #[DataProvider('invalidStaffReasons')]
    public function test_staff_cancelling_requires_a_reason_of_five_to_255_characters(?string $reason): void
    {
        $appointment = $this->appointment(AppointmentStatus::Confirmed);
        $this->at('2026-10-06 10:00:00');

        $this->assertFails('REASON_REQUIRED', fn () => $this->service->transition($appointment, AppointmentStatus::Cancelled, $this->staff(), $reason));

        $this->assertSame(AppointmentStatus::Confirmed, $appointment->fresh()->status);
        $this->assertSame(1, $this->booked());
    }

    public function test_the_system_cancelling_and_the_check_in_correction_also_require_a_reason(): void
    {
        $confirmed = $this->appointment(AppointmentStatus::Confirmed);
        $checkedIn = $this->appointment(AppointmentStatus::CheckedIn);
        $this->at('2026-10-06 10:00:00');

        $this->assertFails('REASON_REQUIRED', fn () => $this->service->transition($confirmed, AppointmentStatus::Cancelled, Actor::system()));
        $this->assertFails('REASON_REQUIRED', fn () => $this->service->transition($checkedIn, AppointmentStatus::Confirmed, $this->staff()));
        $this->assertFails('REASON_REQUIRED', fn () => $this->service->transition($checkedIn, AppointmentStatus::Confirmed, $this->staff(), 'abc'));
    }

    public function test_reason_length_limits_are_inclusive_and_the_reason_is_trimmed(): void
    {
        $short = $this->appointment(AppointmentStatus::Confirmed);
        $long = $this->appointment(AppointmentStatus::Confirmed);
        $this->at('2026-10-06 10:00:00');

        $this->service->transition($short, AppointmentStatus::Cancelled, $this->staff(), '  abcde  ');
        $this->service->transition($long, AppointmentStatus::Cancelled, $this->staff(), str_repeat('b', 255));

        $this->assertSame('abcde', $short->fresh()->cancel_reason);
        $this->assertSame(255, mb_strlen($long->fresh()->cancel_reason));
    }

    public function test_the_patient_reason_is_optional_and_blank_becomes_null(): void
    {
        $withoutReason = $this->appointment(AppointmentStatus::Pending);
        $blank = $this->appointment(AppointmentStatus::Pending);

        $this->service->transition($withoutReason, AppointmentStatus::Cancelled, $this->ownerOf($withoutReason));
        $this->service->transition($blank, AppointmentStatus::Cancelled, $this->ownerOf($blank), '   ');

        $this->assertNull($withoutReason->fresh()->cancel_reason);
        $this->assertNull($blank->fresh()->cancel_reason);
        $this->assertSame(0, $this->booked());
        $this->assertNull(AppointmentStatusHistory::query()->where('appointment_id', $blank->id)->value('reason'));
    }

    // ------------------------------------------------------------------ STALE_STATUS (expected)

    public function test_a_mismatching_expected_status_is_stale_and_changes_nothing(): void
    {
        $appointment = $this->appointment(AppointmentStatus::Confirmed);
        $this->at('2026-10-06 08:30:00');

        $e = $this->assertFails('STALE_STATUS', fn () => $this->service->transition($appointment, AppointmentStatus::CheckedIn, $this->staff(), null, AppointmentStatus::Pending));

        $this->assertStringContainsString('Muat ulang halaman', $e->getMessage());
        $this->assertSame(AppointmentStatus::Confirmed, $appointment->fresh()->status);
        $this->assertNull($appointment->fresh()->checked_in_at);
        $this->assertSame(0, AppointmentStatusHistory::query()->count());
    }

    public function test_a_matching_expected_status_lets_the_transition_through(): void
    {
        $appointment = $this->appointment(AppointmentStatus::Confirmed);
        $this->at('2026-10-06 08:30:00');

        $this->service->transition($appointment, AppointmentStatus::CheckedIn, $this->staff(), null, AppointmentStatus::Confirmed);

        $this->assertSame(AppointmentStatus::CheckedIn, $appointment->fresh()->status);
    }

    public function test_a_change_made_by_someone_else_after_the_page_was_loaded_is_stale_with_expected_and_final_without_it(): void
    {
        $appointment = $this->appointment(AppointmentStatus::Confirmed);
        $stale = Appointment::query()->findOrFail($appointment->id);

        // Petugas lain membatalkan lebih dulu (instance $stale masih memegang status lama).
        $this->at('2026-10-06 10:00:00');
        $this->service->transition($appointment, AppointmentStatus::Cancelled, $this->staff(), 'Dibatalkan petugas lain');

        $this->assertFails('STALE_STATUS', fn () => $this->service->transition($stale, AppointmentStatus::CheckedIn, $this->staff(), null, AppointmentStatus::Confirmed));
        $this->assertFails('ALREADY_FINAL', fn () => $this->service->transition($stale, AppointmentStatus::CheckedIn, $this->staff()));

        $this->assertSame(0, $this->booked());
        $this->assertSame(1, AppointmentStatusHistory::query()->count());
    }

    // ------------------------------------------------------------------ kuota dan antrean (BKG-10, BKG-13)

    public function test_cancelling_releases_one_seat_but_never_lowers_the_last_queue_number(): void
    {
        $first = $this->appointment(AppointmentStatus::Confirmed);
        $this->appointment(AppointmentStatus::Pending);
        $this->assertSame(2, $this->booked());
        $this->assertSame(2, $this->lastQueue());

        $this->service->transition($first, AppointmentStatus::Cancelled, $this->ownerOf($first));

        $this->assertSame(1, $this->booked());
        $this->assertSame(2, $this->lastQueue());

        $third = $this->appointment(AppointmentStatus::Pending);
        $this->assertSame(3, $third->queue_number);
        $this->assertSame(2, $this->booked());
        $this->assertSame(3, $this->lastQueue());
    }

    public function test_bkg_13_only_cancelling_changes_booked_count_and_a_second_cancel_is_rejected(): void
    {
        $appointment = $this->appointment(AppointmentStatus::Pending);
        $this->assertSame(1, $this->booked());

        $this->at('2026-10-05 08:00:00');
        $this->service->transition($appointment, AppointmentStatus::Confirmed, $this->staff());
        $this->assertSame(1, $this->booked());

        $this->at('2026-10-06 09:00:00');
        $this->service->transition($appointment, AppointmentStatus::CheckedIn, $this->staff());
        $this->assertSame(1, $this->booked());

        $this->service->transition($appointment, AppointmentStatus::Completed, $this->staff());
        $this->assertSame(1, $this->booked());
        $this->assertSame(1, $this->lastQueue());

        $other = $this->appointment(AppointmentStatus::Confirmed);
        $this->service->transition($other, AppointmentStatus::Cancelled, $this->staff(), 'Pasien menelepon');
        $this->assertSame(1, $this->booked());

        $this->assertFails('ALREADY_FINAL', fn () => $this->service->transition($other, AppointmentStatus::Cancelled, $this->staff(), 'Pasien menelepon lagi'));
        $this->assertSame(1, $this->booked());
        $this->assertSame(2, $this->lastQueue());
    }

    // ------------------------------------------------------------------ sesi berstatus lain tetap boleh dibatalkan

    /** @return array<string, array{0: string}> */
    public static function sessionStates(): array
    {
        return ['closed' => ['closed'], 'needs_review' => ['needs_review'], 'cancelled' => ['cancelled']];
    }

    #[DataProvider('sessionStates')]
    public function test_an_appointment_on_a_closed_review_or_cancelled_session_can_still_be_cancelled(string $state): void
    {
        $factory = PracticeSession::factory()->manual();
        $factory = match ($state) {
            'closed' => $factory->closed(),
            'needs_review' => $factory->needsReview(['source' => PracticeSession::SOURCE_EXCEPTION, 'exception_id' => 1]),
            default => $factory->cancelled(),
        };
        $session = $factory->create([
            'session_date' => '2026-10-06',
            'start_time' => '09:00:00',
            'end_time' => '12:00:00',
            'quota' => 3,
        ]);
        $appointment = $this->appointment(AppointmentStatus::Confirmed, [], true, $session);
        $owner = $this->ownerOf($appointment);

        $this->service->transition($appointment, AppointmentStatus::Cancelled, $owner, 'Tidak jadi berobat');

        $this->assertSame(AppointmentStatus::Cancelled, $appointment->fresh()->status);
        $this->assertSame(0, $this->booked($session));
        $this->assertSame(1, $this->lastQueue($session));
    }

    // ------------------------------------------------------------------ riwayat, audit, instance (F6-R15, F6-R35)

    public function test_each_transition_appends_exactly_one_history_row_in_order(): void
    {
        $appointment = $this->appointment(AppointmentStatus::Pending);
        $staff = $this->staff();

        $this->at('2026-10-05 09:00:00');
        $this->service->transition($appointment, AppointmentStatus::Confirmed, $staff);
        $this->at('2026-10-06 09:00:00');
        $this->service->transition($appointment, AppointmentStatus::CheckedIn, $staff);
        $this->at('2026-10-06 09:20:00');
        $this->service->transition($appointment, AppointmentStatus::Confirmed, $staff, 'Salah memilih pasien');

        $rows = $appointment->fresh()->statusHistories;

        $this->assertSame(
            [['pending', 'confirmed', null], ['confirmed', 'checked_in', null], ['checked_in', 'confirmed', 'Salah memilih pasien']],
            $rows->map(fn (AppointmentStatusHistory $row): array => [$row->from_status->value, $row->to_status->value, $row->reason])->all(),
        );
    }

    public function test_the_given_instance_and_the_returned_appointment_reflect_the_new_state(): void
    {
        $appointment = $this->appointment(AppointmentStatus::Confirmed);
        $owner = $this->ownerOf($appointment);

        $result = $this->service->transition($appointment, AppointmentStatus::Cancelled, $owner, 'Berhalangan hadir');

        $this->assertSame($appointment, $result);
        $this->assertSame(AppointmentStatus::Cancelled, $appointment->status);
        $this->assertNull($appointment->active_flag);
        $this->assertSame('Berhalangan hadir', $appointment->cancel_reason);
        $this->assertTrue($appointment->relationLoaded('practiceSession'));
        $this->assertSame(0, $appointment->practiceSession->booked_count);
        $this->assertTrue($appointment->relationLoaded('clinic'));
        $this->assertTrue($appointment->relationLoaded('doctor'));
        $this->assertTrue($appointment->relationLoaded('patientProfile'));
    }

    public function test_the_audit_row_records_the_status_change_without_personal_data(): void
    {
        $appointment = $this->appointment(AppointmentStatus::Confirmed, [
            'payment_type' => PaymentType::Bpjs,
            'payment_reference' => '0009900000011',
            'complaint' => '<script>alert(1)</script> nyeri dada rahasia',
        ]);
        $owner = $this->ownerOf($appointment);

        $this->service->transition($appointment, AppointmentStatus::Cancelled, $owner, 'Berhalangan hadir');

        $log = AuditLog::query()->where('action', 'appointment.status_changed')->sole();

        $this->assertSame($appointment->getMorphClass(), $log->auditable_type);
        $this->assertSame($appointment->id, (int) $log->auditable_id);
        $this->assertSame(ActorType::Patient, $log->actor_type);
        $this->assertSame($owner->userId, (int) $log->user_id);
        $this->assertSame(['status' => 'confirmed'], $log->old_values);
        $this->assertSame(['status' => 'cancelled', 'reason' => 'Berhalangan hadir'], $log->new_values);
        $this->assertStringContainsString($appointment->booking_code, $log->description);
        $this->assertStringContainsString('Dikonfirmasi', $log->description);
        $this->assertStringContainsString('Dibatalkan', $log->description);

        $stored = json_encode([$log->old_values, $log->new_values, $log->description]);
        $this->assertStringNotContainsString('0009900000011', $stored);
        $this->assertStringNotContainsString('rahasia', $stored);
        $this->assertStringNotContainsString('script', $stored);
    }

    public function test_the_audit_row_omits_the_reason_key_when_there_is_none(): void
    {
        $appointment = $this->appointment(AppointmentStatus::Pending);
        $this->at('2026-10-05 09:00:00');

        $this->service->transition($appointment, AppointmentStatus::Confirmed, $this->staff());

        $log = AuditLog::query()->where('action', 'appointment.status_changed')->sole();
        $this->assertSame(ActorType::Staff, $log->actor_type);
        $this->assertSame(['status' => 'pending'], $log->old_values);
        $this->assertSame(['status' => 'confirmed'], $log->new_values);
    }

    // ------------------------------------------------------------------ notifikasi (K-13, NTF-01/03/07)

    /** @return array<string, array{0: AppointmentStatus, 1: AppointmentStatus, 2: string, 3: list<string>}> */
    public static function notificationChannels(): array
    {
        $database = ['database'];
        $both = ['database', 'mail'];

        return [
            'dikonfirmasi petugas' => [AppointmentStatus::Pending, AppointmentStatus::Confirmed, 'staff', $both],
            'check-in dari confirmed' => [AppointmentStatus::Confirmed, AppointmentStatus::CheckedIn, 'staff', $database],
            'check-in dari pending' => [AppointmentStatus::Pending, AppointmentStatus::CheckedIn, 'staff', $database],
            'selesai' => [AppointmentStatus::CheckedIn, AppointmentStatus::Completed, 'staff', $database],
            'dibatalkan pasien' => [AppointmentStatus::Confirmed, AppointmentStatus::Cancelled, 'patient', $both],
            'dibatalkan petugas' => [AppointmentStatus::Pending, AppointmentStatus::Cancelled, 'staff', $both],
            'tidak hadir oleh petugas' => [AppointmentStatus::Confirmed, AppointmentStatus::NoShow, 'staff', $database],
            'tidak hadir oleh sistem' => [AppointmentStatus::Pending, AppointmentStatus::NoShow, 'system', $database],
        ];
    }

    /** @param list<string> $channels */
    #[DataProvider('notificationChannels')]
    public function test_ntf_07_database_for_every_transition_and_mail_only_for_confirmed_and_cancelled(AppointmentStatus $from, AppointmentStatus $to, string $kind, array $channels): void
    {
        $appointment = $this->appointment($from);
        $recipient = User::query()->findOrFail(PatientProfile::query()->whereKey($appointment->patient_profile_id)->value('user_id'));
        $actor = $this->actorFor($kind, $appointment);
        $this->at(self::matrixMoment($from->value, $to->value));
        Notification::fake();

        $this->service->transition($appointment, $to, $actor, 'Alasan uji notifikasi');

        Notification::assertSentToTimes($recipient, AppointmentStatusChangedNotification::class, 1);
        Notification::assertSentTo(
            $recipient,
            AppointmentStatusChangedNotification::class,
            fn (AppointmentStatusChangedNotification $notification, array $actual): bool => $actual === $channels
                && $notification->appointment->is($appointment)
                && $notification->from === $from
                && $notification->to === $to,
        );
    }

    public function test_the_check_in_correction_notifies_the_patient_on_the_database_channel_only(): void
    {
        $appointment = $this->appointment(AppointmentStatus::CheckedIn);
        $recipient = User::query()->findOrFail(PatientProfile::query()->whereKey($appointment->patient_profile_id)->value('user_id'));
        $this->at('2026-10-06 09:30:00');
        Notification::fake();

        $this->service->transition($appointment, AppointmentStatus::Confirmed, $this->staff(), 'Salah memilih pasien');

        Notification::assertSentTo(
            $recipient,
            AppointmentStatusChangedNotification::class,
            fn (AppointmentStatusChangedNotification $notification, array $channels): bool => $channels === ['database'],
        );
    }

    public function test_ntf_01_the_database_notification_holds_the_status_and_no_sensitive_data(): void
    {
        $appointment = $this->appointment(AppointmentStatus::Pending, [
            'payment_type' => PaymentType::Bpjs,
            'payment_reference' => '0009900000011',
            'complaint' => '<script>alert(1)</script> nyeri dada rahasia',
        ]);
        $recipient = User::query()->findOrFail(PatientProfile::query()->whereKey($appointment->patient_profile_id)->value('user_id'));
        $this->at('2026-10-05 09:00:00');

        $this->service->transition($appointment, AppointmentStatus::Confirmed, $this->staff());

        $notification = $recipient->notifications()->sole();
        $data = $notification->data;

        $this->assertSame($appointment->id, (int) $data['appointment_id']);
        $this->assertSame($appointment->booking_code, $data['booking_code']);
        $this->assertSame('confirmed', $data['status']);
        $this->assertArrayHasKey('title', $data);
        $this->assertArrayHasKey('body', $data);

        $stored = json_encode($data);
        $this->assertStringNotContainsString('0009900000011', $stored);
        $this->assertStringNotContainsString('rahasia', $stored);
        $this->assertStringNotContainsString('script', $stored);
    }

    public function test_ntf_03_a_walk_in_patient_without_an_account_gets_no_notification_but_history_and_audit_are_written(): void
    {
        $appointment = $this->appointment(AppointmentStatus::Confirmed, [], false);
        Notification::fake();
        $this->at('2026-10-06 10:00:00');
        $staff = User::factory()->staff()->create();

        $this->service->transition($appointment, AppointmentStatus::Cancelled, Actor::staff($staff), 'Pasien menelepon');

        Notification::assertNothingSent();
        $this->assertSame(AppointmentStatus::Cancelled, $appointment->fresh()->status);
        $this->assertHistory($appointment, AppointmentStatus::Confirmed, AppointmentStatus::Cancelled, ActorType::Staff, $staff->id, 'Pasien menelepon');
        $this->assertSame(1, AuditLog::query()->where('action', 'appointment.status_changed')->count());
        $this->assertSame(0, DB::table('notifications')->count());
    }

    // ------------------------------------------------------------------ tidak ada efek parsial saat gagal

    public function test_a_rejected_transition_writes_and_sends_nothing(): void
    {
        $appointment = $this->appointment(AppointmentStatus::Pending);
        $updatedAt = $appointment->fresh()->updated_at->toDateTimeString();
        Notification::fake();
        $this->at('2026-10-05 10:00:00');

        $this->assertFails('INVALID_TRANSITION', fn () => $this->service->transition($appointment, AppointmentStatus::Completed, $this->staff()));
        $this->assertFails('REASON_REQUIRED', fn () => $this->service->transition($appointment, AppointmentStatus::Cancelled, $this->staff()));
        $this->assertFails('CHECKIN_NOT_TODAY', fn () => $this->service->transition($appointment, AppointmentStatus::CheckedIn, $this->staff()));

        $this->assertSame(AppointmentStatus::Pending, $appointment->fresh()->status);
        $this->assertSame($updatedAt, $appointment->fresh()->updated_at->toDateTimeString());
        $this->assertSame(1, $this->booked());
        $this->assertSame(0, AppointmentStatusHistory::query()->count());
        $this->assertSame(0, AuditLog::query()->where('action', 'appointment.status_changed')->count());
        Notification::assertNothingSent();
    }

    /** @return array<string, array{0: string}> */
    public static function writeFaultPoints(): array
    {
        return [
            'insert riwayat' => ['insert into `appointment_status_histories`'],
            'insert audit' => ['insert into `audit_logs`'],
        ];
    }

    #[DataProvider('writeFaultPoints')]
    public function test_a_failure_after_the_cancel_has_released_the_seat_rolls_back_every_effect(string $fragment): void
    {
        $appointment = $this->appointment(AppointmentStatus::Confirmed);
        $owner = $this->ownerOf($appointment);
        Notification::fake();

        DB::connection()->beforeExecuting(function (string $query, array $bindings) use ($fragment): void {
            if (str_contains(strtolower($query), $fragment)) {
                throw new QueryException('mariadb', $query, $bindings, new PDOException('Simulasi galat penulisan'));
            }
        });

        try {
            $this->service->transition($appointment, AppointmentStatus::Cancelled, $owner, 'Berhalangan hadir');
            $this->fail('QueryException tidak dilempar.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('Simulasi galat penulisan', $e->getMessage());
        }

        $fresh = Appointment::query()->findOrFail($appointment->id);
        $this->assertSame(AppointmentStatus::Confirmed, $fresh->status);
        $this->assertNull($fresh->cancelled_at);
        $this->assertNull($fresh->cancel_reason);
        $this->assertSame(1, $fresh->active_flag);
        $this->assertSame(1, $this->booked());
        $this->assertSame(0, AppointmentStatusHistory::query()->count());
        $this->assertSame(0, AuditLog::query()->where('action', 'appointment.status_changed')->count());
        Notification::assertNothingSent();
    }

    // ------------------------------------------------------------------ urutan kunci (F6-R15, 00 F.6)

    public function test_locks_are_taken_by_primary_key_in_the_order_profile_session_appointment_before_any_other_query(): void
    {
        $appointment = $this->appointment(AppointmentStatus::Pending);
        $owner = $this->ownerOf($appointment);
        $this->at('2026-10-05 08:00:00');

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->service->transition($appointment, AppointmentStatus::Cancelled, $owner, 'Berhalangan hadir');
        $queries = array_column(DB::getQueryLog(), 'query');
        DB::disableQueryLog();

        $lockPositions = array_keys(array_filter($queries, fn (string $query): bool => str_contains($query, 'for update')));

        $this->assertSame([0, 1, 2], array_slice($lockPositions, 0, 3), 'Tiga kueri pertama harus tiga kunci.');
        $this->assertStringContainsString('`patient_profiles`', $queries[0]);
        $this->assertStringContainsString('`practice_sessions`', $queries[1]);
        $this->assertStringContainsString('`appointments`', $queries[2]);

        foreach (array_slice($queries, 0, 3) as $query) {
            $this->assertStringContainsString('`id` = ?', $query, 'Kunci hanya lewat PK.');
        }
    }

    // ------------------------------------------------------------------ ke-12 kode terpicu (BKG-12)

    public function test_bkg_12_all_twelve_error_codes_are_raised_at_least_once(): void
    {
        $cases = [
            'INVALID_TRANSITION' => [AppointmentStatus::Pending, AppointmentStatus::Completed, 'staff', '2026-10-06 10:00:00', null, null],
            'ALREADY_FINAL' => [AppointmentStatus::Completed, AppointmentStatus::Confirmed, 'staff', '2026-10-06 10:00:00', null, null],
            'FORBIDDEN_TRANSITION' => [AppointmentStatus::Confirmed, AppointmentStatus::CheckedIn, 'patient', '2026-10-06 10:00:00', null, null],
            'STALE_STATUS' => [AppointmentStatus::Confirmed, AppointmentStatus::CheckedIn, 'staff', '2026-10-06 10:00:00', null, AppointmentStatus::Pending],
            'CANCEL_CUTOFF_PASSED' => [AppointmentStatus::Confirmed, AppointmentStatus::Cancelled, 'patient', '2026-10-06 07:01:00', null, null],
            'CONFIRM_AFTER_END' => [AppointmentStatus::Pending, AppointmentStatus::Confirmed, 'staff', '2026-10-06 12:00:00', null, null],
            'CHECKIN_NOT_TODAY' => [AppointmentStatus::Confirmed, AppointmentStatus::CheckedIn, 'staff', '2026-10-05 10:00:00', null, null],
            'CHECKIN_NOT_OPEN' => [AppointmentStatus::Confirmed, AppointmentStatus::CheckedIn, 'staff', '2026-10-06 07:59:00', null, null],
            'CHECKIN_CLOSED' => [AppointmentStatus::Confirmed, AppointmentStatus::CheckedIn, 'staff', '2026-10-06 12:00:01', null, null],
            'CORRECTION_NOT_SAME_DAY' => [AppointmentStatus::CheckedIn, AppointmentStatus::Confirmed, 'staff', '2026-10-05 12:00:00', 'Salah check-in', null],
            'SESSION_NOT_ENDED' => [AppointmentStatus::Confirmed, AppointmentStatus::NoShow, 'staff', '2026-10-06 12:00:00', null, null],
            'REASON_REQUIRED' => [AppointmentStatus::Confirmed, AppointmentStatus::Cancelled, 'staff', '2026-10-06 10:00:00', null, null],
        ];

        $raised = [];

        foreach ($cases as $expectedCode => [$from, $to, $kind, $moment, $reason, $expected]) {
            $appointment = $this->appointment($from);
            $actor = $this->actorFor($kind, $appointment);
            $this->at($moment);

            $e = $this->assertFails($expectedCode, fn () => $this->service->transition($appointment, $to, $actor, $reason, $expected));

            $this->assertNotSame('', $e->getMessage());
            $raised[] = $e->errorCode;
        }

        $this->assertEqualsCanonicalizing(self::ALL_CODES, $raised);
        $this->assertSame(0, AppointmentStatusHistory::query()->count());
    }
}
