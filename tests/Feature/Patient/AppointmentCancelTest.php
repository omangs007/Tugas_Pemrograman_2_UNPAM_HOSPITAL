<?php

namespace Tests\Feature\Patient;

use App\Enums\ActorType;
use App\Enums\AppointmentStatus;
use App\Enums\PaymentType;
use App\Enums\SessionStatus;
use App\Models\Appointment;
use App\Models\AppointmentStatusHistory;
use App\Models\AuditLog;
use App\Models\Doctor;
use App\Models\PracticeSession;
use App\Models\User;
use App\Notifications\AppointmentStatusChangedNotification;
use App\Services\Settings\SettingsService;
use Carbon\CarbonImmutable;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InteractsWithMasterData;
use Tests\Support\InteractsWithPatients;
use Tests\Support\InteractsWithSchedules;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

/**
 * Pembatalan oleh pasien (V-12, F6-R25, BR-73..BR-81) dan tampilannya di halaman detail: PAS-03, BKG-11/13 (sisi
 * HTTP), NTF-01/02/07 (sisi batal), batas tepat 120/119 menit, status check-in/final, alasan 255 karakter, 404
 * pemilik lain, modal dan teks "hubungi RS". Waktu dibekukan pada Senin 2026-10-12 08.00; sesi bawaan besok 09.00.
 */
class AppointmentCancelTest extends TestCase
{
    use InteractsWithMasterData;
    use InteractsWithPatients;
    use InteractsWithSchedules;
    use InteractsWithStaff;
    use RefreshDatabase;

    private const SUCCESS = 'Pendaftaran berhasil dibatalkan. Kuota telah dilepas.';

    private const BPJS = '0009900000011';

