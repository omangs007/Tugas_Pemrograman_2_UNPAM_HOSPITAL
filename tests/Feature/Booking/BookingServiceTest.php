<?php

namespace Tests\Feature\Booking;

use App\Enums\ActorType;
use App\Enums\AppointmentStatus;
use App\Enums\BookingChannel;
use App\Enums\PaymentType;
use App\Enums\SessionStatus;
use App\Exceptions\BookingException;
use App\Models\Appointment;
use App\Models\AppointmentStatusHistory;
use App\Models\AuditLog;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\InsurancePartner;
use App\Models\PatientProfile;
use App\Models\PracticeSession;
use App\Models\User;
use App\Notifications\AppointmentStatusChangedNotification;
use App\Services\Booking\BookingCodeGenerator;
use App\Services\Booking\BookingData;
use App\Services\Booking\BookingService;
use App\Services\Settings\SettingsService;
use App\Support\Actor;
use Database\Seeders\SettingsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Notification;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\InteractsWithPatients;
use Tests\Support\InteractsWithSchedules;
use Tests\Support\InteractsWithStaff;
use Tests\Support\RegistersAppointmentRoutes;
use Tests\TestCase;

/**
 * F6-R12, F6-R13, F6-R35: `BookingService::book()` dan `precheck()`. BKG-01/02/04/05/06/07/08/09 (satu per kode
 * F.5, tanpa baris parsial), BKG-10, BKG-15, BKG-17, BKG-18 (pelanggaran UNIQUE/CHECK nyata dari MariaDB, bukan
 * mock), NTF-01/NTF-03 (sisi `book()`), SEK-12, Review Focus 1 (urutan kunci, nomor antrean tidak dipakai ulang)
 * dan Review Focus 5 (riwayat dan audit tanpa data sensitif).
 *
 * Di dalam RefreshDatabase, `DB::transaction` adalah savepoint: deadlock tidak diulang (Laravel melempar
 * DeadlockException) dan tidak ada transaksi terluar. Pengulangan transaksi nyata, SYSTEM_BUSY karena lock wait,
 * dan balapan antarkoneksi diuji di tests/Feature/Concurrency (Task 6).
 */
class BookingServiceTest extends TestCase
{
    use InteractsWithPatients;
    use InteractsWithSchedules;
    use InteractsWithStaff;
    use RefreshDatabase;
    use RegistersAppointmentRoutes;

    protected function setUp(): void
    {
        parent::setUp();

        // Kamis 2026-10-08 09.30; sesi bawaan = besok (Jumat 2026-10-09) 08.00-12.00.
        $this->travelTo('2026-10-08 09:30:00');
        app()->setLocale('id');
        $this->seed(SettingsSeeder::class);
        $this->ensureAppointmentShowRoute();
    }

    // ------------------------------------------------------------------ pembantu

    private function service(): BookingService
    {
        return $this->app->make(BookingService::class);
    }

    private function umum(): BookingData
    {
        return new BookingData(PaymentType::Umum);
    }

    /**
     * Sesi manual (tanpa templat) kuota 5 dengan dokter dan poliklinik baru.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function makeSession(array $overrides = []): PracticeSession
    {
        return $this->createSession($overrides + ['doctor_schedule_id' => null, 'quota' => 5]);
    }

    private function bookAs(User $user, PracticeSession $session, ?BookingData $data = null): Appointment
    {
        return $this->service()->book($user->patientProfile, $session->id, $data ?? $this->umum(), Actor::patient($user));
    }

    /**
     * Pembatalan langsung di DB (Task 5 belum ada): status final dan `booked_count - 1`; `last_queue_number` tetap.
     */
    private function cancelDirectly(Appointment $appointment): void
    {
        DB::table('appointments')->where('id', $appointment->id)->update([
            'status' => AppointmentStatus::Cancelled->value,
            'cancelled_at' => now(),
            'cancelled_by_type' => ActorType::Patient->value,
            'updated_at' => now(),
        ]);
        DB::table('practice_sessions')->where('id', $appointment->practice_session_id)->decrement('booked_count');
    }

    /**
     * @return array<string, int>
     */
    private function counters(int $sessionId): array
    {
        return (array) DB::table('practice_sessions')->where('id', $sessionId)->first(['booked_count', 'last_queue_number']);
    }

    /**
     * @return array{rows: array<string, int>, counters: array<string, int>}
     */
    private function snapshot(int $sessionId): array
    {
        return [
            'rows' => [
                'appointments' => DB::table('appointments')->count(),
                'histories' => DB::table('appointment_status_histories')->count(),
                'audits' => DB::table('audit_logs')->count(),
                'notifications' => DB::table('notifications')->count(),
            ],
            'counters' => $this->counters($sessionId),
        ];
    }

    private function sentMailCount(): int
    {
        return count(app('mailer')->getSymfonyTransport()->messages());
    }

    // ------------------------------------------------------------------ sukses (BKG-01, BKG-15, loket)

