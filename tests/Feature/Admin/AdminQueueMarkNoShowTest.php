<?php

namespace Tests\Feature\Admin;

use App\Enums\ActorType;
use App\Enums\AppointmentStatus;
use App\Enums\SessionStatus;
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
 * POST `admin.queue.mark-no-show` (F7A-R23, V-16) dan banner/modal pada antrean: BKG-16 via HTTP (riwayat dan audit
 * per appointment, aktor petugas), E5 (batas `end_time` strik, sesi `needs_review`, tanpa konfirmasi), E2 (tekan dua
 * kali), NTF-03, E4 (escape di modal), akses. Waktu dibekukan pada Selasa 2026-10-06 07.00; sesi uji 09.00-12.00.
 */
class AdminQueueMarkNoShowTest extends TestCase
{
    use InteractsWithMasterData;
    use InteractsWithSchedules;
    use InteractsWithStaff;
    use RefreshDatabase;

    private const NOT_ENDED = 'Tandai tidak hadir hanya dapat dilakukan setelah sesi berakhir (pukul 12.00).';

    private const UNDER_REVIEW = 'Sesi ini sedang menunggu penyelesaian di layar Sesi terdampak.';

    private const NOTHING = 'Tidak ada pendaftaran yang perlu ditandai tidak hadir pada sesi ini.';

    private const CONFIRM_REQUIRED = 'Centang konfirmasi untuk menandai seluruh pendaftaran yang belum hadir sebagai tidak hadir.';

    private Clinic $clinic;

    private Doctor $doctor;

    private User $officer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-06 07:00:00');
        $this->seed(SettingsSeeder::class);