    private Doctor $doctor;

    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SettingsSeeder::class);
        $this->travelTo(CarbonImmutable::parse('2026-10-12 08:00:00'));

        $clinic = $this->createClinic(['name' => 'Poli Anak', 'code' => 'ANK']);
        $this->doctor = $this->createDoctor(['name' => 'Sinta Maharani', 'title_prefix' => 'dr.', 'title_suffix' => 'Sp.A'], $clinic);
    }

    // --- pembantu ----------------------------------------------------------------------------

    private function code(): string
    {
        return sprintf('UPH-ABCD-%04d', ++$this->sequence);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function sessionOn(string $date, string $start = '09:00:00', string $end = '12:00:00', array $overrides = []): PracticeSession
    {
        return $this->createSession($overrides + [
            'doctor_id' => $this->doctor->id,
            'clinic_id' => $this->doctor->clinic_id,
            'session_date' => $date,
            'start_time' => $start,
            'end_time' => $end,
            'doctor_schedule_id' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function book(User $user, PracticeSession $session, AppointmentStatus $status = AppointmentStatus::Confirmed, array $overrides = []): Appointment
    {
        return $this->bookAppointment($session, $status, array_merge([
            'booking_code' => $this->code(),
            'patient_profile_id' => $user->patientProfile->id,
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function cancel(Appointment|string $target, array $data = []): TestResponse
    {
        $code = $target instanceof Appointment ? $target->booking_code : $target;

        return $this->post(route('patient.appointments.cancel', $code), $data);
    }

    private function showUrl(Appointment $appointment): string
    {
        return route('patient.appointments.show', $appointment->booking_code);
    }

    private function currentStatus(Appointment $appointment): AppointmentStatus
    {
        return Appointment::query()->findOrFail($appointment->id)->status;
    }

    private function historyCount(Appointment $appointment): int
    {
        return AppointmentStatusHistory::query()->where('appointment_id', $appointment->id)->count();
    }

    // --- akses -------------------------------------------------------------------------------

    public function test_guests_are_sent_to_the_login_page_and_nothing_changes(): void
    {
        $owner = $this->createPatient();
        $appointment = $this->book($owner, $this->sessionOn('2026-10-13'));

        $this->cancel($appointment)->assertRedirect(route('login'));

        $this->assertSame(AppointmentStatus::Confirmed, $this->currentStatus($appointment));
    }

    public function test_staff_are_sent_to_the_admin_dashboard_and_nothing_changes(): void
    {
        $owner = $this->createPatient();
        $appointment = $this->book($owner, $this->sessionOn('2026-10-13'));

        $this->actingAs($this->createStaff('content-admin'));
        $this->cancel($appointment)->assertRedirect(route('admin.dashboard'));

        $this->assertSame(AppointmentStatus::Confirmed, $this->currentStatus($appointment));
    }

    // --- PAS-03 dan Review Focus E.3 ---------------------------------------------------------

    public function test_cancelling_another_patients_appointment_gives_404_without_side_effects(): void
    {
        Notification::fake();
        $owner = $this->createPatient(['email' => 'pemilik@contoh.test']);
        $intruder = $this->createPatient(['email' => 'penyusup@contoh.test']);
        $session = $this->sessionOn('2026-10-13');
        $appointment = $this->book($owner, $session);

        $this->actingAs($intruder);
        $this->cancel($appointment, ['cancel_reason' => 'Bukan milik saya'])->assertNotFound();
        // Alasan terlalu panjang: tetap 404 (otorisasi mendahului validasi), bukan galat validasi.
        $this->cancel($appointment, ['cancel_reason' => str_repeat('a', 300)])->assertNotFound();

        $this->assertSame(AppointmentStatus::Confirmed, $this->currentStatus($appointment));
        $this->assertSame(1, $session->fresh()->booked_count);
        $this->assertSame(0, $this->historyCount($appointment));
        $this->assertSame(0, AuditLog::query()->where('action', 'appointment.status_changed')->count());
        Notification::assertNothingSent();
    }

    public function test_malformed_and_unknown_codes_give_404(): void
    {
        $this->actingAs($this->createPatient());

        $this->cancel('XYZ')->assertNotFound();
        $this->cancel('UPH-ILLE-OOOO')->assertNotFound();
        $this->cancel('UPH-ABCD-9999')->assertNotFound();
    }

    // --- pembatalan sukses -------------------------------------------------------------------

    public function test_a_successful_cancellation_flashes_releases_quota_and_records_the_trail(): void
    {
        $user = $this->createPatient();
        $session = $this->sessionOn('2026-10-13');
        $appointment = $this->book($user, $session);
        $this->assertSame(1, $session->booked_count);
        $this->assertSame(1, $session->last_queue_number);

        $this->actingAs($user);
        $this->cancel($appointment, ['cancel_reason' => 'Berhalangan hadir'])
            ->assertRedirect($this->showUrl($appointment))
            ->assertSessionHas('success', self::SUCCESS)
            ->assertSessionHasNoErrors();

        $fresh = Appointment::query()->findOrFail($appointment->id);
        $this->assertSame(AppointmentStatus::Cancelled, $fresh->status);
        $this->assertNotNull($fresh->cancelled_at);
        $this->assertSame(ActorType::Patient, $fresh->cancelled_by_type);
        $this->assertSame($user->id, (int) $fresh->cancelled_by_user_id);
        $this->assertSame('Berhalangan hadir', $fresh->cancel_reason);
        $this->assertNull($fresh->active_flag);

        // BKG-11/BKG-13: kuota dilepas, penghitung antrean tidak turun (nomor tidak dipakai ulang).
        $sessionNow = $session->fresh();
        $this->assertSame(0, $sessionNow->booked_count);
        $this->assertSame(1, $sessionNow->last_queue_number);

        $history = AppointmentStatusHistory::query()->where('appointment_id', $appointment->id)->get();
        $this->assertCount(1, $history);
        $this->assertSame(AppointmentStatus::Confirmed, $history[0]->from_status);
        $this->assertSame(AppointmentStatus::Cancelled, $history[0]->to_status);
        $this->assertSame(ActorType::Patient, $history[0]->actor_type);
        $this->assertSame($user->id, (int) $history[0]->actor_user_id);
        $this->assertSame(1, AuditLog::query()->where('action', 'appointment.status_changed')->count());

        // Flash tampil di halaman detail berikutnya dan status berubah.
        $this->get($this->showUrl($appointment))
            ->assertOk()
            ->assertSee(self::SUCCESS)
            ->assertSee('Dibatalkan');
    }

    public function test_a_pending_appointment_can_be_cancelled_too(): void
    {
        $user = $this->createPatient();
        $session = $this->sessionOn('2026-10-13');
        $appointment = $this->book($user, $session, AppointmentStatus::Pending);

        $this->actingAs($user);
        $this->cancel($appointment)->assertRedirect($this->showUrl($appointment))->assertSessionHas('success', self::SUCCESS);

        $this->assertSame(AppointmentStatus::Cancelled, $this->currentStatus($appointment));
        $this->assertSame(0, $session->fresh()->booked_count);
    }

    /**
     * @return array<string, array{0: array<string, mixed>}>
     */
    public static function emptyReasons(): array
    {
        return [
            'tanpa field' => [[]],
            'kosong' => [['cancel_reason' => '']],
            'spasi saja' => [['cancel_reason' => '   ']],
            'null' => [['cancel_reason' => null]],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    #[DataProvider('emptyReasons')]
    public function test_the_reason_is_optional_and_stored_as_null_when_empty(array $payload): void
    {
        $user = $this->createPatient();
        $appointment = $this->book($user, $this->sessionOn('2026-10-13'));

        $this->actingAs($user);
        $this->cancel($appointment, $payload)->assertRedirect($this->showUrl($appointment))->assertSessionHas('success');

        $this->assertNull(Appointment::query()->findOrFail($appointment->id)->cancel_reason);
    }

    public function test_extra_fields_cannot_change_anything_except_the_cancellation(): void
    {
        $user = $this->createPatient();
        $appointment = $this->book($user, $this->sessionOn('2026-10-13'), AppointmentStatus::Confirmed, ['queue_number' => 1]);

        $this->actingAs($user);
        $this->cancel($appointment, [
            'cancel_reason' => 'Batal',
            'status' => 'completed',
            'booking_code' => 'UPH-ZZZZ-ZZZZ',
            'patient_profile_id' => 999999,
            'queue_number' => 99,
            'cancelled_by_type' => 'staff',
            'cancelled_by_user_id' => 12345,
        ])->assertRedirect($this->showUrl($appointment));

        $fresh = Appointment::query()->findOrFail($appointment->id);
        $this->assertSame(AppointmentStatus::Cancelled, $fresh->status);
        $this->assertSame($appointment->booking_code, $fresh->booking_code);
        $this->assertSame($appointment->patient_profile_id, $fresh->patient_profile_id);
        $this->assertSame(1, $fresh->queue_number);
        $this->assertSame(ActorType::Patient, $fresh->cancelled_by_type);
        $this->assertSame($user->id, (int) $fresh->cancelled_by_user_id);
    }

    // --- alasan: batas 255 dan input liar (V-12) ----------------------------------------------

    public function test_a_reason_of_exactly_255_characters_is_accepted_counted_in_characters_not_bytes(): void
    {
        $user = $this->createPatient();
        $appointment = $this->book($user, $this->sessionOn('2026-10-13'));
        $reason = str_repeat('é', 255);

        $this->actingAs($user);
        $this->cancel($appointment, ['cancel_reason' => $reason])->assertSessionHas('success', self::SUCCESS);

        $this->assertSame($reason, Appointment::query()->findOrFail($appointment->id)->cancel_reason);
    }

    public function test_a_reason_of_256_characters_is_rejected_with_the_indonesian_message(): void
    {
        $user = $this->createPatient();
        $session = $this->sessionOn('2026-10-13');
        $appointment = $this->book($user, $session);

        $this->actingAs($user);
        $this->cancel($appointment, ['cancel_reason' => str_repeat('a', 256)])
            ->assertRedirect($this->showUrl($appointment))
            ->assertSessionHas('error', 'Alasan maksimal 255 karakter.')
            ->assertSessionMissing('success');

        $this->assertSame(AppointmentStatus::Confirmed, $this->currentStatus($appointment));
        $this->assertSame(1, $session->fresh()->booked_count);
        $this->assertSame(0, $this->historyCount($appointment));

        $this->get($this->showUrl($appointment))->assertOk()->assertSee('Alasan maksimal 255 karakter.');
    }

    /**
     * @return array<string, array{0: mixed}>
     */
    public static function wildReasons(): array
    {
        return [
            'larik' => [['teks']],
            'larik asosiatif' => [['a' => 'b']],
            'larik bersarang' => [[['x']]],
        ];
    }

    #[DataProvider('wildReasons')]
    public function test_an_array_reason_is_rejected_without_a_server_error(mixed $reason): void
    {
        $user = $this->createPatient();
        $appointment = $this->book($user, $this->sessionOn('2026-10-13'));

        $this->actingAs($user);
        $this->cancel($appointment, ['cancel_reason' => $reason])
            ->assertRedirect($this->showUrl($appointment))
            ->assertSessionHas('error', 'Alasan tidak valid.');

        $this->assertSame(AppointmentStatus::Confirmed, $this->currentStatus($appointment));
    }

    // --- notifikasi (NTF-01, NTF-02, NTF-07) -------------------------------------------------

    public function test_cancellation_notifies_the_patient_by_mail_and_database(): void
    {
        Notification::fake();
        $user = $this->createPatient();
        $appointment = $this->book($user, $this->sessionOn('2026-10-13'));

        $this->actingAs($user);
        $this->cancel($appointment)->assertSessionHas('success');

        Notification::assertSentToTimes($user, AppointmentStatusChangedNotification::class, 1);
        Notification::assertSentTo(
            $user,
            AppointmentStatusChangedNotification::class,
            fn (AppointmentStatusChangedNotification $notification, array $channels): bool => $notification->to === AppointmentStatus::Cancelled
                && $notification->appointment->is($appointment)
                && in_array('mail', $channels, true)
                && in_array('database', $channels, true),
        );
    }

    public function test_the_database_notification_carries_no_sensitive_data(): void
    {
        $user = $this->createPatient();
        $appointment = $this->book($user, $this->sessionOn('2026-10-13'), AppointmentStatus::Confirmed, [
            'payment_type' => PaymentType::Bpjs,
            'payment_reference' => self::BPJS,
            'payment_reference_last4' => '0011',
            'complaint' => 'Nyeri dada kiri sejak semalam',
        ]);

        $this->actingAs($user);
        $this->cancel($appointment, ['cancel_reason' => 'Berhalangan'])->assertSessionHas('success');

        $rows = DB::table('notifications')->where('notifiable_id', $user->id)->get();
        $this->assertCount(1, $rows);
        $data = json_decode((string) $rows[0]->data, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame($appointment->booking_code, $data['booking_code']);
        $this->assertSame('cancelled', $data['status']);
        $this->assertArrayNotHasKey('complaint', $data);
        $this->assertArrayNotHasKey('payment_reference', $data);
        $encoded = (string) $rows[0]->data;
        $this->assertStringNotContainsString(self::BPJS, $encoded);
        $this->assertStringNotContainsString('Nyeri dada', $encoded);
    }

    // --- batas waktu tepat (Review Focus E.2) ------------------------------------------------

    /**
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function cutoffMoments(): array
    {
        return [
            '121 menit sebelum sesi' => ['06:59:00', true],
            '120 menit tepat (inklusif)' => ['07:00:00', true],
            '119 menit 59 detik' => ['07:00:01', false],
            '119 menit' => ['07:01:00', false],
            '1 menit sebelum sesi' => ['08:59:00', false],
        ];
    }

    #[DataProvider('cutoffMoments')]
    public function test_the_patient_cancellation_cutoff_is_inclusive_at_exactly_120_minutes(string $time, bool $allowed): void
    {
        $user = $this->createPatient();
        $session = $this->sessionOn('2026-10-12');
        $appointment = $this->book($user, $session);

        $this->travelTo(CarbonImmutable::parse('2026-10-12 '.$time));
        $this->actingAs($user);
        $response = $this->cancel($appointment)->assertRedirect($this->showUrl($appointment));

        if ($allowed) {
            $response->assertSessionHas('success', self::SUCCESS);
            $this->assertSame(AppointmentStatus::Cancelled, $this->currentStatus($appointment));
            $this->assertSame(0, $session->fresh()->booked_count);

            return;
        }

        $response->assertSessionMissing('success');
        $message = (string) session('error');
        $this->assertStringContainsString('Batas pembatalan online telah lewat', $message);
        $this->assertStringContainsString('120 menit', $message);
        $this->assertStringContainsString('(021) 0000-0000', $message);
        $this->assertSame(AppointmentStatus::Confirmed, $this->currentStatus($appointment));
        $this->assertSame(1, $session->fresh()->booked_count);
        $this->assertSame(0, $this->historyCount($appointment));
    }

    public function test_the_cutoff_follows_the_booking_setting(): void
    {
        app(SettingsService::class)->set('booking.cancel_cutoff_minutes', 60);
        $user = $this->createPatient();
        $first = $this->book($user, $this->sessionOn('2026-10-12'));

        $this->actingAs($user);
        $this->travelTo(CarbonImmutable::parse('2026-10-12 08:00:00'));
        $this->cancel($first)->assertSessionHas('success', self::SUCCESS);

        $second = $this->book($user, $this->sessionOn('2026-10-12', '14:00:00', '16:00:00'));
        $this->travelTo(CarbonImmutable::parse('2026-10-12 13:00:01'));
        $this->cancel($second)->assertSessionMissing('success');
        $this->assertStringContainsString('60 menit', (string) session('error'));
        $this->assertSame(AppointmentStatus::Confirmed, $this->currentStatus($second));
    }

    // --- status yang tidak dapat dibatalkan --------------------------------------------------

    public function test_a_checked_in_appointment_cannot_be_cancelled_by_the_patient(): void
    {
        $user = $this->createPatient();
        $session = $this->sessionOn('2026-10-13');
        $appointment = $this->book($user, $session, AppointmentStatus::CheckedIn);

        $this->actingAs($user);
        $this->cancel($appointment)
            ->assertRedirect($this->showUrl($appointment))
            ->assertSessionHas('error', fn (string $message): bool => str_contains($message, 'sudah check-in tidak dapat dibatalkan'))
            ->assertSessionMissing('success');

        $this->assertSame(AppointmentStatus::CheckedIn, $this->currentStatus($appointment));
        $this->assertSame(1, $session->fresh()->booked_count);
        $this->assertSame(0, $this->historyCount($appointment));
    }

    /**
     * @return array<string, array{0: AppointmentStatus}>
     */
    public static function finalStatuses(): array
    {
        return [
            'selesai' => [AppointmentStatus::Completed],
            'dibatalkan' => [AppointmentStatus::Cancelled],
            'tidak hadir' => [AppointmentStatus::NoShow],
        ];
    }

    #[DataProvider('finalStatuses')]
    public function test_a_final_appointment_cannot_be_cancelled_and_keeps_the_quota_untouched(AppointmentStatus $status): void
    {
        $user = $this->createPatient();
        $session = $this->sessionOn('2026-10-13');
        $appointment = $this->book($user, $session, $status);
        $bookedBefore = $session->fresh()->booked_count;

        $this->actingAs($user);
        $this->cancel($appointment)
            ->assertRedirect($this->showUrl($appointment))
            ->assertSessionHas('error', fn (string $message): bool => str_contains($message, 'Pendaftaran ini sudah berstatus'))
            ->assertSessionMissing('success');

        $this->assertSame($status, $this->currentStatus($appointment));
        $this->assertSame($bookedBefore, $session->fresh()->booked_count);
        $this->assertSame(0, $this->historyCount($appointment));
    }

    public function test_a_second_submission_after_success_fails_without_releasing_the_quota_twice(): void
    {
        $user = $this->createPatient();
        $session = $this->sessionOn('2026-10-13');
        $appointment = $this->book($user, $session);
        $other = $this->book($this->createPatient(['email' => 'lain@contoh.test']), $session, AppointmentStatus::Confirmed, ['queue_number' => 2]);
        $this->assertSame(2, $session->fresh()->booked_count);

        $this->actingAs($user);
        $this->cancel($appointment)->assertSessionHas('success');
        $this->cancel($appointment)->assertSessionHas('error')->assertSessionMissing('success');

        $this->assertSame(1, $session->fresh()->booked_count, 'Kuota hanya dilepas satu kali; pendaftaran pasien lain tetap menghitung.');
        $this->assertSame(1, $this->historyCount($appointment));
        $this->assertSame(AppointmentStatus::Confirmed, $this->currentStatus($other));
    }

    public function test_appointments_of_closed_cancelled_and_needs_review_sessions_can_still_be_cancelled(): void
    {
        $user = $this->createPatient();
        $this->actingAs($user);

        $closed = $this->sessionOn('2026-10-13', '09:00:00', '12:00:00', ['status' => SessionStatus::Closed, 'status_reason' => 'Ditutup sementara']);
        $cancelledSession = $this->sessionOn('2026-10-14', '09:00:00', '12:00:00', ['status' => SessionStatus::Cancelled, 'status_reason' => 'Dokter cuti']);
        $review = PracticeSession::factory()->needsReview(['source' => 'exception', 'exception_id' => 1])->create([
            'doctor_id' => $this->doctor->id,
            'clinic_id' => $this->doctor->clinic_id,
            'session_date' => '2026-10-15',
            'start_time' => '09:00:00',
            'end_time' => '12:00:00',
            'doctor_schedule_id' => null,
        ]);

        foreach ([$closed, $cancelledSession, $review] as $session) {
            $appointment = $this->book($user, $session);
            $this->cancel($appointment)->assertSessionHas('success', self::SUCCESS);

            $this->assertSame(AppointmentStatus::Cancelled, $this->currentStatus($appointment));
            $this->assertSame(0, $session->fresh()->booked_count);
        }
    }

    // --- tampilan di halaman detail (F6-R25) -------------------------------------------------

    public function test_the_detail_page_offers_the_button_modal_and_noscript_form_before_the_cutoff(): void
    {
        $user = $this->createPatient();
        $appointment = $this->book($user, $this->sessionOn('2026-10-13'));
        $code = $appointment->booking_code;
        $action = route('patient.appointments.cancel', $code);

        $response = $this->actingAs($user)->get($this->showUrl($appointment))->assertOk();
        $html = $response->getContent();

        $response->assertSee('Batalkan Pendaftaran')
            ->assertSee('data-bs-toggle="modal" data-bs-target="#modal-batal"', false)
            ->assertSee('id="modal-batal"', false)
            ->assertSee('Batalkan pendaftaran '.$code.'?')
            ->assertSee('Kuota akan dilepas dan nomor antrean Anda tidak dapat dipakai kembali.')
            ->assertSee('Alasan (opsional)')
            ->assertSee('Tidak, kembali')
            ->assertSee('Ya, batalkan')
            ->assertSee('maxlength="255"', false)
            ->assertDontSee('Batas pembatalan online telah lewat');

        // Dua formulir (modal dan noscript), masing-masing dengan token dan field alasan.
        $this->assertSame(2, substr_count($html, 'action="'.$action.'"'));
        $this->assertSame(2, substr_count($html, 'name="cancel_reason"'));
        $this->assertGreaterThanOrEqual(2, substr_count($html, 'name="_token"'));
        $this->assertSame(1, substr_count($html, 'id="modal-batal-alasan"'));
        $this->assertSame(1, substr_count($html, 'id="noscript-batal-alasan"'));
        $this->assertStringNotContainsString('<x-confirm-modal', $html);
    }

    public function test_the_noscript_block_holds_a_working_cancel_form_without_the_modal_trigger(): void
    {
        $user = $this->createPatient();
        $appointment = $this->book($user, $this->sessionOn('2026-10-13'));
        $action = route('patient.appointments.cancel', $appointment->booking_code);

        $html = $this->actingAs($user)->get($this->showUrl($appointment))->assertOk()->getContent();

        preg_match_all('#<noscript>(.*?)</noscript>#s', $html, $matches);
        $block = collect($matches[1])->first(fn (string $content): bool => str_contains($content, 'name="cancel_reason"'));

        $this->assertNotNull($block, 'Tidak ada <noscript> berisi formulir batal.');
        $this->assertStringContainsString('action="'.$action.'"', $block);
        $this->assertStringContainsString('name="_token"', $block);
        $this->assertStringContainsString('type="submit"', $block);
        $this->assertStringNotContainsString('data-bs-toggle', $block);
        $this->assertStringNotContainsString('data-bs-target', $block);
    }

    public function test_the_button_is_shown_at_exactly_120_minutes_and_replaced_by_contact_info_at_119(): void
    {
        $user = $this->createPatient();
        $appointment = $this->book($user, $this->sessionOn('2026-10-12'));
        $this->actingAs($user);

        $this->travelTo(CarbonImmutable::parse('2026-10-12 07:00:00'));
        $this->get($this->showUrl($appointment))
            ->assertOk()
            ->assertSee('data-bs-target="#modal-batal"', false)
            ->assertDontSee('Batas pembatalan online telah lewat');

        $this->travelTo(CarbonImmutable::parse('2026-10-12 07:01:00'));
        $html = $this->get($this->showUrl($appointment))
            ->assertOk()
            ->assertSee('Batas pembatalan online telah lewat. Untuk membatalkan atau mengubah pendaftaran, hubungi rumah sakit di (021) 0000-0000 atau melalui', false)
            ->assertSee('href="https://wa.me/6280000000000"', false)
            ->assertDontSee('data-bs-target="#modal-batal"', false)
            ->assertDontSee('name="cancel_reason"', false)
            ->getContent();
        $this->assertStringNotContainsString('action="'.route('patient.appointments.cancel', $appointment->booking_code).'"', $html);
    }

    public function test_a_checked_in_appointment_shows_the_contact_info_instead_of_the_button(): void
    {
        $user = $this->createPatient();
        $appointment = $this->book($user, $this->sessionOn('2026-10-13'), AppointmentStatus::CheckedIn);

        $this->actingAs($user)->get($this->showUrl($appointment))
            ->assertOk()
            ->assertSee('Batas pembatalan online telah lewat.')
            ->assertDontSee('data-bs-target="#modal-batal"', false)
            ->assertDontSee('name="cancel_reason"', false);
    }

    #[DataProvider('finalStatuses')]
    public function test_a_final_appointment_shows_neither_the_button_nor_the_contact_info(AppointmentStatus $status): void
    {
        $user = $this->createPatient();
        $appointment = $this->book($user, $this->sessionOn('2026-10-13'), $status);

        $this->actingAs($user)->get($this->showUrl($appointment))
            ->assertOk()
            ->assertDontSee('Batalkan Pendaftaran')
            ->assertDontSee('data-bs-target="#modal-batal"', false)
            ->assertDontSee('name="cancel_reason"', false)
            ->assertDontSee('Batas pembatalan online telah lewat');
    }

    public function test_the_cancel_ui_follows_the_cutoff_setting_and_hides_the_whatsapp_link_without_a_number(): void
    {
        app(SettingsService::class)->set('booking.cancel_cutoff_minutes', 30);
        app(SettingsService::class)->set('contact.whatsapp', null);
        $user = $this->createPatient();
        $appointment = $this->book($user, $this->sessionOn('2026-10-12'));
        $this->actingAs($user);

        $this->travelTo(CarbonImmutable::parse('2026-10-12 08:30:00'));
        $this->get($this->showUrl($appointment))->assertSee('data-bs-target="#modal-batal"', false);

        $this->travelTo(CarbonImmutable::parse('2026-10-12 08:30:01'));
        $this->get($this->showUrl($appointment))
            ->assertOk()
            ->assertSee('Batas pembatalan online telah lewat.')
            ->assertDontSee('wa.me', false)
            ->assertDontSee('data-bs-target="#modal-batal"', false);
    }

    public function test_the_detail_page_of_another_patient_stays_404_and_has_no_cancel_form(): void
    {
        $owner = $this->createPatient(['email' => 'pemilik@contoh.test']);
        $intruder = $this->createPatient(['email' => 'penyusup@contoh.test']);
        $appointment = $this->book($owner, $this->sessionOn('2026-10-13'));

        $this->actingAs($intruder)->get($this->showUrl($appointment))
            ->assertNotFound()
            ->assertDontSee($appointment->booking_code);
    }
}