    public function test_an_online_booking_creates_a_pending_appointment_with_the_session_columns_copied(): void
    {
        $user = $this->createPatient();
        $session = $this->makeSession(['quota' => 3]);

        $appointment = $this->bookAs($user, $session);

        $this->assertTrue(BookingCodeGenerator::isValid($appointment->booking_code));
        $this->assertSame($user->patientProfile->id, $appointment->patient_profile_id);
        $this->assertSame($session->id, $appointment->practice_session_id);
        $this->assertSame($session->doctor_id, $appointment->doctor_id);
        $this->assertSame($session->clinic_id, $appointment->clinic_id);
        $this->assertSame('2026-10-09', $appointment->session_date->toDateString());
        $this->assertSame(1, $appointment->queue_number);
        $this->assertSame(AppointmentStatus::Pending, $appointment->status);
        $this->assertSame(BookingChannel::Online, $appointment->channel);
        $this->assertSame(PaymentType::Umum, $appointment->payment_type);
        $this->assertSame(1, $appointment->active_flag, 'Kolom generated terisi karena appointment dibaca ulang dari DB.');
        $this->assertNull($appointment->confirmed_at);
        $this->assertNull($appointment->created_by_user_id);
        $this->assertNull($appointment->insurance_partner_id);
        $this->assertNull($appointment->payment_reference);
        $this->assertNull($appointment->payment_reference_last4);

        foreach (['clinic', 'doctor', 'practiceSession'] as $relation) {
            $this->assertTrue($appointment->relationLoaded($relation), "Relasi {$relation} harus sudah dimuat.");
        }

        $this->assertSame($session->clinic->code.'-001', $appointment->queue_label);
        $this->assertSame(['booked_count' => 1, 'last_queue_number' => 1], $this->counters($session->id));
    }

    public function test_the_first_history_row_has_no_from_status_and_records_the_patient_actor(): void
    {
        $user = $this->createPatient();

        $appointment = $this->bookAs($user, $this->makeSession());

        $history = AppointmentStatusHistory::query()->where('appointment_id', $appointment->id)->sole();
        $this->assertNull($history->from_status);
        $this->assertSame(AppointmentStatus::Pending, $history->to_status);
        $this->assertSame(ActorType::Patient, $history->actor_type);
        $this->assertSame($user->id, $history->actor_user_id);
        $this->assertNull($history->reason);
    }

    public function test_auto_confirm_creates_a_confirmed_appointment_with_the_automatic_confirmation_reason(): void
    {
        app(SettingsService::class)->set('booking.auto_confirm', true);
        $user = $this->createPatient();
        $session = $this->makeSession();

        $appointment = $this->bookAs($user, $session);

        $this->assertSame(AppointmentStatus::Confirmed, $appointment->status);
        $this->assertSame('2026-10-08 09:30:00', $appointment->confirmed_at->toDateTimeString());
        $this->assertSame(BookingChannel::Online, $appointment->channel);

        $history = AppointmentStatusHistory::query()->where('appointment_id', $appointment->id)->sole();
        $this->assertNull($history->from_status);
        $this->assertSame(AppointmentStatus::Confirmed, $history->to_status);
        $this->assertSame('Konfirmasi otomatis', $history->reason);
        $this->assertSame(['booked_count' => 1, 'last_queue_number' => 1], $this->counters($session->id));
    }

    public function test_a_counter_booking_by_staff_is_confirmed_and_records_the_creator(): void
    {
        $staff = $this->createStaff('registration-officer');
        $walkIn = PatientProfile::factory()->create();
        $session = $this->makeSession();

        $appointment = $this->service()->book($walkIn, $session->id, $this->umum(), Actor::staff($staff));

        $this->assertSame(BookingChannel::Loket, $appointment->channel);
        $this->assertSame(AppointmentStatus::Confirmed, $appointment->status);
        $this->assertSame('2026-10-08 09:30:00', $appointment->confirmed_at->toDateTimeString());
        $this->assertSame($staff->id, $appointment->created_by_user_id);

        $history = AppointmentStatusHistory::query()->where('appointment_id', $appointment->id)->sole();
        $this->assertSame(ActorType::Staff, $history->actor_type);
        $this->assertSame($staff->id, $history->actor_user_id);
        $this->assertNull($history->reason, 'Alasan "Konfirmasi otomatis" hanya untuk pendaftaran online.');

        $audit = AuditLog::query()->where('action', 'appointment.created')->sole();
        $this->assertSame(ActorType::Staff, $audit->actor_type);
        $this->assertSame($staff->id, $audit->user_id);
        $this->assertSame('loket', $audit->new_values['channel']);
        $this->assertStringContainsString('(Loket)', $audit->description);
    }

    public function test_a_counter_booking_is_accepted_after_the_online_cutoff_while_online_is_not(): void
    {
        $session = $this->makeSession(['session_date' => '2026-10-08', 'start_time' => '10:00:00', 'end_time' => '12:00:00']);
        $staff = $this->createStaff('registration-officer');
        $user = $this->createPatient();

        try {
            $this->bookAs($user, $session);
            $this->fail('Pendaftaran online 30 menit sebelum sesi seharusnya ditolak (batas 60 menit).');
        } catch (BookingException $e) {
            $this->assertSame(BookingException::CUTOFF_PASSED, $e->errorCode);
        }

        $appointment = $this->service()->book($user->patientProfile, $session->id, $this->umum(), Actor::staff($staff));

        $this->assertSame(BookingChannel::Loket, $appointment->channel);
        $this->assertSame(['booked_count' => 1, 'last_queue_number' => 1], $this->counters($session->id));
    }

    // ------------------------------------------------------------------ antrean dan kode (BKG-02, BKG-10, BKG-17)

