<?php

namespace Tests\Feature\Models;

use App\Enums\ActorType;
use App\Enums\AppointmentStatus;
use App\Enums\BookingChannel;
use App\Enums\Gender;
use App\Enums\PaymentType;
use App\Models\Appointment;
use App\Models\AppointmentStatusHistory;
use App\Models\PatientProfile;
use App\Models\PracticeSession;
use App\Models\User;
use App\Services\Booking\BookingCodeGenerator;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InteractsWithSchedules;
use Tests\TestCase;

/**
 * 09 §2.2, §4.1, §4.2 dan 11 §5.4 (skema + model + factory; belum ada UI atau service, F3-R4).
 */
class BookingModelsTest extends TestCase
{
    use InteractsWithSchedules;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-05 10:00:00');
    }

    // --- PatientProfile ---

    public function test_patient_profile_factory_and_casts(): void
    {
        $profile = PatientProfile::factory()->create(['birth_date' => '1990-04-12', 'gender' => Gender::Female])->fresh();

        $this->assertMatchesRegularExpression('/^P\d{6}$/', $profile->patient_number);
        $this->assertSame('1990-04-12', $profile->birth_date->toDateString());
        $this->assertSame(Gender::Female, $profile->gender);
        $this->assertSame(BookingChannel::Loket, $profile->registration_channel);
        $this->assertTrue($profile->is_active);
        $this->assertNull($profile->user_id);
        $this->assertSame('2026-10-05 10:00:00', $profile->consent_at->format('Y-m-d H:i:s'));
        $this->assertSame('1.0', $profile->consent_version);
    }

    public function test_patient_profile_bpjs_number_is_encrypted_and_hidden(): void
    {
        $profile = PatientProfile::factory()->create(['bpjs_number' => '0001234567890']);

        $raw = DB::table('patient_profiles')->where('id', $profile->id)->value('bpjs_number');

        $this->assertNotSame('0001234567890', $raw);
        $this->assertStringNotContainsString('0001234567890', (string) $raw);
        $this->assertSame('0001234567890', $profile->fresh()->bpjs_number);
        $this->assertArrayNotHasKey('bpjs_number', $profile->fresh()->toArray());
    }

    public function test_patient_profile_system_columns_are_not_mass_assignable(): void
    {
        $this->expectException(MassAssignmentException::class);

        new PatientProfile(['patient_number' => 'P000001']);
    }

    public function test_patient_profile_with_account_links_a_patient_user_and_copies_the_email(): void
    {
        $profile = PatientProfile::factory()->withAccount()->create();

        $loaded = PatientProfile::query()->with('user')->findOrFail($profile->id);

        $this->assertNotNull($loaded->user);
        $this->assertTrue($loaded->user->isPatient());
        $this->assertSame($loaded->user->email, $loaded->email);
        $this->assertSame(BookingChannel::Online, $loaded->registration_channel);
    }

    public function test_patient_profile_relations(): void
    {
        $staff = User::factory()->staff()->create();
        $profile = PatientProfile::factory()->create();
        $profile->forceFill(['created_by_user_id' => $staff->id])->save();
        $session = $this->createSession(['doctor_schedule_id' => null]);
        $appointment = $this->bookAppointment($session, AppointmentStatus::Confirmed, ['patient_profile_id' => $profile->id]);

        $loaded = PatientProfile::query()->with(['user', 'creator', 'appointments'])->findOrFail($profile->id);

        $this->assertNull($loaded->user);
        $this->assertSame($staff->id, $loaded->creator->id);
        $this->assertSame([$appointment->id], $loaded->appointments->pluck('id')->all());
    }

    // --- Appointment ---

    public function test_appointment_factory_derives_doctor_clinic_and_date_from_the_session(): void
    {
        $session = $this->createSession(['session_date' => '2026-10-12']);

        $appointment = Appointment::factory()->create(['practice_session_id' => $session->id])->refresh();

        $this->assertSame($session->doctor_id, $appointment->doctor_id);
        $this->assertSame($session->clinic_id, $appointment->clinic_id);
        $this->assertSame('2026-10-12', $appointment->session_date->toDateString());
        $this->assertSame(1, $appointment->queue_number);
        $this->assertSame(AppointmentStatus::Confirmed, $appointment->status);
        $this->assertSame(BookingChannel::Online, $appointment->channel);
        $this->assertSame(PaymentType::Umum, $appointment->payment_type);
        $this->assertTrue(BookingCodeGenerator::isValid($appointment->booking_code), $appointment->booking_code);
    }

    public function test_appointment_booking_codes_are_unique_across_a_batch(): void
    {
        $session = $this->createSession();

        $codes = [];

        foreach (range(1, 5) as $queue) {
            $codes[] = Appointment::factory()->create(['practice_session_id' => $session->id, 'queue_number' => $queue])->booking_code;
        }

        $this->assertCount(5, array_unique($codes));
    }

    public function test_appointment_payment_reference_is_encrypted_and_hidden(): void
    {
        $session = $this->createSession();
        $appointment = Appointment::factory()->create([
            'practice_session_id' => $session->id, 'payment_type' => PaymentType::Bpjs, 'payment_reference' => '0001234567890',
        ]);

        $raw = DB::table('appointments')->where('id', $appointment->id)->value('payment_reference');

        $this->assertNotSame('0001234567890', $raw);
        $this->assertSame('0001234567890', $appointment->fresh()->payment_reference);
        $this->assertArrayNotHasKey('payment_reference', $appointment->fresh()->toArray());
    }

    public function test_appointment_has_an_insurance_partner_relation_since_phase_six(): void
    {
        $this->assertTrue(method_exists(Appointment::class, 'insurancePartner'));
        $this->assertInstanceOf(BelongsTo::class, (new Appointment)->insurancePartner());
    }

    public function test_appointment_system_columns_are_not_mass_assignable(): void
    {
        foreach (['status', 'queue_number', 'booking_code', 'active_flag', 'cancelled_at'] as $column) {
            try {
                new Appointment([$column => 'x']);
                $this->fail("{$column} seharusnya tidak dapat diisi massal.");
            } catch (MassAssignmentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_appointment_relations(): void
    {
        $staff = User::factory()->staff()->create();
        $session = $this->createSession();
        $appointment = $this->bookAppointment($session);
        $appointment->forceFill(['created_by_user_id' => $staff->id, 'cancelled_by_user_id' => $staff->id])->save();
        AppointmentStatusHistory::factory()->create(['appointment_id' => $appointment->id, 'to_status' => AppointmentStatus::Pending]);
        AppointmentStatusHistory::factory()->create([
            'appointment_id' => $appointment->id, 'from_status' => AppointmentStatus::Pending, 'to_status' => AppointmentStatus::Confirmed,
        ]);

        $loaded = Appointment::query()
            ->with(['patientProfile', 'practiceSession', 'doctor', 'clinic', 'statusHistories', 'creator', 'canceller'])
            ->findOrFail($appointment->id);

        $this->assertInstanceOf(PatientProfile::class, $loaded->patientProfile);
        $this->assertInstanceOf(PracticeSession::class, $loaded->practiceSession);
        $this->assertSame($session->doctor_id, $loaded->doctor->id);
        $this->assertSame($session->clinic_id, $loaded->clinic->id);
        $this->assertSame($staff->id, $loaded->creator->id);
        $this->assertSame($staff->id, $loaded->canceller->id);
        $this->assertSame(
            [AppointmentStatus::Pending, AppointmentStatus::Confirmed],
            $loaded->statusHistories->map(fn (AppointmentStatusHistory $history): AppointmentStatus => $history->to_status)->all(),
        );
    }

    public function test_active_scope_matches_the_enum_and_the_generated_column_for_every_status(): void
    {
        $session = $this->createSession(['quota' => 50]);

        foreach (AppointmentStatus::cases() as $status) {
            $this->bookAppointment($session, $status);
        }

        $activeValues = array_map(fn (AppointmentStatus $status): string => $status->value, AppointmentStatus::ACTIVE);
        $quotaValues = array_map(fn (AppointmentStatus $status): string => $status->value, AppointmentStatus::QUOTA_CONSUMING);

        $this->assertEqualsCanonicalizing($activeValues, Appointment::query()->active()->get()->map(fn (Appointment $a): string => $a->status->value)->all());
        $this->assertEqualsCanonicalizing($activeValues, Appointment::query()->whereNotNull('active_flag')->get()->map(fn (Appointment $a): string => $a->status->value)->all(), 'I-15: active_flag harus selaras dengan enum.');
        $this->assertEqualsCanonicalizing($quotaValues, Appointment::query()->quotaConsuming()->get()->map(fn (Appointment $a): string => $a->status->value)->all());
        $this->assertSame(count($quotaValues), $session->fresh()->booked_count, 'I-1: booked_count = jumlah appointment non-batal.');
    }

    public function test_status_state_fills_the_matching_timestamps(): void
    {
        $session = $this->createSession(['quota' => 50]);
        $make = fn (AppointmentStatus $status, int $queue): Appointment => Appointment::factory()->status($status)->create(['practice_session_id' => $session->id, 'queue_number' => $queue]);

        $pending = $make(AppointmentStatus::Pending, 1);
        $confirmed = $make(AppointmentStatus::Confirmed, 2);
        $checkedIn = $make(AppointmentStatus::CheckedIn, 3);
        $completed = $make(AppointmentStatus::Completed, 4);
        $cancelled = $make(AppointmentStatus::Cancelled, 5);
        $noShow = $make(AppointmentStatus::NoShow, 6);

        $this->assertNull($pending->confirmed_at);
        $this->assertNotNull($confirmed->confirmed_at);
        $this->assertNull($confirmed->checked_in_at);
        $this->assertNotNull($checkedIn->checked_in_at);
        $this->assertNull($checkedIn->completed_at);
        $this->assertNotNull($completed->completed_at);
        $this->assertNotNull($cancelled->cancelled_at);
        $this->assertSame(ActorType::System, $cancelled->cancelled_by_type);
        $this->assertNull($cancelled->confirmed_at);
        $this->assertNotNull($noShow->no_show_at);
        $this->assertNull($noShow->completed_at);
    }

    // --- upcomingActive (F3-R15) ---

    /**
     * @return array<string, array{0: string, 1: AppointmentStatus, 2: string, 3: string, 4: bool}>
     */
    public static function upcomingCases(): array
    {
        return [
            'aktif, besok' => ['2026-10-06', AppointmentStatus::Confirmed, '08:00:00', '12:00:00', true],
            'pending, besok' => ['2026-10-06', AppointmentStatus::Pending, '08:00:00', '12:00:00', true],
            'check-in, hari ini belum berakhir' => ['2026-10-05', AppointmentStatus::CheckedIn, '09:00:00', '10:00:01', true],
            'aktif, hari ini tepat berakhir' => ['2026-10-05', AppointmentStatus::Confirmed, '08:00:00', '10:00:00', false],
            'aktif, hari ini sudah berakhir' => ['2026-10-05', AppointmentStatus::Confirmed, '06:00:00', '09:00:00', false],
            'aktif, kemarin' => ['2026-10-04', AppointmentStatus::Confirmed, '08:00:00', '23:00:00', false],
            'dibatalkan, besok' => ['2026-10-06', AppointmentStatus::Cancelled, '08:00:00', '12:00:00', false],
            'selesai, besok' => ['2026-10-06', AppointmentStatus::Completed, '08:00:00', '12:00:00', false],
            'tidak hadir, besok' => ['2026-10-06', AppointmentStatus::NoShow, '08:00:00', '12:00:00', false],
        ];
    }

    /** Sekarang: Senin 5 Oktober 2026 pukul 10.00. */
    #[DataProvider('upcomingCases')]
    public function test_upcoming_active_scope(string $date, AppointmentStatus $status, string $start, string $end, bool $expected): void
    {
        $session = $this->createSession(['doctor_schedule_id' => null, 'session_date' => $date, 'start_time' => $start, 'end_time' => $end]);
        $appointment = $this->bookAppointment($session, $status);

        $found = Appointment::query()->upcomingActive()->whereKey($appointment->id)->exists();

        $this->assertSame($expected, $found);
    }

    public function test_upcoming_active_scope_accepts_an_explicit_moment(): void
    {
        $session = $this->createSession(['doctor_schedule_id' => null, 'session_date' => '2026-10-05', 'start_time' => '08:00:00', 'end_time' => '12:00:00']);
        $appointment = $this->bookAppointment($session);

        $this->assertTrue(Appointment::query()->upcomingActive(now()->setTime(11, 59, 59))->whereKey($appointment->id)->exists());
        $this->assertFalse(Appointment::query()->upcomingActive(now()->setTime(12, 0, 0))->whereKey($appointment->id)->exists());
    }

    // --- AppointmentStatusHistory ---

    public function test_status_history_has_no_updated_at_and_casts_enums(): void
    {
        $history = AppointmentStatusHistory::factory()->create()->fresh();

        $this->assertNull(AppointmentStatusHistory::UPDATED_AT);
        $this->assertArrayNotHasKey('updated_at', $history->getAttributes());
        $this->assertNotNull($history->created_at);
        $this->assertNull($history->from_status);
        $this->assertSame(AppointmentStatus::Pending, $history->to_status);
        $this->assertSame(ActorType::System, $history->actor_type);
    }

    public function test_status_history_relations(): void
    {
        $staff = User::factory()->staff()->create();
        $history = AppointmentStatusHistory::factory()->create(['actor_type' => ActorType::Staff, 'actor_user_id' => $staff->id]);

        $loaded = AppointmentStatusHistory::query()->with(['appointment', 'actor'])->findOrFail($history->id);

        $this->assertInstanceOf(Appointment::class, $loaded->appointment);
        $this->assertSame($staff->id, $loaded->actor->id);
    }

    public function test_status_history_is_append_only(): void
    {
        $history = AppointmentStatusHistory::factory()->create();

        try {
            $history->update(['reason' => 'Diubah']);
            $this->fail('Riwayat tidak boleh diubah.');
        } catch (LogicException $e) {
            $this->assertSame('Riwayat status appointment tidak dapat diubah atau dihapus.', $e->getMessage());
        }

        try {
            $history->delete();
            $this->fail('Riwayat tidak boleh dihapus.');
        } catch (LogicException) {
            $this->addToAssertionCount(1);
        }

        $this->assertDatabaseHas('appointment_status_histories', ['id' => $history->id, 'reason' => null]);
    }
}
