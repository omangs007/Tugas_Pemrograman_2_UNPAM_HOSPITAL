<?php

namespace Tests\Feature\Admin;

use App\Enums\AppointmentStatus;
use App\Enums\PaymentType;
use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\InsurancePartner;
use App\Models\PatientProfile;
use App\Models\User;
use App\Services\Booking\AppointmentStatusService;
use App\Support\Actor;
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
 * Detail pendaftaran #38 (F7A-R18, E1): tombol per status dan waktu persis aturan server (check-in tepat jendela dibuka,
 * `aria-disabled` dengan teks "Dibuka HH.MM", `end` tepat dan `end + 1 menit`, koreksi hanya hari yang sama), BPJS dan
 * polis tersamar (SEK-12), escape (SEK-15), `kembali`, akses. Sesi uji: 09.00-12.00; check-in dibuka 08.00.
 */
class AdminAppointmentShowTest extends TestCase
{
    use InteractsWithMasterData;
    use InteractsWithSchedules;
    use InteractsWithStaff;
    use RefreshDatabase;

    private const BPJS = '0009900000011';

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

    /**
     * @param  array<string, mixed>  $overrides
     * @param  array<string, mixed>  $profile
     */
    private function appointmentFor(AppointmentStatus $status, string $date = '2026-10-06', array $overrides = [], array $profile = []): Appointment
    {
        $session = $this->createSession([
            'doctor_id' => $this->doctor->id,
            'clinic_id' => $this->clinic->id,
            'session_date' => $date,
            'start_time' => '09:00:00',
            'end_time' => '12:00:00',
            'quota' => 20,
            'doctor_schedule_id' => null,
        ]);

        $owner = PatientProfile::factory()->create($profile + ['full_name' => 'Siti Rahmawati']);

        return $this->bookAppointment($session, $status, array_merge(['patient_profile_id' => $owner->id], $overrides));
    }

    private function show(Appointment $appointment, array $query = [], ?User $user = null): TestResponse
    {
        return $this->actingAs($user ?? $this->officer)
            ->get(route('admin.appointments.show', ['appointment' => $appointment->booking_code] + $query));
    }

    /** @return list<string> nilai `to` dari semua field tersembunyi pada halaman, unik dan terurut */
    private function shownTargets(string $html): array
    {
        preg_match_all('/<input type="hidden" name="to" value="([a-z_]+)">/', $html, $matches);
        $targets = array_values(array_unique($matches[1]));
        sort($targets);

        return $targets;
    }

    // --- akses dan URL -----------------------------------------------------------------------

    /** @return array<string, array{string, int}> */
    public static function roles(): array
    {
        return [
            'super admin' => ['super-admin', 200],
            'petugas pendaftaran' => ['registration-officer', 200],
            'admin konten' => ['content-admin', 403],
        ];
    }

    #[DataProvider('roles')]
    public function test_access_per_staff_role(string $role, int $status): void
    {
        $this->show($this->appointmentFor(AppointmentStatus::Confirmed), [], $this->createStaff($role))->assertStatus($status);
    }

    public function test_a_patient_gets_403_and_a_guest_is_sent_to_the_admin_login(): void
    {
        $appointment = $this->appointmentFor(AppointmentStatus::Confirmed);

        $this->show($appointment, [], User::factory()->patient()->create())->assertForbidden();

        auth()->logout();
        $this->get(route('admin.appointments.show', ['appointment' => $appointment->booking_code]))->assertRedirect(route('admin.login'));
    }

    public function test_the_url_uses_the_booking_code_and_other_shapes_are_404(): void
    {
        $appointment = $this->appointmentFor(AppointmentStatus::Confirmed);

        $this->assertSame(url('/admin/pendaftaran/'.$appointment->booking_code), route('admin.appointments.show', ['appointment' => $appointment->booking_code]));

        $this->actingAs($this->officer);
        $this->get('/admin/pendaftaran/'.$appointment->id)->assertNotFound();
        $this->get('/admin/pendaftaran/abc')->assertNotFound();
        $this->get('/admin/pendaftaran/'.strtolower($appointment->booking_code))->assertNotFound();
        $this->get('/admin/pendaftaran/UPH-ZZZZ-ZZZZ')->assertNotFound();
        $this->get('/admin/pendaftaran/UPH-ABCD-EFGH')->assertNotFound();
    }

    // --- E1: tombol vs aturan server ---------------------------------------------------------