    public function test_five_bookings_get_queue_numbers_one_to_five_with_valid_unique_codes_and_the_sixth_is_refused(): void
    {
        $clinic = Clinic::factory()->create(['code' => 'ANK']);
        $doctor = Doctor::factory()->create(['clinic_id' => $clinic->id]);
        $session = $this->makeSession(['doctor_id' => $doctor->id, 'clinic_id' => $clinic->id, 'quota' => 5]);

        $codes = [];
        $labels = [];
        foreach (range(1, 5) as $expectedQueue) {
            $appointment = $this->bookAs($this->createPatient(), $session);
            $this->assertSame($expectedQueue, $appointment->queue_number);
            $codes[] = $appointment->booking_code;
            $labels[] = $appointment->queue_label;
            $this->assertMatchesRegularExpression(BookingCodeGenerator::PATTERN, $appointment->booking_code);
        }

        $this->assertSame(['ANK-001', 'ANK-002', 'ANK-003', 'ANK-004', 'ANK-005'], $labels);
        $this->assertCount(5, array_unique($codes));
        $this->assertSame(['booked_count' => 5, 'last_queue_number' => 5], $this->counters($session->id));

        try {
            $this->bookAs($this->createPatient(), $session);
            $this->fail('Pasien keenam pada kuota 5 seharusnya ditolak.');
        } catch (BookingException $e) {
            $this->assertSame(BookingException::QUOTA_FULL, $e->errorCode);
        }

        $this->assertSame(['booked_count' => 5, 'last_queue_number' => 5], $this->counters($session->id));
    }

    public function test_a_cancelled_queue_number_is_never_reused_and_the_same_patient_can_book_again(): void
    {
        $session = $this->makeSession(['quota' => 3]);
        $userA = $this->createPatient();
        $userB = $this->createPatient();
        $userC = $this->createPatient();

        $first = $this->bookAs($userA, $session);
        $this->bookAs($userB, $session);
        $this->cancelDirectly($first);
        $this->assertSame(['booked_count' => 1, 'last_queue_number' => 2], $this->counters($session->id));

        $third = $this->bookAs($userC, $session);
        $again = $this->bookAs($userA, $session);

        $this->assertSame(3, $third->queue_number, 'Nomor antrean 1 tidak dipakai ulang.');
        $this->assertSame(4, $again->queue_number, 'Pasien yang sama boleh mendaftar lagi sesudah batal, dengan nomor baru.');
        $this->assertSame(['booked_count' => 3, 'last_queue_number' => 4], $this->counters($session->id));
        $this->assertSame([1, 2, 3, 4], Appointment::query()->orderBy('queue_number')->pluck('queue_number')->all());
    }

    // ------------------------------------------------------------------ pembayaran (BKG-09, SEK-12, save_bpjs)

    public function test_the_bpjs_number_is_stored_encrypted_with_the_last_four_digits(): void
    {
        $user = $this->createPatient();
        $data = new BookingData(paymentType: PaymentType::Bpjs, paymentReference: '0009900000011');

        $appointment = $this->bookAs($user, $this->makeSession(), $data);

        $raw = (string) DB::table('appointments')->where('id', $appointment->id)->value('payment_reference');
        $this->assertNotSame('', $raw);
        $this->assertStringNotContainsString('0009900000011', $raw, 'Nilai mentah di DB harus terenkripsi.');
        $this->assertSame('0009900000011', Crypt::decryptString($raw));
        $this->assertSame('0009900000011', Appointment::query()->findOrFail($appointment->id)->payment_reference);
        $this->assertSame('0011', DB::table('appointments')->where('id', $appointment->id)->value('payment_reference_last4'));
        $this->assertSame('•••••••••0011', $appointment->masked_payment_reference);
        $this->assertSame(PaymentType::Bpjs, $appointment->payment_type);
        $this->assertNull($appointment->insurance_partner_id);
    }

    public function test_an_insurance_booking_stores_the_partner_and_the_encrypted_policy_number(): void
    {
        $user = $this->createPatient();
        $partner = InsurancePartner::factory()->create();
        $data = new BookingData(paymentType: PaymentType::Asuransi, paymentReference: 'AB-1234567', insurancePartnerId: $partner->id);

        $appointment = $this->bookAs($user, $this->makeSession(), $data);

        $raw = (string) DB::table('appointments')->where('id', $appointment->id)->value('payment_reference');
        $this->assertStringNotContainsString('AB-1234567', $raw);
        $this->assertSame('AB-1234567', Crypt::decryptString($raw));
        $this->assertSame($partner->id, $appointment->insurance_partner_id);
        $this->assertSame('4567', $appointment->payment_reference_last4);
    }

    public function test_an_insurance_booking_without_a_policy_number_keeps_the_reference_empty(): void
    {
        $user = $this->createPatient();
        $partner = InsurancePartner::factory()->create();

        $appointment = $this->bookAs($user, $this->makeSession(), new BookingData(paymentType: PaymentType::Asuransi, insurancePartnerId: $partner->id));

        $this->assertSame($partner->id, $appointment->insurance_partner_id);
        $this->assertNull($appointment->payment_reference);
        $this->assertNull($appointment->payment_reference_last4);
    }

