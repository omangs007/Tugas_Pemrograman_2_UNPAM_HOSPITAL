<?php

namespace Tests\Feature\Models;

use App\Enums\AppointmentStatus;
use App\Enums\PaymentType;
use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\PatientProfile;
use App\Models\PracticeSession;
use App\Services\Booking\BookingCodeGenerator;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\LazyLoadingViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InteractsWithContent;
use Tests\Support\InteractsWithSchedules;
use Tests\TestCase;

/**
 * Tambahan model Appointment fase 6 (relasi insurancePartner, scope, accessor, predikat pembatalan) dan factory
 * F6-R33. Waktu dibekukan Senin 5 Oktober 2026 pukul 10.00.
 */
class AppointmentDomainTest extends TestCase
{
    use InteractsWithContent;
    use InteractsWithSchedules;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-05 10:00:00');
    }

    // --- insurancePartner() dan BR-180 ---

    public function test_insurance_partner_relation_resolves_the_referenced_partner(): void
    {
        $partner = $this->createInsurancePartner();
        $appointment = $this->bookAppointment($this->createSession(), overrides: [
            'insurance_partner_id' => $partner->id,
            'payment_type' => PaymentType::Asuransi,
        ]);

        $this->assertInstanceOf(BelongsTo::class, $appointment->insurancePartner());
        $this->assertTrue($appointment->insurancePartner->is($partner));
        $this->assertSame([$appointment->id], $partner->appointments->pluck('id')->all());
    }

    public function test_insurance_partner_is_null_when_the_appointment_has_no_partner(): void
    {
        $appointment = $this->bookAppointment($this->createSession());

        $this->assertNull($appointment->insurancePartner);
    }

    public function test_partner_deletion_stays_blocked_by_the_br_180_text_while_an_appointment_refers_to_it(): void
    {
        $partner = $this->createInsurancePartner();
        $appointment = $this->bookAppointment($this->createSession(), overrides: [
            'insurance_partner_id' => $partner->id,
            'payment_type' => PaymentType::Asuransi,
        ]);

        $this->assertSame('Mitra asuransi masih dipakai oleh 1 data. Nonaktifkan saja.', $partner->deletionBlockedReason());
        $this->assertTrue($appointment->fresh()->insurancePartner->is($partner));

        $appointment->delete();

        $this->assertNull($partner->fresh()->deletionBlockedReason());
    }

    // --- $fillable (D-88) ---

    public function test_fillable_is_unchanged_and_the_last4_column_is_not_mass_assignable(): void
    {
        $this->assertSame(
            ['patient_profile_id', 'practice_session_id', 'doctor_id', 'clinic_id', 'session_date', 'channel', 'payment_type', 'insurance_partner_id', 'payment_reference', 'complaint', 'created_by_user_id'],
            (new Appointment)->getFillable(),
        );

        foreach (['payment_reference_last4', 'cancelled_by_type', 'cancelled_by_user_id', 'schedule_changed_at'] as $column) {
            try {
                new Appointment([$column => 'x']);
                $this->fail("{$column} seharusnya tidak dapat diisi massal.");
            } catch (MassAssignmentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    // --- scope ---

    public function test_for_patient_scope_returns_only_that_patients_appointments(): void
    {
        $mine = PatientProfile::factory()->create();
        $theirs = PatientProfile::factory()->create();
        $first = $this->bookAppointment($this->createSession(), overrides: ['patient_profile_id' => $mine->id]);
        $second = $this->bookAppointment($this->createSession(), AppointmentStatus::Cancelled, ['patient_profile_id' => $mine->id]);
        $this->bookAppointment($this->createSession(), overrides: ['patient_profile_id' => $theirs->id]);

        $found = Appointment::query()->forPatient($mine)->orderBy('id')->pluck('id')->all();

        $this->assertSame([$first->id, $second->id], $found);
        $this->assertSame([], Appointment::query()->forPatient(PatientProfile::factory()->create())->pluck('id')->all());
    }

    public function test_for_patient_scope_composes_with_the_existing_scopes(): void
    {
        $profile = PatientProfile::factory()->create();
        $active = $this->bookAppointment($this->createSession(), overrides: ['patient_profile_id' => $profile->id]);
        $this->bookAppointment($this->createSession(), AppointmentStatus::Cancelled, ['patient_profile_id' => $profile->id]);

        $this->assertSame([$active->id], Appointment::query()->forPatient($profile)->active()->pluck('id')->all());
        $this->assertSame([$active->id], Appointment::query()->forPatient($profile)->upcomingActive()->pluck('id')->all());
    }

    public function test_schedule_changed_scope_returns_only_appointments_with_a_change_marker(): void
    {
        $plain = $this->bookAppointment($this->createSession());
        $changed = $this->bookAppointment($this->createSession());
        $changed->forceFill(['schedule_changed_at' => now()])->save();

        $this->assertSame([$changed->id], Appointment::query()->scheduleChanged()->pluck('id')->all());
        $this->assertNotContains($plain->id, Appointment::query()->scheduleChanged()->pluck('id')->all());
    }

    // --- queue_label ---

    public function test_queue_label_combines_the_clinic_code_and_the_queue_number(): void
    {
        $session = $this->sessionOfClinicCode('ANK');
        $seventh = $this->bookAppointment($session, overrides: ['queue_number' => 7]);
        $big = $this->bookAppointment($session, overrides: ['queue_number' => 1234]);

        $this->assertSame('ANK-007', $seventh->load('clinic')->queue_label);
        $this->assertSame('ANK-1234', $big->load('clinic')->queue_label);
    }

    public function test_queue_label_needs_the_clinic_relation_on_lists(): void
    {
        $session = $this->sessionOfClinicCode('ANK');
        $this->bookAppointment($session);
        $this->bookAppointment($session);

        $labels = Appointment::query()->with('clinic')->orderBy('queue_number')->get()->map->queue_label->all();

        $this->assertSame(['ANK-001', 'ANK-002'], $labels);

        $this->expectException(LazyLoadingViolationException::class);

        Appointment::query()->orderBy('queue_number')->get()->each(fn (Appointment $appointment): string => $appointment->queue_label);
    }

    // --- masked_payment_reference ---

    public function test_masked_payment_reference_shows_nine_dots_and_the_last_four_characters(): void
    {
        $session = $this->createSession();
        $appointment = Appointment::factory()->forSession($session)->bpjs('0001234567890')->create()->fresh();

        $this->assertSame('•••••••••7890', $appointment->masked_payment_reference);
        $this->assertSame('7890', $appointment->payment_reference_last4);
        $this->assertStringNotContainsString('000123456', $appointment->masked_payment_reference);
        $this->assertArrayNotHasKey('masked_payment_reference', $appointment->toArray());
        $this->assertArrayNotHasKey('payment_reference', $appointment->toArray());
        $this->assertNotSame('0001234567890', DB::table('appointments')->where('id', $appointment->id)->value('payment_reference'));
    }

    public function test_masked_payment_reference_is_null_without_a_reference(): void
    {
        $appointment = Appointment::factory()->forSession($this->createSession())->create()->fresh();

        $this->assertNull($appointment->payment_reference_last4);
        $this->assertNull($appointment->masked_payment_reference);
    }

    // --- startsAt() / endsAt() ---

    public function test_starts_at_and_ends_at_come_from_the_session_of_the_appointment(): void
    {
        $session = $this->createSession(['doctor_schedule_id' => null, 'session_date' => '2026-10-06', 'start_time' => '09:00:00', 'end_time' => '12:30:00']);
        $appointment = $this->bookAppointment($session)->load('practiceSession');

        $this->assertInstanceOf(CarbonImmutable::class, $appointment->startsAt());
        $this->assertInstanceOf(CarbonImmutable::class, $appointment->endsAt());
        $this->assertSame('2026-10-06 09:00:00', $appointment->startsAt()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-06 12:30:00', $appointment->endsAt()->format('Y-m-d H:i:s'));
        $this->assertSame('Asia/Jakarta', $appointment->startsAt()->timezoneName);
    }

    // --- isCancellableByPatient() ---

    /**
     * @return array<string, array{0: string, 1: int, 2: AppointmentStatus, 3: bool}>
     */
    public static function cancellableCases(): array
    {
        // Sesi 6 Oktober 2026 pukul 09.00; kolom: jam "sekarang" pada hari itu, batas menit, status, hasil.
        return [
            'tepat di batas 120 menit (inklusif)' => ['07:00:00', 120, AppointmentStatus::Confirmed, true],
            'satu detik sebelum batas' => ['06:59:59', 120, AppointmentStatus::Confirmed, true],
            'satu detik lewat batas' => ['07:00:01', 120, AppointmentStatus::Confirmed, false],
            '121 menit sebelum sesi' => ['06:59:00', 120, AppointmentStatus::Pending, true],
            '119 menit sebelum sesi' => ['07:01:00', 120, AppointmentStatus::Pending, false],
            'batas 0 menit: tepat saat mulai' => ['09:00:00', 0, AppointmentStatus::Confirmed, true],
            'batas 0 menit: satu detik sesudah mulai' => ['09:00:01', 0, AppointmentStatus::Confirmed, false],
            'checked_in tidak dapat dibatalkan' => ['07:00:00', 120, AppointmentStatus::CheckedIn, false],
            'completed tidak dapat dibatalkan' => ['07:00:00', 120, AppointmentStatus::Completed, false],
            'cancelled tidak dapat dibatalkan lagi' => ['07:00:00', 120, AppointmentStatus::Cancelled, false],
            'no_show tidak dapat dibatalkan' => ['07:00:00', 120, AppointmentStatus::NoShow, false],
        ];
    }

    #[DataProvider('cancellableCases')]
    public function test_is_cancellable_by_patient_follows_status_and_the_inclusive_cutoff(string $time, int $cutoff, AppointmentStatus $status, bool $expected): void
    {
        $session = $this->createSession(['doctor_schedule_id' => null, 'session_date' => '2026-10-06', 'start_time' => '09:00:00', 'end_time' => '12:00:00']);
        $appointment = $this->bookAppointment($session, $status)->load('practiceSession');

        $this->assertSame($expected, $appointment->isCancellableByPatient(CarbonImmutable::parse('2026-10-06 '.$time), $cutoff));
    }

    public function test_is_cancellable_by_patient_accepts_a_mutable_carbon_instance(): void
    {
        $session = $this->createSession(['doctor_schedule_id' => null, 'session_date' => '2026-10-06', 'start_time' => '09:00:00', 'end_time' => '12:00:00']);
        $appointment = $this->bookAppointment($session)->load('practiceSession');

        $this->assertTrue($appointment->isCancellableByPatient(Carbon::parse('2026-10-06 07:00:00'), 120));
        $this->assertFalse($appointment->isCancellableByPatient(Carbon::parse('2026-10-06 07:00:01'), 120));
    }

    // --- AppointmentFactory (F6-R33) ---

    public function test_the_factory_uses_valid_unique_booking_codes_f6_r5(): void
    {
        $session = $this->createSession();
        $codes = [];

        foreach (range(1, 6) as $queue) {
            $appointment = Appointment::factory()->forSession($session)->create(['queue_number' => $queue]);

            $this->assertTrue(BookingCodeGenerator::isValid($appointment->booking_code), $appointment->booking_code);
            $codes[] = $appointment->booking_code;
        }

        $this->assertCount(6, array_unique($codes));
    }

    public function test_for_session_copies_the_session_columns_without_touching_the_counters(): void
    {
        $session = $this->createSession(['session_date' => '2026-10-12']);

        $appointment = Appointment::factory()->forSession($session)->create()->refresh();

        $this->assertSame($session->id, $appointment->practice_session_id);
        $this->assertSame($session->doctor_id, $appointment->doctor_id);
        $this->assertSame($session->clinic_id, $appointment->clinic_id);
        $this->assertSame('2026-10-12', $appointment->session_date->toDateString());
        $this->assertSame(0, $session->fresh()->booked_count, 'Factory tidak menyinkronkan booked_count.');
        $this->assertSame(0, $session->fresh()->last_queue_number);
    }

    public function test_for_patient_uses_the_given_profile(): void
    {
        $profile = PatientProfile::factory()->create();

        $appointment = Appointment::factory()->forSession($this->createSession())->forPatient($profile)->create();

        $this->assertSame($profile->id, $appointment->patient_profile_id);
        $this->assertSame(1, PatientProfile::query()->count(), 'forPatient tidak boleh membuat profil lain.');
    }

    public function test_bpjs_state_fills_type_reference_and_last4(): void
    {
        $default = Appointment::factory()->forSession($this->createSession())->bpjs()->create()->fresh();

        $this->assertSame(PaymentType::Bpjs, $default->payment_type);
        $this->assertSame('0009900000011', $default->payment_reference);
        $this->assertSame('0011', $default->payment_reference_last4);

        $custom = Appointment::factory()->forSession($this->createSession())->bpjs('0001234567890')->create()->fresh();

        $this->assertSame('0001234567890', $custom->payment_reference);
        $this->assertSame('7890', $custom->payment_reference_last4);
    }

    // --- PracticeSessionFactory (F6-R33) ---

    public function test_starting_in_minutes_places_the_session_relative_to_now(): void
    {
        $session = PracticeSession::factory()->manual()->startingInMinutes(90)->create()->refresh();

        $this->assertSame('2026-10-05', $session->session_date->toDateString());
        $this->assertSame('11:30:00', $session->start_time);
        $this->assertSame('15:30:00', $session->end_time);
        $this->assertTrue($session->starts_at->equalTo(now()->addMinutes(90)));
    }

    public function test_starting_in_minutes_supports_sessions_that_already_started(): void
    {
        $session = PracticeSession::factory()->manual()->startingInMinutes(-30)->create()->refresh();

        $this->assertSame('09:30:00', $session->start_time);
        $this->assertSame('13:30:00', $session->end_time);
        $this->assertFalse($session->isEnded());
    }

    public function test_starting_in_minutes_crosses_midnight_correctly(): void
    {
        $this->travelTo('2026-10-05 22:30:00');

        $sameDay = PracticeSession::factory()->manual()->startingInMinutes(60)->create()->refresh();
        $nextDay = PracticeSession::factory()->manual()->startingInMinutes(120)->create()->refresh();

        $this->assertSame('2026-10-05', $sameDay->session_date->toDateString());
        $this->assertSame('23:30:00', $sameDay->start_time);
        $this->assertSame('23:59:59', $sameDay->end_time, 'end_time dipotong agar tetap di hari yang sama.');
        $this->assertSame('2026-10-06', $nextDay->session_date->toDateString());
        $this->assertSame('00:30:00', $nextDay->start_time);
        $this->assertSame('04:30:00', $nextDay->end_time);
    }

    public function test_full_fills_the_counters_to_the_final_quota(): void
    {
        $default = PracticeSession::factory()->manual()->full()->create()->refresh();
        $custom = PracticeSession::factory()->manual()->full()->create(['quota' => 3])->refresh();

        $this->assertSame(20, $default->booked_count);
        $this->assertSame(20, $default->last_queue_number);
        $this->assertSame(3, $custom->booked_count);
        $this->assertSame(3, $custom->last_queue_number);
        $this->assertSame(0, $custom->remaining);
    }

    private function sessionOfClinicCode(string $code): PracticeSession
    {
        $clinic = Clinic::factory()->create(['code' => $code]);
        $doctor = Doctor::factory()->create(['clinic_id' => $clinic->id]);

        return $this->createSession(['doctor_id' => $doctor->id, 'quota' => 50]);
    }
}