    /**
     * @return array<string, array{AppointmentStatus, string, string, list<string>, ?string}>
     *                                                                                        status, jam sekarang, tanggal sesi, tombol yang tampil (terurut), jam check-in dibuka bila tombol check-in nonaktif
     */
    public static function buttonMatrix(): array
    {
        $today = '2026-10-06';

        return [
            'pending, semenit sebelum jendela check-in' => [AppointmentStatus::Pending, $today.' 07:59:00', $today, ['cancelled', 'confirmed'], '08.00'],
            'pending, tepat jendela dibuka' => [AppointmentStatus::Pending, $today.' 08:00:00', $today, ['cancelled', 'checked_in', 'confirmed'], null],
            'pending, tepat akhir sesi' => [AppointmentStatus::Pending, $today.' 12:00:00', $today, ['cancelled', 'checked_in'], null],
            'pending, semenit setelah akhir sesi' => [AppointmentStatus::Pending, $today.' 12:01:00', $today, ['cancelled'], null],
            'confirmed, semenit sebelum jendela check-in' => [AppointmentStatus::Confirmed, $today.' 07:59:00', $today, ['cancelled'], '08.00'],
            'confirmed, tepat jendela dibuka' => [AppointmentStatus::Confirmed, $today.' 08:00:00', $today, ['cancelled', 'checked_in'], null],
            'confirmed, tepat akhir sesi' => [AppointmentStatus::Confirmed, $today.' 12:00:00', $today, ['cancelled', 'checked_in'], null],
            'confirmed, semenit setelah akhir sesi' => [AppointmentStatus::Confirmed, $today.' 12:01:00', $today, ['cancelled'], null],
            'confirmed, sesi besok' => [AppointmentStatus::Confirmed, $today.' 07:00:00', '2026-10-07', ['cancelled'], null],
            'checked_in, hari yang sama' => [AppointmentStatus::CheckedIn, $today.' 10:00:00', $today, ['completed', 'confirmed'], null],
            'checked_in, sesi kemarin (tanpa koreksi)' => [AppointmentStatus::CheckedIn, $today.' 10:00:00', '2026-10-05', ['completed'], null],
            'completed' => [AppointmentStatus::Completed, $today.' 10:00:00', $today, [], null],
            'cancelled' => [AppointmentStatus::Cancelled, $today.' 10:00:00', $today, [], null],
            'no_show' => [AppointmentStatus::NoShow, $today.' 10:00:00', $today, [], null],
        ];
    }

    /**
     * @param  list<string>  $expected
     */
    #[DataProvider('buttonMatrix')]
    public function test_the_buttons_are_exactly_what_the_server_accepts(AppointmentStatus $status, string $now, string $sessionDate, array $expected, ?string $opensAt): void
    {
        $appointment = $this->appointmentFor($status, $sessionDate);
        $this->travelTo($now);
        Notification::fake();

        $html = $this->show($appointment)->assertOk()->getContent();

        $this->assertSame($expected, $this->shownTargets($html), 'Tombol yang tampil.');

        if ($opensAt === null) {
            $this->assertStringNotContainsString('aria-disabled="true"', $html);
            $this->assertStringNotContainsString('Dibuka ', $html);
        } else {
            $this->assertStringContainsString('aria-disabled="true"', $html);
            $this->assertStringContainsString('Dibuka '.$opensAt, $html);
        }

        // Arah sebaliknya: setiap tujuan dicoba ke server (dalam savepoint yang digulung balik).
        $accepted = [];

        foreach (['confirmed', 'checked_in', 'completed', 'cancelled'] as $to) {
            DB::beginTransaction();
            $this->flushSession();

            $this->actingAs($this->officer)->post(route('admin.appointments.transition', ['appointment' => $appointment->booking_code]), [
                'to' => $to,
                'expected_status' => $status->value,
                'reason' => 'Alasan uji petugas',
            ]);

            if (DB::table('appointments')->where('id', $appointment->id)->value('status') !== $status->value) {
                $accepted[] = $to;
            }

            DB::rollBack();
        }

        sort($accepted);

        $this->assertSame($expected, $accepted, 'Tujuan yang diterima server.');
    }

    public function test_a_check_in_before_the_window_is_a_focusable_aria_disabled_button_with_visible_text(): void
    {
        $appointment = $this->appointmentFor(AppointmentStatus::Confirmed);
        $this->travelTo('2026-10-06 07:59:00');

        $html = $this->show($appointment)->assertOk()->getContent();

        $this->assertSame(1, preg_match('/<button[^>]*aria-disabled="true"[^>]*>/', $html, $button));
        $this->assertStringContainsString('type="button"', $button[0]);
        $this->assertStringContainsString('aria-describedby="checkin-open-'.$appointment->id.'"', $button[0]);
        $this->assertDoesNotMatchRegularExpression('/<button[^>]*\sdisabled[\s>=]/', $html);
        $this->assertStringContainsString('id="checkin-open-'.$appointment->id.'"', $html);
        $this->assertStringContainsString('Dibuka 08.00', $html);
        $this->assertStringNotContainsString('<input type="hidden" name="to" value="checked_in">', $html);
    }