    public function test_save_bpjs_updates_the_profile_number_only_when_requested_for_a_bpjs_booking(): void
    {
        $number = '0009900000011';
        $saver = $this->createPatient();
        $keeper = $this->createPatient();
        $cash = $this->createPatient();

        $this->bookAs($saver, $this->makeSession(), new BookingData(paymentType: PaymentType::Bpjs, paymentReference: $number, saveBpjs: true));
        $this->bookAs($keeper, $this->makeSession(), new BookingData(paymentType: PaymentType::Bpjs, paymentReference: $number, saveBpjs: false));
        $this->bookAs($cash, $this->makeSession(), new BookingData(paymentType: PaymentType::Umum, saveBpjs: true));

        $savedProfile = PatientProfile::query()->findOrFail($saver->patientProfile->id);
        $this->assertSame($number, $savedProfile->bpjs_number);
        $this->assertSame('0011', $savedProfile->bpjs_number_last4);
        $raw = (string) DB::table('patient_profiles')->where('id', $savedProfile->id)->value('bpjs_number');
        $this->assertStringNotContainsString($number, $raw, 'Nomor BPJS profil tersimpan terenkripsi.');

        // R-a: hanya penyimpan BPJS yang menulis audit `patient.updated`; nomor tidak bocor ke audit.
        $audit = AuditLog::query()->where('action', 'patient.updated')->sole();
        $this->assertSame($savedProfile->id, (int) $audit->auditable_id);
        $this->assertStringNotContainsString($number, json_encode([$audit->old_values, $audit->new_values, $audit->description]));

        $this->assertNull(PatientProfile::query()->findOrFail($keeper->patientProfile->id)->bpjs_number, 'Tanpa save_bpjs profil tidak berubah.');
        $this->assertNull(PatientProfile::query()->findOrFail($cash->patientProfile->id)->bpjs_number, 'save_bpjs hanya berlaku untuk pembayaran BPJS.');
    }

    // ------------------------------------------------------------------ riwayat dan audit (F6-R35, Review Focus 5)