        $this->clinic = $this->createClinic(['name' => 'Poli Anak', 'code' => 'ANK']);
        $this->doctor = $this->createDoctor(['name' => 'Sinta Maharani', 'title_prefix' => 'dr.', 'title_suffix' => 'Sp.A'], $this->clinic);
        $this->officer = $this->createStaff('registration-officer');
    }

    // --- pembantu ----------------------------------------------------------------------------

    /** @param  array<string, mixed>  $overrides */
    private function sessionOn(string $date, array $overrides = []): PracticeSession
    {
        return $this->createSession(array_merge([
            'doctor_id' => $this->doctor->id,
            'clinic_id' => $this->clinic->id,
            'session_date' => $date,
            'start_time' => '09:00:00',
            'end_time' => '12:00:00',
            'quota' => 20,
            'doctor_schedule_id' => null,
        ], $overrides));
    }

    /** Sesi kemarin 09.00-12.00 (sudah berakhir pada 07.00 hari ini). */
    private function endedSession(array $overrides = []): PracticeSession
    {
        return $this->sessionOn('2026-10-05', $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @param  array<string, mixed>  $profile
     */
    private function appointmentOn(PracticeSession $session, string $name, AppointmentStatus $status = AppointmentStatus::Confirmed, array $overrides = [], array $profile = []): Appointment
    {
        $owner = PatientProfile::factory()->create(['full_name' => $name] + $profile);

        return $this->bookAppointment($session, $status, array_merge(['patient_profile_id' => $owner->id], $overrides));
    }

    /** @param  array<string, mixed>  $data */
    private function markNoShow(PracticeSession $session, array $data = ['confirm' => '1'], ?User $user = null): TestResponse
    {
        return $this->actingAs($user ?? $this->officer)
            ->post(route('admin.queue.mark-no-show', ['session' => $session->id]), $data);
    }

    private function queueUrl(PracticeSession $session): string
    {
        return route('admin.queue.index', ['tanggal' => $session->session_date->toDateString(), 'sesi' => $session->id]);
    }

    private function statusOf(Appointment $appointment): string
    {
        return (string) DB::table('appointments')->where('id', $appointment->id)->value('status');
    }

    private function historyCount(Appointment $appointment): int
    {
        return AppointmentStatusHistory::query()->where('appointment_id', $appointment->id)->count();
    }

    private function queue(PracticeSession $session, ?User $user = null): TestResponse
    {
        return $this->actingAs($user ?? $this->officer)->get($this->queueUrl($session));
    }

    // --- BKG-16 via HTTP ---------------------------------------------------------------------

    public function test_bkg_16_marks_only_pending_and_confirmed_with_staff_history_and_audit(): void
    {
        $session = $this->endedSession();
        $pending = $this->appointmentOn($session, 'Pasien Menunggu', AppointmentStatus::Pending);
        $confirmed = $this->appointmentOn($session, 'Pasien Terkonfirmasi', AppointmentStatus::Confirmed);
        $checkedIn = $this->appointmentOn($session, 'Pasien Hadir', AppointmentStatus::CheckedIn);
        $completed = $this->appointmentOn($session, 'Pasien Selesai', AppointmentStatus::Completed);
        $cancelled = $this->appointmentOn($session, 'Pasien Batal', AppointmentStatus::Cancelled);
        $booked = PracticeSession::query()->findOrFail($session->id)->booked_count;
        Notification::fake();

        $this->markNoShow($session, ['confirm' => '1', 'reason' => '  Pasien tidak datang  '])
            ->assertRedirect($this->queueUrl($session))
            ->assertSessionHas('success', '2 pendaftaran ditandai tidak hadir.');

        foreach ([$pending, $confirmed] as $appointment) {
            $this->assertSame('no_show', $this->statusOf($appointment));
            $this->assertNotNull(Appointment::query()->findOrFail($appointment->id)->no_show_at);

            $history = AppointmentStatusHistory::query()->where('appointment_id', $appointment->id)->sole();
            $this->assertSame($appointment->status, $history->from_status);
            $this->assertSame('no_show', $history->to_status->value);
            $this->assertSame(ActorType::Staff, $history->actor_type);
            $this->assertSame($this->officer->id, (int) $history->actor_user_id);
            $this->assertSame('Pasien tidak datang', $history->reason);

            $audit = AuditLog::query()->where('action', 'appointment.status_changed')->where('auditable_id', $appointment->id)->sole();
            $this->assertSame($this->officer->id, (int) $audit->user_id);
            $this->assertSame($appointment->status->value, $audit->old_values['status']);
            $this->assertSame('no_show', $audit->new_values['status']);
        }

        foreach ([['checked_in', $checkedIn], ['completed', $completed], ['cancelled', $cancelled]] as [$status, $appointment]) {
            $this->assertSame($status, $this->statusOf($appointment));
            $this->assertSame(0, $this->historyCount($appointment));
        }

        $this->assertSame(2, AuditLog::query()->where('action', 'appointment.status_changed')->count());
        // `no_show` tetap memakai kuota.
        $this->assertSame($booked, PracticeSession::query()->findOrFail($session->id)->booked_count);
    }

    public function test_the_flash_uses_the_singular_count_too_and_a_blank_reason_is_stored_as_null(): void
    {
        $session = $this->endedSession();
        $appointment = $this->appointmentOn($session, 'Satu-satunya', AppointmentStatus::Pending);
        Notification::fake();

        $this->markNoShow($session, ['confirm' => '1', 'reason' => '   '])
            ->assertSessionHas('success', '1 pendaftaran ditandai tidak hadir.');

        $this->assertNull(AppointmentStatusHistory::query()->where('appointment_id', $appointment->id)->sole()->reason);
    }

    public function test_a_closed_session_that_has_ended_is_accepted(): void
    {
        $session = $this->endedSession(['status' => SessionStatus::Closed]);
        $appointment = $this->appointmentOn($session, 'Pasien Sesi Ditutup', AppointmentStatus::Confirmed);
        Notification::fake();

        $this->markNoShow($session)->assertSessionHas('success', '1 pendaftaran ditandai tidak hadir.');
        $this->assertSame('no_show', $this->statusOf($appointment));
    }

    public function test_ntf_03_only_patients_with_an_account_are_notified(): void
    {
        $session = $this->endedSession();
        $walkIn = PatientProfile::factory()->create(['user_id' => null]);
        $account = PatientProfile::factory()->withAccount()->create();
        $this->bookAppointment($session, AppointmentStatus::Pending, ['patient_profile_id' => $walkIn->id]);
        $this->bookAppointment($session, AppointmentStatus::Confirmed, ['patient_profile_id' => $account->id]);
        Notification::fake();

        $this->markNoShow($session)->assertSessionHas('success', '2 pendaftaran ditandai tidak hadir.');

        Notification::assertCount(1);
        Notification::assertSentToTimes(User::query()->findOrFail($account->user_id), AppointmentStatusChangedNotification::class, 1);
        $this->assertSame(2, AppointmentStatusHistory::query()->count());
    }

    // --- E5: batas waktu dan status sesi -----------------------------------------------------

    public function test_e5_exactly_at_the_end_time_is_rejected_and_one_second_later_is_accepted(): void
    {
        $session = $this->sessionOn('2026-10-06');
        $appointment = $this->appointmentOn($session, 'Pasien Uji', AppointmentStatus::Confirmed);
        Notification::fake();

        $this->travelTo('2026-10-06 11:59:59');
        $this->markNoShow($session)
            ->assertRedirect($this->queueUrl($session))
            ->assertSessionHas('error', self::NOT_ENDED);

        $this->travelTo('2026-10-06 12:00:00');
        $this->flushSession();
        $this->markNoShow($session)
            ->assertRedirect($this->queueUrl($session))
            ->assertSessionHas('error', self::NOT_ENDED);

        $this->assertSame('confirmed', $this->statusOf($appointment));
        $this->assertSame(0, $this->historyCount($appointment));
        $this->assertSame(0, AuditLog::query()->where('action', 'appointment.status_changed')->count());

        $this->travelTo('2026-10-06 12:00:01');
        $this->flushSession();
        $this->markNoShow($session)
            ->assertRedirect($this->queueUrl($session))
            ->assertSessionHas('success', '1 pendaftaran ditandai tidak hadir.');

        $this->assertSame('no_show', $this->statusOf($appointment));
    }

    public function test_a_session_that_has_not_started_yet_is_rejected_with_the_end_time(): void
    {
        $session = $this->sessionOn('2026-10-07');
        $appointment = $this->appointmentOn($session, 'Pasien Besok', AppointmentStatus::Pending);

        $this->markNoShow($session)
            ->assertRedirect($this->queueUrl($session))
            ->assertSessionHas('error', self::NOT_ENDED);

        $this->assertSame('pending', $this->statusOf($appointment));
    }

    public function test_e5_a_needs_review_session_is_rejected_with_the_review_message_and_nothing_is_written(): void
    {
        $session = $this->endedSession(['status' => SessionStatus::NeedsReview]);
        $pending = $this->appointmentOn($session, 'Pasien Menunggu', AppointmentStatus::Pending);
        $confirmed = $this->appointmentOn($session, 'Pasien Terkonfirmasi', AppointmentStatus::Confirmed);

        $this->markNoShow($session)
            ->assertRedirect($this->queueUrl($session))
            ->assertSessionHas('error', self::UNDER_REVIEW);

        $this->assertSame('pending', $this->statusOf($pending));
        $this->assertSame('confirmed', $this->statusOf($confirmed));
        $this->assertSame(0, AppointmentStatusHistory::query()->count());
    }

    public function test_a_needs_review_session_that_has_not_ended_gets_the_review_message_not_the_end_message(): void
    {
        $session = $this->sessionOn('2026-10-06', ['status' => SessionStatus::NeedsReview]);
        $this->appointmentOn($session, 'Pasien Uji', AppointmentStatus::Confirmed);

        $this->markNoShow($session)->assertSessionHas('error', self::UNDER_REVIEW);
    }

    // --- konfirmasi dan alasan (V-16) --------------------------------------------------------

    /** @return array<string, array{array<string, mixed>}> */
    public static function missingConfirmation(): array
    {
        return [
            'tidak dikirim' => [[]],
            'kosong' => [['confirm' => '']],
            'nol' => [['confirm' => '0']],
            'tidak' => [['confirm' => 'no']],
            'larik' => [['confirm' => ['1']]],
        ];
    }

    /** @param  array<string, mixed>  $data */
    #[DataProvider('missingConfirmation')]
    public function test_without_the_confirmation_nothing_is_written_and_the_queue_shows_the_error(array $data): void
    {
        $session = $this->endedSession();
        $appointment = $this->appointmentOn($session, 'Pasien Uji', AppointmentStatus::Confirmed);

        $this->markNoShow($session, $data + ['reason' => 'Alasan uji'])
            ->assertRedirect($this->queueUrl($session))
            ->assertSessionHasErrors(['confirm' => self::CONFIRM_REQUIRED])
            ->assertSessionHas('error', self::CONFIRM_REQUIRED);

        $this->assertSame('confirmed', $this->statusOf($appointment));
        $this->assertSame(0, AppointmentStatusHistory::query()->count());
        $this->assertSame(0, AuditLog::query()->where('action', 'appointment.status_changed')->count());
    }

    public function test_the_confirmation_is_checked_before_the_session_rules(): void
    {
        $session = $this->sessionOn('2026-10-06', ['status' => SessionStatus::NeedsReview]);

        $this->markNoShow($session, [])
            ->assertSessionHasErrors(['confirm' => self::CONFIRM_REQUIRED])
            ->assertSessionHas('error', self::CONFIRM_REQUIRED);
    }

    /** @return array<string, array{mixed, ?string}> */
    public static function reasons(): array
    {
        return [
            'tidak dikirim' => [null, null],
            'dua ratus lima puluh lima karakter' => [str_repeat('a', 255), null],
            'dua ratus lima puluh enam karakter' => [str_repeat('a', 256), 'Alasan maksimal 255 karakter.'],
            'larik' => [['abc'], 'Alasan harus berupa teks.'],
        ];
    }

    #[DataProvider('reasons')]
    public function test_the_optional_reason_is_limited_to_255_characters(mixed $reason, ?string $error): void
    {
        $session = $this->endedSession();
        $appointment = $this->appointmentOn($session, 'Pasien Uji', AppointmentStatus::Confirmed);
        Notification::fake();

        $response = $this->markNoShow($session, array_filter(['confirm' => '1', 'reason' => $reason], fn (mixed $value): bool => $value !== null));

        if ($error === null) {
            $response->assertSessionHas('success', '1 pendaftaran ditandai tidak hadir.');
            $this->assertSame($reason, AppointmentStatusHistory::query()->where('appointment_id', $appointment->id)->sole()->reason);

            return;
        }

        $response->assertRedirect($this->queueUrl($session))
            ->assertSessionHasErrors(['reason' => $error])
            ->assertSessionHas('error', $error);
        $this->assertSame('confirmed', $this->statusOf($appointment));
    }

    // --- E2: tekan dua kali ------------------------------------------------------------------

    public function test_e2_pressing_twice_marks_each_appointment_exactly_once(): void
    {
        $session = $this->endedSession();
        $appointments = [
            $this->appointmentOn($session, 'Pasien Satu', AppointmentStatus::Pending),
            $this->appointmentOn($session, 'Pasien Dua', AppointmentStatus::Confirmed),
            $this->appointmentOn($session, 'Pasien Tiga', AppointmentStatus::Confirmed),
        ];
        Notification::fake();

        $this->markNoShow($session)->assertSessionHas('success', '3 pendaftaran ditandai tidak hadir.');

        $this->flushSession();
        $this->markNoShow($session)
            ->assertRedirect($this->queueUrl($session))
            ->assertSessionHas('warning', self::NOTHING)
            ->assertSessionMissing('success');

        $ids = array_map(fn (Appointment $appointment): int => $appointment->id, $appointments);

        $this->assertSame(3, AppointmentStatusHistory::query()->whereIn('appointment_id', $ids)->count());
        $this->assertSame(3, AuditLog::query()->where('action', 'appointment.status_changed')->count());
        $this->assertSame(3, Appointment::query()->whereIn('id', $ids)->where('status', 'no_show')->count());
    }

    public function test_a_session_without_anything_to_mark_says_so_with_a_warning(): void
    {
        $session = $this->endedSession();
        $this->appointmentOn($session, 'Pasien Hadir', AppointmentStatus::CheckedIn);
        $this->appointmentOn($session, 'Pasien Selesai', AppointmentStatus::Completed);

        $this->markNoShow($session)
            ->assertRedirect($this->queueUrl($session))
            ->assertSessionHas('warning', self::NOTHING)
            ->assertSessionMissing('success');

        $this->assertSame(0, AppointmentStatusHistory::query()->count());
    }

    public function test_the_flash_is_shown_on_the_queue_and_the_banner_is_gone_afterwards(): void
    {
        $session = $this->endedSession();
        $this->appointmentOn($session, 'Pasien Satu', AppointmentStatus::Pending);
        $this->appointmentOn($session, 'Pasien Dua', AppointmentStatus::Confirmed);
        Notification::fake();

        $this->actingAs($this->officer)
            ->followingRedirects()
            ->post(route('admin.queue.mark-no-show', ['session' => $session->id]), ['confirm' => '1'])
            ->assertOk()
            ->assertSee('2 pendaftaran ditandai tidak hadir.')
            ->assertSee('Tidak hadir')
            ->assertDontSee('data-no-show-banner', false)
            ->assertDontSee('Tandai tidak hadir (', false);
    }

    // --- akses -------------------------------------------------------------------------------

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
        $session = $this->endedSession();
        $appointment = $this->appointmentOn($session, 'Pasien Uji', AppointmentStatus::Pending);
        Notification::fake();

        $this->markNoShow($session, ['confirm' => '1'], $this->createStaff($role))->assertStatus($status);

        $this->assertSame($status === 302 ? 'no_show' : 'pending', $this->statusOf($appointment));
    }

    public function test_a_staff_member_with_only_the_view_permission_gets_403(): void
    {
        $this->seedRoles();
        $viewer = User::factory()->staff()->create();
        $viewer->givePermissionTo('appointments.view');
        $session = $this->endedSession();
        $appointment = $this->appointmentOn($session, 'Pasien Uji', AppointmentStatus::Pending);

        $this->markNoShow($session, ['confirm' => '1'], $viewer)->assertForbidden();

        $this->assertSame('pending', $this->statusOf($appointment));
    }

    public function test_a_patient_gets_403_and_a_guest_is_sent_to_the_admin_login(): void
    {
        $session = $this->endedSession();
        $appointment = $this->appointmentOn($session, 'Pasien Uji', AppointmentStatus::Pending);

        $this->markNoShow($session, ['confirm' => '1'], User::factory()->patient()->create())->assertForbidden();

        auth()->logout();
        $this->post(route('admin.queue.mark-no-show', ['session' => $session->id]), ['confirm' => '1'])->assertRedirect(route('admin.login'));
        $this->assertSame('pending', $this->statusOf($appointment));
    }

    public function test_a_non_numeric_or_unknown_session_is_404_and_get_is_405(): void
    {
        $session = $this->endedSession();
        $this->actingAs($this->officer);

        $this->post('/admin/antrean/abc/tidak-hadir', ['confirm' => '1'])->assertNotFound();
        $this->post('/admin/antrean/999999/tidak-hadir', ['confirm' => '1'])->assertNotFound();
        $this->get('/admin/antrean/'.$session->id.'/tidak-hadir')->assertStatus(405);
    }

    // --- banner dan modal pada antrean -------------------------------------------------------

    public function test_the_banner_and_the_modal_appear_after_the_session_has_ended(): void
    {
        $session = $this->sessionOn('2026-10-06');
        $this->appointmentOn($session, 'Pasien Menunggu', AppointmentStatus::Pending);
        $this->appointmentOn($session, 'Pasien Terkonfirmasi', AppointmentStatus::Confirmed);
        $this->appointmentOn($session, 'Pasien Sudah Hadir', AppointmentStatus::CheckedIn);
        $this->travelTo('2026-10-06 12:01:00');

        $html = $this->queue($session)
            ->assertOk()
            ->assertSee('role="status" data-no-show-banner', false)
            ->assertSee('Sesi telah berakhir pukul 12.00. 2 pendaftaran belum check-in (Menunggu / Dikonfirmasi).')
            ->assertSee('Tandai tidak hadir (2)')
            ->assertSee('id="modal-tidak-hadir"', false)
            ->assertSee('action="'.route('admin.queue.mark-no-show', ['session' => $session->id]).'"', false)
            ->getContent();

        $this->assertSame(1, preg_match('/<input[^>]*type="checkbox"[^>]*name="confirm"[^>]*>/', $html, $checkbox));
        $this->assertStringContainsString('required', $checkbox[0]);
        $this->assertSame(1, preg_match('/<textarea[^>]*name="reason"[^>]*>/', $html, $textarea));
        $this->assertStringContainsString('maxlength="255"', $textarea[0]);
        $this->assertStringNotContainsString('required', $textarea[0]);

        $this->assertSame(1, preg_match('/data-no-show-list>(.*?)<\/ul>/s', $html, $list));
        $this->assertStringContainsString('ANK-001 Pasien Menunggu', $list[1]);
        $this->assertStringContainsString('ANK-002 Pasien Terkonfirmasi', $list[1]);
        $this->assertStringNotContainsString('Pasien Sudah Hadir', $list[1]);
    }

    public function test_the_banner_is_hidden_exactly_at_the_end_time_where_the_server_would_refuse(): void
    {
        $session = $this->sessionOn('2026-10-06');
        $this->appointmentOn($session, 'Pasien Menunggu', AppointmentStatus::Pending);
        $this->travelTo('2026-10-06 12:00:00');

        $this->queue($session)
            ->assertOk()
            ->assertDontSee('data-no-show-banner', false)
            ->assertDontSee('modal-tidak-hadir', false)
            ->assertDontSee('Tandai tidak hadir (', false);

        $this->travelTo('2026-10-06 12:00:01');

        $this->queue($session)->assertOk()->assertSee('Tandai tidak hadir (1)');
    }

    public function test_there_is_no_banner_before_the_end_or_when_nothing_is_waiting(): void
    {
        $running = $this->sessionOn('2026-10-06');
        $this->appointmentOn($running, 'Pasien Menunggu', AppointmentStatus::Pending);
        $this->travelTo('2026-10-06 10:00:00');
        $this->queue($running)->assertOk()->assertDontSee('data-no-show-banner', false);

        $done = $this->endedSession();
        $this->appointmentOn($done, 'Pasien Hadir', AppointmentStatus::CheckedIn);
        $this->appointmentOn($done, 'Pasien Selesai', AppointmentStatus::Completed);
        $this->appointmentOn($done, 'Pasien Batal', AppointmentStatus::Cancelled);
        $this->queue($done)->assertOk()->assertDontSee('data-no-show-banner', false)->assertDontSee('modal-tidak-hadir', false);
    }

    public function test_a_viewer_sees_the_banner_text_but_no_button_and_no_modal(): void
    {
        $this->seedRoles();
        $viewer = User::factory()->staff()->create();
        $viewer->givePermissionTo('appointments.view');
        $session = $this->endedSession();
        $this->appointmentOn($session, 'Pasien Menunggu', AppointmentStatus::Pending);

        $this->queue($session, $viewer)
            ->assertOk()
            ->assertSee('data-no-show-banner', false)
            ->assertSee('Sesi telah berakhir pukul 12.00. 1 pendaftaran belum check-in')
            ->assertDontSee('modal-tidak-hadir', false)
            ->assertDontSee('Tandai tidak hadir (', false);
    }

    public function test_a_needs_review_session_shows_the_note_and_neither_the_button_nor_the_modal(): void
    {
        $session = $this->endedSession(['status' => SessionStatus::NeedsReview]);
        $this->appointmentOn($session, 'Pasien Menunggu', AppointmentStatus::Pending);

        $this->queue($session)
            ->assertOk()
            ->assertSee('role="status" data-under-review', false)
            ->assertSee(self::UNDER_REVIEW)
            ->assertDontSee('data-no-show-banner', false)
            ->assertDontSee('modal-tidak-hadir', false)
            ->assertDontSee('Tandai tidak hadir (', false);
    }

    public function test_e4_patient_names_are_escaped_in_the_modal_list_and_the_rows(): void
    {
        $session = $this->endedSession();
        $this->appointmentOn($session, '<script>alert(1)</script>', AppointmentStatus::Pending);

        $html = $this->queue($session)
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)
            ->getContent();

        $this->assertGreaterThanOrEqual(2, substr_count($html, '&lt;script&gt;alert(1)&lt;/script&gt;'));
        $this->assertSame(1, preg_match('/data-no-show-list>(.*?)<\/ul>/s', $html, $list));
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $list[1]);
    }
}