    public function test_the_modals_carry_the_static_fields_and_the_reason_limits(): void
    {
        $confirmed = $this->appointmentFor(AppointmentStatus::Confirmed);
        $this->travelTo('2026-10-06 08:30:00');

        $html = $this->show($confirmed)->assertOk()->getContent();
        $action = route('admin.appointments.transition', ['appointment' => $confirmed->booking_code]);

        $this->assertStringContainsString('id="modal-batal"', $html);
        $this->assertStringContainsString('action="'.$action.'"', $html);
        $this->assertStringContainsString('<input type="hidden" name="to" value="cancelled">', $html);
        $this->assertStringContainsString('<input type="hidden" name="expected_status" value="confirmed">', $html);
        $this->assertSame(1, preg_match('/<textarea[^>]*name="reason"[^>]*>/', $html, $textarea));
        $this->assertStringContainsString('minlength="5"', $textarea[0]);
        $this->assertStringContainsString('maxlength="255"', $textarea[0]);
        $this->assertStringContainsString('required', $textarea[0]);
        $this->assertStringNotContainsString('id="modal-koreksi"', $html);

        $checked = $this->appointmentFor(AppointmentStatus::CheckedIn);
        $html = $this->show($checked)->assertOk()->getContent();

        $this->assertStringContainsString('id="modal-koreksi"', $html);
        $this->assertStringNotContainsString('id="modal-batal"', $html);
        $this->assertStringContainsString('<input type="hidden" name="to" value="confirmed">', $html);
        $this->assertStringContainsString('<input type="hidden" name="expected_status" value="checked_in">', $html);
    }

    public function test_inline_buttons_post_to_the_transition_route_with_expected_status_and_kembali(): void
    {
        $appointment = $this->appointmentFor(AppointmentStatus::Confirmed);
        $this->travelTo('2026-10-06 08:30:00');

        $html = $this->show($appointment, ['kembali' => '/admin/antrean?tanggal=2026-10-06&sesi=4'])->assertOk()->getContent();

        $this->assertStringContainsString('<input type="hidden" name="to" value="checked_in">', $html);
        $this->assertStringContainsString('<input type="hidden" name="expected_status" value="confirmed">', $html);
        $this->assertStringContainsString('<input type="hidden" name="kembali" value="/admin/antrean?tanggal=2026-10-06&amp;sesi=4">', $html);
        $this->assertStringContainsString('action="'.route('admin.appointments.transition', ['appointment' => $appointment->booking_code]).'"', $html);
    }

    public function test_a_staff_member_with_only_the_view_permission_sees_no_action_buttons(): void
    {
        $this->seedRoles();
        $viewer = User::factory()->staff()->create();
        $viewer->givePermissionTo('appointments.view');
        $appointment = $this->appointmentFor(AppointmentStatus::Confirmed);
        $this->travelTo('2026-10-06 08:30:00');

        $html = $this->show($appointment, [], $viewer)->assertOk()->getContent();

        $this->assertSame([], $this->shownTargets($html));
        $this->assertStringNotContainsString('id="modal-batal"', $html);
        $this->assertStringContainsString($appointment->booking_code, $html);
    }

    public function test_a_final_appointment_says_there_are_no_more_actions(): void
    {
        $this->show($this->appointmentFor(AppointmentStatus::Completed))
            ->assertOk()
            ->assertSee('Tidak ada aksi yang tersedia untuk status ini.');
    }

    // --- isi halaman -------------------------------------------------------------------------

    public function test_the_detail_shows_the_booking_data_history_and_proof_button(): void
    {
        $appointment = $this->appointmentFor(AppointmentStatus::Confirmed, '2026-10-06', ['booking_code' => 'UPH-ABCD-2345', 'complaint' => 'Demam tiga hari']);
        $this->travelTo('2026-10-06 08:30:00');
        app(AppointmentStatusService::class)->transition($appointment, AppointmentStatus::CheckedIn, Actor::staff($this->officer));

        $this->show($appointment)
            ->assertOk()
            ->assertSee('Pendaftaran UPH-ABCD-2345')
            ->assertSee('ANK-001')
            ->assertSee('Poli Anak')
            ->assertSee('dr. Sinta Maharani, Sp.A')
            ->assertSee('Selasa, 6 Oktober 2026')
            ->assertSee('09.00–12.00')
            ->assertSee('Umum/Pribadi')
            ->assertSee('Online')
            ->assertSee('Demam tiga hari')
            ->assertSee('Riwayat Status')
            ->assertSee('Hadir/check-in')
            ->assertSee('Staf')
            ->assertSee($this->officer->name)
            ->assertSee('Siti Rahmawati')
            ->assertSee('href="'.route('admin.appointments.proof', ['appointment' => 'UPH-ABCD-2345']).'"', false)
            ->assertSee('Cetak Bukti');
    }

