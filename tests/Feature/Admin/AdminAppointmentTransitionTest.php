<?php

namespace Tests\Feature\Admin;

use App\Enums\ActorType;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\AppointmentStatusHistory;
use App\Models\AuditLog;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\PatientProfile;
use App\Models\PracticeSession;
use App\Models\User;
use App\Notifications\AppointmentStatusChangedNotification;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InteractsWithMasterData;
use Tests\Support\InteractsWithSchedules;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

/**
 * POST `admin.appointments.transition` (F7A-R18, V-15): setiap transisi dengan flash VERBATIM, BKG-14 dan BKG-19 via HTTP,
 * `to` yang dilarang, alasan 5 sampai 255 karakter, KON-05 UI (`expected_status` basi), NTF-03 via HTTP, `kembali`, akses.
 * Sesi uji: Selasa 2026-10-06 09.00-12.00; check-in dibuka 08.00.
 */
class AdminAppointmentTransitionTest extends TestCase
{
    use InteractsWithMasterData;
    use InteractsWithSchedules;
    use InteractsWithStaff;
    use RefreshDatabase;

    private const STALE = 'Status pendaftaran sudah berubah. Muat ulang halaman untuk melihat status terbaru.';

    private const REASON_REQUIRED = 'Alasan wajib diisi untuk pembatalan atau koreksi check-in.';

    private const BAD_TARGET = 'Status tujuan tidak diperbolehkan. Gunakan aksi Tandai tidak hadir pada antrean sesi.';

    private Clinic $clinic;

    private Doctor $doctor;

    private User $officer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-06 07:00:00');
        $this->seed(SettingsSeeder::class);