    public function test_the_audit_row_records_the_creation_without_personal_or_payment_data(): void
    {
        $user = $this->createPatient([], ['phone' => '081355577788', 'address' => 'Jl. Rahasia Kenanga No. 99 Tangerang']);
        $data = new BookingData(
            paymentType: PaymentType::Bpjs,
            paymentReference: '0009900000011',
            complaint: 'Nyeri dada hebat <script>alert(1)</script>',
            saveBpjs: true,
        );

        $appointment = $this->bookAs($user, $this->makeSession(), $data);

        $audit = AuditLog::query()->where('action', 'appointment.created')->sole();
        $this->assertSame(ActorType::Patient, $audit->actor_type);
        $this->assertSame($user->id, $audit->user_id);
        $this->assertSame($appointment->id, $audit->auditable_id);
        $this->assertNull($audit->old_values);
        $this->assertEquals([
            'booking_code' => $appointment->booking_code,
            'queue_number' => 1,
            'status' => 'pending',
            'channel' => 'online',
            'payment_type' => 'bpjs',
        ], $audit->new_values);
        $this->assertSame("Pendaftaran {$appointment->booking_code} dibuat (Online).", $audit->description);

        $dump = (string) json_encode([
            DB::table('audit_logs')->get()->all(),
            DB::table('appointment_status_histories')->get()->all(),
        ]);

        foreach (['0009900000011', 'Nyeri dada', '<script>', '081355577788', 'Rahasia Kenanga', $user->email, $user->name] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $dump, "Audit atau riwayat memuat: {$forbidden}");
        }
    }

    // ------------------------------------------------------------------ notifikasi (NTF-01, NTF-03)

    public function test_a_pending_booking_notifies_the_account_by_database_only_after_the_commit(): void
    {
        $user = $this->createPatient();
        $session = $this->makeSession();
        $baseLevel = DB::transactionLevel();
        $levels = [];
        Event::listen(NotificationSending::class, function () use (&$levels): void {
            $levels[] = DB::transactionLevel();
        });

        $appointment = $this->bookAs($user, $session);

        $this->assertSame([$baseLevel], $levels, 'Tepat satu pengiriman (database), sesudah transaksi pendaftaran commit.');
        $stored = $user->notifications()->sole();
        $this->assertSame(AppointmentStatusChangedNotification::class, $stored->type);
        $this->assertSame('pending', $stored->data['status']);
        $this->assertSame($appointment->booking_code, $stored->data['booking_code']);
        $this->assertSame($appointment->id, $stored->data['appointment_id']);
        $this->assertSame(0, $this->sentMailCount());
    }

    public function test_an_auto_confirmed_booking_notifies_by_database_and_mail(): void
    {
        app(SettingsService::class)->set('booking.auto_confirm', true);
        $user = $this->createPatient();

        $appointment = $this->bookAs($user, $this->makeSession());

        $this->assertSame(1, $user->notifications()->count());
        $this->assertSame('confirmed', $user->notifications()->sole()->data['status']);
        $messages = app('mailer')->getSymfonyTransport()->messages();
        $this->assertCount(1, $messages);
        $this->assertSame(
            "Status pendaftaran {$appointment->booking_code}: Dikonfirmasi",
            $messages->first()->getOriginalMessage()->getSubject(),
        );
    }

    public function test_a_counter_booking_for_a_profile_without_an_account_sends_nothing(): void
    {
        Notification::fake();
        $staff = $this->createStaff('registration-officer');
        $walkIn = PatientProfile::factory()->create();

        $this->service()->book($walkIn, $this->makeSession()->id, $this->umum(), Actor::staff($staff));

        Notification::assertNothingSent();
        $this->assertSame(0, DB::table('notifications')->count());
    }

    public function test_a_notification_failure_is_reported_and_the_booking_stays_saved(): void
    {
        $user = $this->createPatient();
        $session = $this->makeSession();
        Event::listen(NotificationSending::class, function (): void {
            throw new RuntimeException('Kanal notifikasi gagal.');
        });
        Exceptions::fake();

        $appointment = $this->bookAs($user, $session);

        $this->assertTrue(Appointment::query()->whereKey($appointment->id)->exists());
        $this->assertSame(['booked_count' => 1, 'last_queue_number' => 1], $this->counters($session->id));
        $this->assertSame(1, AuditLog::query()->where('action', 'appointment.created')->count());
        Exceptions::assertReportedCount(1);
        Exceptions::assertReported(fn (RuntimeException $e): bool => $e->getMessage() === 'Kanal notifikasi gagal.');
    }

    // ------------------------------------------------------------------ kode F.5 tanpa baris parsial (BKG-02..09)

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function failureScenarios(): array
    {
        return [
            'sesi tidak tersedia (BKG-05)' => ['session_unavailable', BookingException::SESSION_UNAVAILABLE],
            'sesi tidak ditemukan' => ['session_not_found', BookingException::SESSION_UNAVAILABLE],
            'di luar jendela (BKG-06)' => ['outside_window', BookingException::OUTSIDE_WINDOW],
            'lewat batas online (BKG-07)' => ['cutoff_passed', BookingException::CUTOFF_PASSED],
            'kuota penuh (BKG-04)' => ['quota_full', BookingException::QUOTA_FULL],
            'pendaftaran ganda (BKG-02)' => ['duplicate_booking', BookingException::DUPLICATE_BOOKING],
            'jadwal beririsan (BKG-03)' => ['overlapping_booking', BookingException::OVERLAPPING_BOOKING],
            'batas aktif (BKG-08)' => ['active_limit_reached', BookingException::ACTIVE_LIMIT_REACHED],
            'pembayaran tidak valid (BKG-09)' => ['invalid_payment', BookingException::INVALID_PAYMENT],
            'profil tidak aktif' => ['profile_inactive', BookingException::SESSION_UNAVAILABLE],
        ];
    }

    /**
     * @return array{0: PatientProfile, 1: int, 2: BookingData, 3: Actor}
     */
    private function scenario(string $case): array
    {
        $user = $this->createPatient();
        $profile = $user->patientProfile;
        $actor = Actor::patient($user);
        $data = $this->umum();

        switch ($case) {
            case 'session_unavailable':
                $sessionId = $this->makeSession(['status' => SessionStatus::Closed])->id;
                break;
            case 'session_not_found':
                $sessionId = 987654;
                break;
            case 'outside_window':
                $sessionId = $this->makeSession(['session_date' => '2026-11-20'])->id;
                break;
            case 'cutoff_passed':
                $sessionId = $this->makeSession(['session_date' => '2026-10-08', 'start_time' => '10:00:00', 'end_time' => '12:00:00'])->id;
                break;
            case 'quota_full':
                $session = $this->makeSession(['quota' => 1]);
                $this->bookAppointment($session);
                $sessionId = $session->id;
                break;
            case 'duplicate_booking':
                $session = $this->makeSession();
                $this->service()->book($profile, $session->id, $data, $actor);
                $sessionId = $session->id;
                break;
            case 'overlapping_booking':
                $first = $this->makeSession(['start_time' => '09:00:00', 'end_time' => '12:00:00']);
                $this->service()->book($profile, $first->id, $data, $actor);
                $sessionId = $this->makeSession(['start_time' => '11:00:00', 'end_time' => '13:00:00'])->id;
                break;
            case 'active_limit_reached':
                foreach (['2026-10-09', '2026-10-10', '2026-10-11'] as $date) {
                    $this->service()->book($profile, $this->makeSession(['session_date' => $date])->id, $data, $actor);
                }
                $sessionId = $this->makeSession(['session_date' => '2026-10-12'])->id;
                break;
            case 'invalid_payment':
                $sessionId = $this->makeSession()->id;
                $data = new BookingData(paymentType: PaymentType::Bpjs, paymentReference: '123456789012');
                break;
            case 'profile_inactive':
                $profile->forceFill(['is_active' => false])->save();
                $sessionId = $this->makeSession()->id;
                break;
            default:
                $this->fail("Skenario tidak dikenal: {$case}");
        }

        return [$profile, $sessionId, $data, $actor];
    }

    #[DataProvider('failureScenarios')]
    public function test_every_f5_failure_has_its_code_and_leaves_no_partial_rows(string $case, string $expectedCode): void
    {
        Notification::fake();
        [$profile, $sessionId, $data, $actor] = $this->scenario($case);
        $before = $this->snapshot($sessionId);

        try {
            $this->service()->book($profile, $sessionId, $data, $actor);
            $this->fail("Pendaftaran skenario {$case} seharusnya ditolak.");
        } catch (BookingException $e) {
            $this->assertSame($expectedCode, $e->errorCode);
            $this->assertNotSame('', $e->getMessage());
        }

        $this->assertSame($before, $this->snapshot($sessionId), 'Tidak boleh ada baris parsial atau perubahan pencacah sesi.');
    }

    public function test_an_inactive_profile_is_refused_with_the_profile_message_inside_the_transaction(): void
    {
        $user = $this->createPatient();
        $profile = $user->patientProfile;
        $session = $this->makeSession();
        // Instansi pemanggil masih menganggap profil aktif; baris di DB sudah nonaktif (dibaca ulang di bawah kunci).
        DB::table('patient_profiles')->where('id', $profile->id)->update(['is_active' => false]);

        try {
            $this->service()->book($profile, $session->id, $this->umum(), Actor::patient($user));
            $this->fail('Profil nonaktif seharusnya ditolak.');
        } catch (BookingException $e) {
            $this->assertSame(BookingException::SESSION_UNAVAILABLE, $e->errorCode);
            $this->assertSame('Profil pasien tidak aktif atau tidak ditemukan.', $e->getMessage());
        }

        $this->assertSame(0, Appointment::query()->count());
    }

    public function test_the_duplicate_message_names_the_subject_by_channel(): void
    {
        $session = $this->makeSession();
        $user = $this->createPatient();
        $staff = $this->createStaff('registration-officer');
        $this->bookAs($user, $session);

        foreach ([[Actor::patient($user), 'Anda'], [Actor::staff($staff), 'pasien ini']] as [$actor, $subject]) {
            try {
                $this->service()->book($user->patientProfile, $session->id, $this->umum(), $actor);
                $this->fail('Pendaftaran ganda seharusnya ditolak.');
            } catch (BookingException $e) {
                $this->assertSame(BookingException::DUPLICATE_BOOKING, $e->errorCode);
                $this->assertSame(__('booking.errors.DUPLICATE_BOOKING', ['subjek' => $subject]), $e->getMessage());
                $this->assertNotNull($e->context['appointment_code'] ?? null, 'Aturan F.5 menyertakan kode pendaftaran yang sudah ada.');
            }
        }
    }

    public function test_the_actor_guards_reject_system_actors_and_other_patients_profiles(): void
    {
        $owner = $this->createPatient();
        $stranger = $this->createPatient();
        $session = $this->makeSession();
        $before = $this->snapshot($session->id);

        try {
            $this->service()->book($owner->patientProfile, $session->id, $this->umum(), Actor::system());
            $this->fail('Pelaku sistem seharusnya ditolak.');
        } catch (LogicException $e) {
            $this->assertSame('Pendaftaran tidak dapat dibuat oleh pelaku sistem.', $e->getMessage());
        }

        $this->expectException(AuthorizationException::class);

        try {
            $this->service()->book($owner->patientProfile, $session->id, $this->umum(), Actor::patient($stranger));
        } finally {
            $this->assertSame($before, $this->snapshot($session->id));
        }
    }

    public function test_a_failure_after_all_inserts_rolls_back_the_appointment_history_audit_and_counters(): void
    {
        Notification::fake();
        $user = $this->createPatient();
        $session = $this->makeSession();
        $before = $this->snapshot($session->id);
        // Langkah terakhir (simpan nomor BPJS ke profil) gagal sesudah appointment, riwayat, dan audit tertulis.
        PatientProfile::updating(function (): void {
            throw new RuntimeException('Gagal menyimpan profil.');
        });

        try {
            $this->bookAs($user, $session, new BookingData(paymentType: PaymentType::Bpjs, paymentReference: '0009900000011', saveBpjs: true));
            $this->fail('Kegagalan penyimpanan profil seharusnya dilempar ulang.');
        } catch (RuntimeException $e) {
            $this->assertSame('Gagal menyimpan profil.', $e->getMessage());
        }

        $this->assertSame($before, $this->snapshot($session->id));
        Notification::assertNothingSent();
    }

    // ------------------------------------------------------------------ BKG-18: pelanggaran DB nyata dipetakan

    public function test_a_real_active_unique_violation_is_mapped_to_duplicate_booking(): void
    {
        $user = $this->createPatient();
        $profile = $user->patientProfile;
        $session = $this->makeSession();
        $before = $this->snapshot($session->id);
        $injected = 0;
        // Sesudah aturan F.5 lolos dan sebelum INSERT, "pendaftaran lain" pasien yang sama pada sesi ini masuk
        // (balapan yang lolos dari pemeriksaan): INSERT kita melanggar UNIQUE `active_flag` yang sebenarnya.
        PracticeSession::updating(function (PracticeSession $locked) use ($profile, &$injected): void {
            if ($injected++ > 0) {
                return;
            }

            DB::table('appointments')->insert([
                'booking_code' => 'UPH-ZZZZ-ZZZZ',
                'patient_profile_id' => $profile->id,
                'practice_session_id' => $locked->id,
                'doctor_id' => $locked->doctor_id,
                'clinic_id' => $locked->clinic_id,
                'session_date' => $locked->session_date->toDateString(),
                'queue_number' => 99,
                'status' => 'pending',
                'channel' => 'online',
                'payment_type' => 'umum',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        try {
            $this->bookAs($user, $session);
            $this->fail('Pelanggaran UNIQUE aktif seharusnya menjadi BookingException.');
        } catch (BookingException $e) {
            $this->assertSame(BookingException::DUPLICATE_BOOKING, $e->errorCode);
            $this->assertSame(__('booking.errors.DUPLICATE_BOOKING', ['subjek' => 'Anda']), $e->getMessage());
            $previous = $e->getPrevious();
            $this->assertInstanceOf(UniqueConstraintViolationException::class, $previous);
            $this->assertSame(1062, $previous->errorInfo[1]);
            $this->assertStringContainsString('appointments_patient_session_active_unique', (string) $previous->errorInfo[2]);
        }

        $this->assertSame(1, $injected);
        $this->assertSame($before, $this->snapshot($session->id), 'Savepoint dibatalkan: tanpa baris parsial, pencacah sesi kembali.');
    }

    public function test_a_real_quota_check_violation_is_mapped_to_quota_full(): void
    {
        $user = $this->createPatient();
        $session = $this->makeSession(['quota' => 5]);
        $before = $this->snapshot($session->id);
        // Pencacah ditulis melewati kuota (booked_count = quota + 1) sehingga CHECK `chk_sessions_quota` yang
        // sebenarnya menolak UPDATE. `last_queue_number` ikut dinaikkan agar `chk_sessions_queue` tidak ikut gagal.
        PracticeSession::updating(function (PracticeSession $locked): void {
            $locked->booked_count = $locked->quota + 1;
            $locked->last_queue_number = $locked->quota + 1;
        });

        try {
            $this->bookAs($user, $session);
            $this->fail('Pelanggaran CHECK kuota seharusnya menjadi BookingException.');
        } catch (BookingException $e) {
            $this->assertSame(BookingException::QUOTA_FULL, $e->errorCode);
            $this->assertSame(__('booking.errors.QUOTA_FULL'), $e->getMessage());
            $previous = $e->getPrevious();
            $this->assertInstanceOf(QueryException::class, $previous);
            $this->assertNotInstanceOf(UniqueConstraintViolationException::class, $previous);
            $this->assertContains($previous->errorInfo[1], [4025, 3819]);
            $this->assertStringContainsString('chk_sessions_quota', (string) $previous->errorInfo[2]);
        }

        $this->assertSame($before, $this->snapshot($session->id));
    }

    public function test_other_check_violations_are_not_mapped_and_surface_as_system_errors(): void
    {
        $user = $this->createPatient();
        $session = $this->makeSession();
        $before = $this->snapshot($session->id);
        // `last_queue_number` di bawah `booked_count` melanggar `chk_sessions_queue`, bukan `chk_sessions_quota`.
        PracticeSession::updating(function (PracticeSession $locked): void {
            $locked->last_queue_number = 0;
        });

        try {
            $this->bookAs($user, $session);
            $this->fail('Pelanggaran CHECK selain kuota seharusnya tetap berupa QueryException (galat sistem).');
        } catch (QueryException $e) {
            $this->assertContains($e->errorInfo[1], [4025, 3819]);
            $this->assertStringContainsString('chk_sessions_queue', (string) $e->errorInfo[2]);
        }

        $this->assertSame($before, $this->snapshot($session->id));
    }

    public function test_a_booking_code_collision_is_retried_with_a_new_code_in_a_new_round(): void
    {
        $session = $this->makeSession();
        $existing = $this->bookAppointment($session);
        $user = $this->createPatient();
        $calls = 0;
        // Tabrakan UNIQUE `booking_code` yang sebenarnya pada percobaan pertama.
        Appointment::creating(function (Appointment $appointment) use ($existing, &$calls): void {
            if (++$calls === 1) {
                $appointment->booking_code = $existing->booking_code;
            }
        });

        $appointment = $this->bookAs($user, $session);

        $this->assertSame(2, $calls, 'Putaran pertama gagal (1062), putaran kedua berhasil.');
        $this->assertNotSame($existing->booking_code, $appointment->booking_code);
        $this->assertTrue(BookingCodeGenerator::isValid($appointment->booking_code));
        $this->assertSame(2, $appointment->queue_number, 'Putaran gagal tidak menghabiskan nomor antrean.');
        $this->assertSame(['booked_count' => 2, 'last_queue_number' => 2], $this->counters($session->id));
        $this->assertSame(2, Appointment::query()->count());
        $this->assertSame(1, AuditLog::query()->where('action', 'appointment.created')->count());
        $this->assertSame(1, AppointmentStatusHistory::query()->where('appointment_id', $appointment->id)->count());
    }

    public function test_a_queue_number_collision_is_retried_and_the_round_leaves_no_trace(): void
    {
        $session = $this->makeSession();
        $this->bookAppointment($session);
        $user = $this->createPatient();
        $calls = 0;
        // Nomor antrean 1 sudah dipakai: UNIQUE `(practice_session_id, queue_number)` yang sebenarnya menolak.
        Appointment::creating(function (Appointment $appointment) use (&$calls): void {
            if (++$calls === 1) {
                $appointment->queue_number = 1;
            }
        });

        $appointment = $this->bookAs($user, $session);

        $this->assertSame(2, $calls);
        $this->assertSame(2, $appointment->queue_number);
        $this->assertSame(['booked_count' => 2, 'last_queue_number' => 2], $this->counters($session->id));
        $this->assertSame(2, Appointment::query()->count());
    }

    public function test_repeated_unique_collisions_stop_after_the_rounds_and_become_system_busy_with_a_report(): void
    {
        $session = $this->makeSession();
        $existing = $this->bookAppointment($session);
        $user = $this->createPatient();
        $before = $this->snapshot($session->id);
        $calls = 0;
        Appointment::creating(function (Appointment $appointment) use ($existing, &$calls): void {
            $calls++;
            $appointment->booking_code = $existing->booking_code;
        });
        Exceptions::fake();

        try {
            $this->bookAs($user, $session);
            $this->fail('Tabrakan kode terus-menerus seharusnya berakhir sebagai SYSTEM_BUSY.');
        } catch (BookingException $e) {
            $this->assertSame(BookingException::SYSTEM_BUSY, $e->errorCode);
            $this->assertSame(__('booking.errors.SYSTEM_BUSY'), $e->getMessage());
            $this->assertInstanceOf(UniqueConstraintViolationException::class, $e->getPrevious());
        }

        $this->assertSame(BookingService::UNIQUE_ROUNDS, $calls);
        $this->assertSame($before, $this->snapshot($session->id));
        Exceptions::assertReportedCount(1);
        Exceptions::assertReported(UniqueConstraintViolationException::class);
    }

    // ------------------------------------------------------------------ urutan kunci (Review Focus 1)

    public function test_the_first_statements_are_the_profile_lock_then_the_session_lock_and_nothing_else_is_locked(): void
    {
        $user = $this->createPatient();
        $session = $this->makeSession();
        // Pemanasan: pengaturan dibaca sebelum transaksi sehingga tidak ada kueri pengaturan di dalamnya.
        $this->app->make(SettingsService::class)->bookingRules();
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        $this->bookAs($user, $session);

        $this->assertStringContainsString('`patient_profiles`', $queries[0]);
        $this->assertStringContainsString('for update', $queries[0], 'Pernyataan pertama transaksi adalah kunci profil.');
        $this->assertStringContainsString('`practice_sessions`', $queries[1]);
        $this->assertStringContainsString('for update', $queries[1], 'Pernyataan kedua adalah kunci sesi.');

        $locking = array_values(array_filter($queries, fn (string $sql): bool => str_contains($sql, 'for update')));
        $this->assertCount(2, $locking, 'Hanya dua pembacaan terkunci (profil dan sesi); appointment tidak dikunci lewat rentang.');
    }

    // ------------------------------------------------------------------ precheck (F6-R12)

    public function test_precheck_passes_for_a_bookable_session_and_writes_and_locks_nothing(): void
    {
        $user = $this->createPatient();
        $session = $this->makeSession();
        $this->app->make(SettingsService::class)->bookingRules();
        $before = $this->snapshot($session->id);
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = strtolower($query->sql);
        });

        $this->service()->precheck($user->patientProfile, $session, $this->umum(), Actor::patient($user));

        $this->assertNotEmpty($queries, 'Precheck membaca data (SELECT biasa).');
        foreach ($queries as $sql) {
            $this->assertDoesNotMatchRegularExpression('/^\s*(insert|update|delete|replace)\b/', $sql);
            $this->assertStringNotContainsString('for update', $sql);
            $this->assertStringNotContainsString('lock in share mode', $sql);
        }
        $this->assertSame($before, $this->snapshot($session->id));
    }

    public function test_precheck_reads_the_current_session_and_throws_the_same_codes_as_book(): void
    {
        $user = $this->createPatient();
        $session = $this->makeSession(['quota' => 1]);
        $stale = PracticeSession::query()->findOrFail($session->id);
        $this->bookAppointment($session);
        $before = $this->snapshot($session->id);

        $this->assertSame(0, $stale->booked_count, 'Instansi lama masih mengira sesi kosong.');

        try {
            $this->service()->precheck($user->patientProfile, $stale, $this->umum(), Actor::patient($user));
            $this->fail('Precheck seharusnya melihat sesi yang sudah penuh.');
        } catch (BookingException $e) {
            $this->assertSame(BookingException::QUOTA_FULL, $e->errorCode);
        }

        $this->assertSame($before, $this->snapshot($session->id));
    }

    public function test_precheck_rejects_inactive_profiles_missing_sessions_and_foreign_profiles(): void
    {
        $user = $this->createPatient();
        $stranger = $this->createPatient();
        $session = $this->makeSession();
        $ghost = new PracticeSession;
        $ghost->id = 987654;

        try {
            $this->service()->precheck($user->patientProfile, $ghost, $this->umum(), Actor::patient($user));
            $this->fail('Sesi yang tidak ada seharusnya ditolak.');
        } catch (BookingException $e) {
            $this->assertSame(BookingException::SESSION_UNAVAILABLE, $e->errorCode);
        }

        $inactive = PatientProfile::query()->findOrFail($user->patientProfile->id)->forceFill(['is_active' => false]);

        try {
            $this->service()->precheck($inactive, $session, $this->umum(), Actor::patient($user));
            $this->fail('Profil nonaktif seharusnya ditolak.');
        } catch (BookingException $e) {
            $this->assertSame('Profil pasien tidak aktif atau tidak ditemukan.', $e->getMessage());
        }

        $this->expectException(AuthorizationException::class);
        $this->service()->precheck($user->patientProfile, $session, $this->umum(), Actor::patient($stranger));
    }
}