    public function test_the_bpjs_number_is_masked_everywhere(): void
    {
        $appointment = $this->appointmentFor(
            AppointmentStatus::Confirmed,
            '2026-10-06',
            ['payment_type' => PaymentType::Bpjs, 'payment_reference' => self::BPJS, 'payment_reference_last4' => '0011'],
            ['bpjs_number' => '0009900000099'],
        );

        $this->show($appointment)
            ->assertOk()
            ->assertSee('BPJS Kesehatan')
            ->assertSee('•••••••••0011')
            ->assertSee('•••••••••0099')
            ->assertDontSee(self::BPJS)
            ->assertDontSee('0009900000099')
            ->assertDontSee('000990000');
    }

    public function test_the_policy_number_is_masked(): void
    {
        $partner = InsurancePartner::factory()->create(['name' => 'Asuransi Contoh Sehat']);
        $appointment = $this->appointmentFor(AppointmentStatus::Confirmed, '2026-10-06', [
            'payment_type' => PaymentType::Asuransi,
            'insurance_partner_id' => $partner->id,
            'payment_reference' => 'POL-778899/12',
            'payment_reference_last4' => '9/12',
        ]);

        $this->show($appointment)
            ->assertOk()
            ->assertSee('Asuransi - Asuransi Contoh Sehat')
            ->assertSee('•••••••••9/12')
            ->assertDontSee('POL-778899');
    }

    public function test_patient_name_and_complaint_are_escaped(): void
    {
        $appointment = $this->appointmentFor(AppointmentStatus::Confirmed, '2026-10-06', ['complaint' => '<script>alert(2)</script>'], ['full_name' => '<script>alert(1)</script>']);

        $this->show($appointment)
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertDontSee('<script>alert(2)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertSee('&lt;script&gt;alert(2)&lt;/script&gt;', false);
    }

    public function test_the_patient_card_links_to_the_patient_and_notes_a_missing_account(): void
    {
        $walkIn = $this->appointmentFor(AppointmentStatus::Confirmed);

        $this->show($walkIn)
            ->assertOk()
            ->assertSee('href="'.route('admin.patients.show', $walkIn->patient_profile_id).'"', false)
            ->assertSee('Tanpa akun online');

        $account = PatientProfile::factory()->withAccount()->create();
        $withAccount = $this->bookAppointment($this->createSession(['doctor_id' => $this->doctor->id, 'clinic_id' => $this->clinic->id, 'session_date' => '2026-10-06', 'doctor_schedule_id' => null]), AppointmentStatus::Confirmed, ['patient_profile_id' => $account->id]);

        $this->show($withAccount)->assertOk()->assertDontSee('Tanpa akun online');
    }

    public function test_a_valid_kembali_becomes_the_back_link_and_an_evil_one_is_ignored(): void
    {
        $appointment = $this->appointmentFor(AppointmentStatus::Confirmed);

        $this->show($appointment, ['kembali' => '/admin/antrean?tanggal=2026-10-06'])
            ->assertOk()
            ->assertSee('href="/admin/antrean?tanggal=2026-10-06"', false);

        $this->show($appointment, ['kembali' => '//evil.test/x'])
            ->assertOk()
            ->assertDontSee('evil.test')
            ->assertSee('href="'.route('admin.appointments.index').'"', false);

        $this->show($appointment, ['kembali' => ['/admin/antrean']])
            ->assertOk()
            ->assertSee('href="'.route('admin.appointments.index').'"', false);
    }

    public function test_the_page_loads_in_strict_mode_with_a_long_history(): void
    {
        $appointment = $this->appointmentFor(AppointmentStatus::Pending);
        $this->travelTo('2026-10-06 08:30:00');
        $service = app(AppointmentStatusService::class);
        Notification::fake();
        $service->transition($appointment, AppointmentStatus::Confirmed, Actor::staff($this->officer));
        $service->transition($appointment, AppointmentStatus::CheckedIn, Actor::staff($this->officer));
        $service->transition($appointment, AppointmentStatus::Confirmed, Actor::staff($this->officer), 'Salah memilih pasien');

        $html = $this->show($appointment)->assertOk()->getContent();

        $this->assertSame(3, substr_count($html, 'data-history-item'));
        $this->assertStringContainsString('Salah memilih pasien', $html);
    }
}