        $this->clinic = $this->createClinic(['name' => 'Poli Anak', 'code' => 'ANK']);
        $this->doctor = $this->createDoctor(['name' => 'Sinta Maharani'], $this->clinic);
        $this->officer = $this->createStaff('registration-officer');
    }

    // --- pembantu ----------------------------------------------------------------------------

    private function sessionOn(string $date): PracticeSession
    {
        return $this->createSession([
            'doctor_id' => $this->doctor->id,
            'clinic_id' => $this->clinic->id,
            'session_date' => $date,
            'start_time' => '09:00:00',
            'end_time' => '12:00:00',
            'quota' => 20,
            'doctor_schedule_id' => null,
        ]);
    }

    /** @param  array<string, mixed>  $overrides */
    private function appointmentFor(AppointmentStatus $status, string $date = '2026-10-06', ?PatientProfile $profile = null, array $overrides = []): Appointment
    {
        $profile ??= PatientProfile::factory()->create();

        return $this->bookAppointment($this->sessionOn($date), $status, array_merge(['patient_profile_id' => $profile->id], $overrides));
    }

    /** @param  array<string, mixed>  $data */
    private function postTransition(Appointment $appointment, array $data, ?User $user = null): TestResponse
    {
        return $this->actingAs($user ?? $this->officer)
            ->post(route('admin.appointments.transition', ['appointment' => $appointment->booking_code]), $data);
    }

    private function detailUrl(Appointment $appointment): string
    {
        return route('admin.appointments.show', ['appointment' => $appointment->booking_code]);
    }

    private function statusOf(Appointment $appointment): string
    {
        return (string) DB::table('appointments')->where('id', $appointment->id)->value('status');
    }

    private function historyCount(Appointment $appointment): int
    {
        return AppointmentStatusHistory::query()->where('appointment_id', $appointment->id)->count();
    }

    // --- setiap transisi dengan flash VERBATIM -----------------------------------------------

    /** @return array<string, array{AppointmentStatus, string, ?string, string, string}> */
    public static function successCases(): array
    {
        return [
            'konfirmasi' => [AppointmentStatus::Pending, 'confirmed', null, '07:00:00', 'Pendaftaran {code} dikonfirmasi.'],
            'check-in dari confirmed' => [AppointmentStatus::Confirmed, 'checked_in', null, '08:00:00', '{queue} check-in berhasil.'],
            'check-in langsung dari pending' => [AppointmentStatus::Pending, 'checked_in', null, '08:30:00', '{queue} check-in berhasil.'],
            'selesai' => [AppointmentStatus::CheckedIn, 'completed', null, '10:00:00', 'Kunjungan {queue} selesai.'],
            'batal dari pending' => [AppointmentStatus::Pending, 'cancelled', 'Pasien menelepon membatalkan', '07:00:00', 'Pendaftaran {code} dibatalkan.'],
            'batal dari confirmed' => [AppointmentStatus::Confirmed, 'cancelled', 'Dokter berhalangan hadir', '09:30:00', 'Pendaftaran {code} dibatalkan.'],
            'koreksi check-in' => [AppointmentStatus::CheckedIn, 'confirmed', 'Salah memilih pasien', '10:00:00', 'Check-in {queue} dikoreksi menjadi Dikonfirmasi.'],
        ];
    }

    #[DataProvider('successCases')]
    public function test_every_transition_succeeds_with_the_verbatim_flash(AppointmentStatus $from, string $to, ?string $reason, string $time, string $flash): void
    {
        $appointment = $this->appointmentFor($from);
        $this->travelTo('2026-10-06 '.$time);
        Notification::fake();

        $this->postTransition($appointment, ['to' => $to, 'expected_status' => $from->value, 'reason' => $reason])
            ->assertRedirect($this->detailUrl($appointment))
            ->assertSessionHas('success', strtr($flash, ['{code}' => $appointment->booking_code, '{queue}' => 'ANK-001']));

        $this->assertSame($to, $this->statusOf($appointment));

        $history = AppointmentStatusHistory::query()->where('appointment_id', $appointment->id)->sole();
        $this->assertSame($from, $history->from_status);
        $this->assertSame($to, $history->to_status->value);
        $this->assertSame(ActorType::Staff, $history->actor_type);
        $this->assertSame($this->officer->id, (int) $history->actor_user_id);
        $this->assertSame($reason, $history->reason);

        $audit = AuditLog::query()->where('action', 'appointment.status_changed')->sole();
        $this->assertSame($this->officer->id, (int) $audit->user_id);
        $this->assertSame($from->value, $audit->old_values['status']);
        $this->assertSame($to, $audit->new_values['status']);
    }

    public function test_cancelling_releases_the_quota_and_a_correction_clears_the_check_in_time(): void
    {
        $cancelled = $this->appointmentFor(AppointmentStatus::Confirmed);
        $this->postTransition($cancelled, ['to' => 'cancelled', 'expected_status' => 'confirmed', 'reason' => 'Pasien menelepon'])->assertSessionHas('success');

        $this->assertSame(0, PracticeSession::query()->findOrFail($cancelled->practice_session_id)->booked_count);
        $this->assertSame('Pasien menelepon', Appointment::query()->findOrFail($cancelled->id)->cancel_reason);

        $this->travelTo('2026-10-06 10:00:00');
        $corrected = $this->appointmentFor(AppointmentStatus::CheckedIn);
        $this->postTransition($corrected, ['to' => 'confirmed', 'expected_status' => 'checked_in', 'reason' => 'Salah memilih pasien'])->assertSessionHas('success');

        $this->assertNull(Appointment::query()->findOrFail($corrected->id)->checked_in_at);
    }

    // --- BKG-14 via HTTP ---------------------------------------------------------------------

    public function test_bkg_14_check_in_one_minute_before_the_window_is_rejected_and_exactly_at_the_opening_is_accepted(): void
    {
        $appointment = $this->appointmentFor(AppointmentStatus::Confirmed);

        $this->travelTo('2026-10-06 07:59:00');
        $this->postTransition($appointment, ['to' => 'checked_in', 'expected_status' => 'confirmed'])
            ->assertRedirect($this->detailUrl($appointment))
            ->assertSessionHas('error', 'Check-in belum dibuka. Check-in dibuka pukul 08.00.');
        $this->assertSame('confirmed', $this->statusOf($appointment));
        $this->assertSame(0, $this->historyCount($appointment));

        $this->travelTo('2026-10-06 08:00:00');
        $this->postTransition($appointment, ['to' => 'checked_in', 'expected_status' => 'confirmed'])
            ->assertRedirect($this->detailUrl($appointment))
            ->assertSessionHas('success', 'ANK-001 check-in berhasil.');
        $this->assertSame('checked_in', $this->statusOf($appointment));
    }

    public function test_bkg_14_check_in_exactly_at_the_end_is_accepted_and_one_minute_later_is_rejected(): void
    {
        $atEnd = $this->appointmentFor(AppointmentStatus::Confirmed);
        $late = $this->appointmentFor(AppointmentStatus::Confirmed);

        $this->travelTo('2026-10-06 12:00:00');
        $this->postTransition($atEnd, ['to' => 'checked_in', 'expected_status' => 'confirmed'])->assertSessionHas('success');
        $this->assertSame('checked_in', $this->statusOf($atEnd));

        $this->travelTo('2026-10-06 12:01:00');
        $this->postTransition($late, ['to' => 'checked_in', 'expected_status' => 'confirmed'])
            ->assertSessionHas('error', 'Sesi sudah berakhir sehingga check-in tidak dapat dilakukan.');
        $this->assertSame('confirmed', $this->statusOf($late));
    }

    public function test_bkg_14_check_in_on_another_day_is_rejected_with_the_visit_date(): void
    {
        $tomorrow = $this->appointmentFor(AppointmentStatus::Confirmed, '2026-10-07');
        $this->travelTo('2026-10-06 08:30:00');

        $this->postTransition($tomorrow, ['to' => 'checked_in', 'expected_status' => 'confirmed'])
            ->assertSessionHas('error', 'Check-in hanya dapat dilakukan pada tanggal kunjungan (Rabu, 7 Oktober 2026).');
        $this->assertSame('confirmed', $this->statusOf($tomorrow));
    }

    public function test_confirming_from_the_exact_end_of_the_session_is_rejected(): void
    {
        $appointment = $this->appointmentFor(AppointmentStatus::Pending);
        $this->travelTo('2026-10-06 12:00:00');

        $this->postTransition($appointment, ['to' => 'confirmed', 'expected_status' => 'pending'])
            ->assertSessionHas('error', 'Sesi sudah berakhir sehingga pendaftaran tidak dapat dikonfirmasi.');
        $this->assertSame('pending', $this->statusOf($appointment));
    }

    // --- BKG-19 via HTTP ---------------------------------------------------------------------

    public function test_bkg_19_the_correction_works_on_the_visit_day_and_not_on_another_day(): void
    {
        $sameDay = $this->appointmentFor(AppointmentStatus::CheckedIn);
        $yesterday = $this->appointmentFor(AppointmentStatus::CheckedIn, '2026-10-05');
        $this->travelTo('2026-10-06 10:00:00');

        $this->postTransition($sameDay, ['to' => 'confirmed', 'expected_status' => 'checked_in', 'reason' => 'Salah memilih pasien'])
            ->assertSessionHas('success', 'Check-in ANK-001 dikoreksi menjadi Dikonfirmasi.');
        $this->assertSame('confirmed', $this->statusOf($sameDay));

        $this->postTransition($yesterday, ['to' => 'confirmed', 'expected_status' => 'checked_in', 'reason' => 'Salah memilih pasien'])
            ->assertSessionHas('error', 'Koreksi check-in hanya dapat dilakukan pada hari yang sama.');
        $this->assertSame('checked_in', $this->statusOf($yesterday));
    }

    public function test_bkg_19_the_correction_requires_a_reason(): void
    {
        $appointment = $this->appointmentFor(AppointmentStatus::CheckedIn);
        $this->travelTo('2026-10-06 10:00:00');

        $this->postTransition($appointment, ['to' => 'confirmed', 'expected_status' => 'checked_in'])
            ->assertRedirect($this->detailUrl($appointment))
            ->assertSessionHasErrors(['reason' => self::REASON_REQUIRED])
            ->assertSessionHas('error', self::REASON_REQUIRED);
        $this->assertSame('checked_in', $this->statusOf($appointment));
    }

    public function test_a_cancelled_appointment_cannot_be_checked_in_nor_reopened(): void
    {
        $appointment = $this->appointmentFor(AppointmentStatus::Completed);
        $this->travelTo('2026-10-06 10:00:00');

        $this->postTransition($appointment, ['to' => 'checked_in', 'expected_status' => 'completed'])
            ->assertSessionHas('error', 'Pendaftaran ini sudah berstatus selesai dan tidak dapat diubah lagi.');
        $this->assertSame('completed', $this->statusOf($appointment));
    }

    // --- tujuan yang dilarang (E3) -----------------------------------------------------------

    /** @return array<string, array{mixed, string}> */
    public static function badTargets(): array
    {
        return [
            'no_show per individu' => ['no_show', self::BAD_TARGET],
            'pending' => ['pending', self::BAD_TARGET],
            'status tidak dikenal' => ['xyz', self::BAD_TARGET],
            'kosong' => ['', 'Status tujuan wajib dipilih.'],
            'larik' => [['cancelled'], 'Status tujuan tidak valid.'],
        ];
    }

    #[DataProvider('badTargets')]
    public function test_a_forbidden_target_is_rejected_with_the_verbatim_message(mixed $to, string $message): void
    {
        $appointment = $this->appointmentFor(AppointmentStatus::Confirmed);
        $this->travelTo('2026-10-06 08:30:00');

        $this->postTransition($appointment, ['to' => $to, 'expected_status' => 'confirmed', 'reason' => 'Alasan uji petugas'])
            ->assertRedirect($this->detailUrl($appointment))
            ->assertSessionHasErrors(['to' => $message])
            ->assertSessionHas('error', $message);

        $this->assertSame('confirmed', $this->statusOf($appointment));
        $this->assertSame(0, $this->historyCount($appointment));
    }

    // --- alasan V-15 -------------------------------------------------------------------------

    /** @return array<string, array{mixed, ?string}> */
    public static function reasons(): array
    {
        return [
            'tidak dikirim' => [null, self::REASON_REQUIRED],
            'hanya spasi' => ['      ', self::REASON_REQUIRED],
            'empat karakter' => ['abcd', 'Alasan minimal 5 karakter.'],
            'empat karakter berspasi' => ['  abcd  ', 'Alasan minimal 5 karakter.'],
            'lima karakter' => ['abcde', null],
            'dua ratus lima puluh lima karakter' => [str_repeat('a', 255), null],
            'dua ratus lima puluh enam karakter' => [str_repeat('a', 256), 'Alasan maksimal 255 karakter.'],
            'larik' => [['abcde'], 'Alasan harus berupa teks.'],
        ];
    }

    #[DataProvider('reasons')]
    public function test_the_cancellation_reason_follows_v15(mixed $reason, ?string $error): void
    {
        $appointment = $this->appointmentFor(AppointmentStatus::Confirmed);

        $response = $this->postTransition($appointment, ['to' => 'cancelled', 'expected_status' => 'confirmed', 'reason' => $reason]);

        if ($error === null) {
            $response->assertSessionHas('success', 'Pendaftaran '.$appointment->booking_code.' dibatalkan.');
            $this->assertSame('cancelled', $this->statusOf($appointment));

            return;
        }

        $response->assertRedirect($this->detailUrl($appointment))
            ->assertSessionHasErrors(['reason' => $error])
            ->assertSessionHas('error', $error);
        $this->assertSame('confirmed', $this->statusOf($appointment));
        $this->assertSame(0, $this->historyCount($appointment));
    }

    public function test_a_reason_is_not_required_for_the_other_transitions(): void
    {
        $appointment = $this->appointmentFor(AppointmentStatus::Pending);

        $this->postTransition($appointment, ['to' => 'confirmed', 'expected_status' => 'pending'])
            ->assertSessionHas('success', 'Pendaftaran '.$appointment->booking_code.' dikonfirmasi.');
    }

    // --- KON-05 UI: halaman basi dan klik ganda ----------------------------------------------

    public function test_kon_05_a_stale_expected_status_is_rejected_without_writing(): void
    {
        $appointment = $this->appointmentFor(AppointmentStatus::Confirmed);
        $other = $this->createStaff('registration-officer');
        $this->travelTo('2026-10-06 08:30:00');

        // Petugas pertama memuat halaman (status confirmed); petugas lain lebih dulu melakukan check-in.
        $this->postTransition($appointment, ['to' => 'checked_in', 'expected_status' => 'confirmed'], $other)->assertSessionHas('success');
        $this->assertSame(1, $this->historyCount($appointment));
        $booked = PracticeSession::query()->findOrFail($appointment->practice_session_id)->booked_count;

        $this->postTransition($appointment, ['to' => 'cancelled', 'expected_status' => 'confirmed', 'reason' => 'Pasien menelepon'])
            ->assertRedirect($this->detailUrl($appointment))
            ->assertSessionHas('error', self::STALE);

        $this->assertSame('checked_in', $this->statusOf($appointment));
        $this->assertSame(1, $this->historyCount($appointment));
        $this->assertSame(1, AuditLog::query()->where('action', 'appointment.status_changed')->count());
        $this->assertSame($booked, PracticeSession::query()->findOrFail($appointment->practice_session_id)->booked_count);
    }

    public function test_a_double_submit_of_the_same_form_changes_the_status_only_once(): void
    {
        $appointment = $this->appointmentFor(AppointmentStatus::Confirmed);
        $this->travelTo('2026-10-06 08:30:00');
        $data = ['to' => 'checked_in', 'expected_status' => 'confirmed'];

        $this->postTransition($appointment, $data)->assertSessionHas('success');
        $this->flushSession();
        $this->postTransition($appointment, $data)->assertSessionHas('error', self::STALE);

        $this->assertSame(1, $this->historyCount($appointment));
    }

    public function test_expected_status_is_required_and_must_be_a_real_status(): void
    {
        $appointment = $this->appointmentFor(AppointmentStatus::Confirmed);
        $this->travelTo('2026-10-06 08:30:00');

        foreach ([null, '', 'xyz', ['confirmed']] as $value) {
            $this->flushSession();
            $this->postTransition($appointment, ['to' => 'checked_in', 'expected_status' => $value])
                ->assertSessionHasErrors(['expected_status' => self::STALE])
                ->assertSessionHas('error', self::STALE);
        }

        $this->assertSame('confirmed', $this->statusOf($appointment));
    }

    // --- NTF-03 via HTTP ---------------------------------------------------------------------

    public function test_ntf_03_a_walk_in_patient_without_an_account_gets_no_notification_but_everything_is_written(): void
    {
        $appointment = $this->appointmentFor(AppointmentStatus::Pending, '2026-10-06', PatientProfile::factory()->create(['user_id' => null]));

        $this->postTransition($appointment, ['to' => 'confirmed', 'expected_status' => 'pending'])->assertSessionHas('success');

        $this->assertSame(0, DB::table('notifications')->count());
        $this->assertSame(1, $this->historyCount($appointment));
        $this->assertSame(1, AuditLog::query()->where('action', 'appointment.status_changed')->count());

        Notification::fake();
        $second = $this->appointmentFor(AppointmentStatus::Pending, '2026-10-06', PatientProfile::factory()->create(['user_id' => null]));
        $this->postTransition($second, ['to' => 'confirmed', 'expected_status' => 'pending'])->assertSessionHas('success');
        Notification::assertNothingSent();
    }

    public function test_ntf_03_a_patient_with_an_account_is_notified_of_a_staff_transition(): void
    {
        $profile = PatientProfile::factory()->withAccount()->create();
        $recipient = User::query()->findOrFail($profile->user_id);
        $appointment = $this->appointmentFor(AppointmentStatus::Pending, '2026-10-06', $profile);
        Notification::fake();

        $this->postTransition($appointment, ['to' => 'confirmed', 'expected_status' => 'pending'])->assertSessionHas('success');

        Notification::assertSentToTimes($recipient, AppointmentStatusChangedNotification::class, 1);
        Notification::assertSentTo(
            $recipient,
            AppointmentStatusChangedNotification::class,
            fn (AppointmentStatusChangedNotification $notification): bool => $notification->appointment->is($appointment)
                && $notification->to === AppointmentStatus::Confirmed,
        );
    }

    public function test_a_rejected_transition_sends_nothing_and_writes_nothing(): void
    {
        $profile = PatientProfile::factory()->withAccount()->create();
        $appointment = $this->appointmentFor(AppointmentStatus::Confirmed, '2026-10-06', $profile);
        $this->travelTo('2026-10-06 07:00:00');
        Notification::fake();

        $this->postTransition($appointment, ['to' => 'checked_in', 'expected_status' => 'confirmed'])->assertSessionHas('error');

        Notification::assertNothingSent();
        $this->assertSame(0, $this->historyCount($appointment));
        $this->assertSame(0, AuditLog::query()->where('action', 'appointment.status_changed')->count());
    }

    // --- kembali (F7A-R19) -------------------------------------------------------------------

    public function test_a_valid_kembali_is_used_on_success_and_on_error(): void
    {
        $appointment = $this->appointmentFor(AppointmentStatus::Confirmed);
        $queue = '/admin/antrean?tanggal=2026-10-06&sesi='.$appointment->practice_session_id;

        $this->travelTo('2026-10-06 08:30:00');
        $this->postTransition($appointment, ['to' => 'checked_in', 'expected_status' => 'confirmed', 'kembali' => $queue])
            ->assertRedirect($queue)
            ->assertSessionHas('success');

        $this->flushSession();
        $this->postTransition($appointment, ['to' => 'checked_in', 'expected_status' => 'confirmed', 'kembali' => $queue])
            ->assertRedirect($queue)
            ->assertSessionHas('error', self::STALE);

        $this->flushSession();
        $this->postTransition($appointment, ['to' => 'no_show', 'expected_status' => 'checked_in', 'kembali' => $queue])
            ->assertRedirect($queue)
            ->assertSessionHasErrors('to');
    }

    /** @return array<string, array{mixed}> */
    public static function evilKembali(): array
    {
        return [
            'domain lain' => ['https://evil.test/admin/antrean'],
            'protokol relatif' => ['//evil.test'],
            'portal pasien' => ['/pasien'],
            'admin pasien' => ['/admin/pasien'],
            'segmen titik-titik' => ['/admin/antrean/../pasien'],
            'larik' => [['/admin/antrean']],
        ];
    }

    #[DataProvider('evilKembali')]
    public function test_an_unsafe_kembali_never_leaves_the_allowed_area(mixed $kembali): void
    {
        $appointment = $this->appointmentFor(AppointmentStatus::Confirmed);
        $this->travelTo('2026-10-06 08:30:00');

        $this->postTransition($appointment, ['to' => 'checked_in', 'expected_status' => 'confirmed', 'kembali' => $kembali])
            ->assertRedirect($this->detailUrl($appointment))
            ->assertSessionHas('success');

        $this->flushSession();
        $this->postTransition($appointment, ['to' => 'checked_in', 'expected_status' => 'confirmed', 'kembali' => $kembali])
            ->assertRedirect($this->detailUrl($appointment))
            ->assertSessionHas('error', self::STALE);
    }

    // --- akses dan URL -----------------------------------------------------------------------

    /** @return array<string, array{string, int}> */
    public static function roles(): array
    {
        return [
            'super admin' => ['super-admin', 302],
            'petugas pendaftaran' => ['registration-officer', 302],
            'admin konten' => ['content-admin', 403],
        ];
    }

    #[DataProvider('roles')]
    public function test_access_per_staff_role(string $role, int $status): void
    {
        $appointment = $this->appointmentFor(AppointmentStatus::Pending);

        $this->postTransition($appointment, ['to' => 'confirmed', 'expected_status' => 'pending'], $this->createStaff($role))->assertStatus($status);

        $this->assertSame($status === 302 ? 'confirmed' : 'pending', $this->statusOf($appointment));
    }

    public function test_a_staff_member_with_only_the_view_permission_gets_403(): void
    {
        $this->seedRoles();
        $viewer = User::factory()->staff()->create();
        $viewer->givePermissionTo('appointments.view');
        $appointment = $this->appointmentFor(AppointmentStatus::Pending);

        $this->postTransition($appointment, ['to' => 'confirmed', 'expected_status' => 'pending'], $viewer)->assertForbidden();
        $this->assertSame('pending', $this->statusOf($appointment));
    }

    public function test_a_patient_gets_403_and_a_guest_is_sent_to_the_admin_login(): void
    {
        $appointment = $this->appointmentFor(AppointmentStatus::Pending);

        $this->postTransition($appointment, ['to' => 'confirmed', 'expected_status' => 'pending'], User::factory()->patient()->create())->assertForbidden();

        auth()->logout();
        $this->post(route('admin.appointments.transition', ['appointment' => $appointment->booking_code]), ['to' => 'confirmed', 'expected_status' => 'pending'])
            ->assertRedirect(route('admin.login'));
        $this->assertSame('pending', $this->statusOf($appointment));
    }

    public function test_malformed_unknown_and_numeric_codes_are_404(): void
    {
        $appointment = $this->appointmentFor(AppointmentStatus::Pending);
        $this->actingAs($this->officer);
        $data = ['to' => 'confirmed', 'expected_status' => 'pending'];

        $this->post('/admin/pendaftaran/abc/status', $data)->assertNotFound();
        $this->post('/admin/pendaftaran/'.$appointment->id.'/status', $data)->assertNotFound();
        $this->post('/admin/pendaftaran/'.strtolower($appointment->booking_code).'/status', $data)->assertNotFound();
        $this->post('/admin/pendaftaran/UPH-ZZZZ-ZZZZ/status', $data)->assertNotFound();
        $this->assertSame('pending', $this->statusOf($appointment));
    }

    public function test_the_transition_is_post_only(): void
    {
        $appointment = $this->appointmentFor(AppointmentStatus::Pending);

        $this->actingAs($this->officer)
            ->get(route('admin.appointments.transition', ['appointment' => $appointment->booking_code]))
            ->assertStatus(405);
    }
}
