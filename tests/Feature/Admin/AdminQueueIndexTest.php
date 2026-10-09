<?php

namespace Tests\Feature\Admin;

use App\Enums\AppointmentStatus;
use App\Enums\PaymentType;
use App\Enums\SessionStatus;
use App\Models\Appointment;
use App\Models\AppointmentStatusHistory;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\PatientProfile;
use App\Models\PracticeSession;
use App\Models\User;
use App\Support\NavigationMenu;
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
 * Antrean Hari Ini (W-19, F7A-R23): pemilih tanggal dan sesi, ringkasan, urutan nomor antrean, tiga cabang `kode`,
 * aksi baris yang persis aturan server (E1, BKG-14), query liar (VAL-07, E3), kosong (EMP-03), escape (SEK-15, E4),
 * akses per peran. Waktu dibekukan pada Selasa 2026-10-06 07.00; sesi uji 09.00-12.00 (check-in dibuka 08.00).
 */
class AdminQueueIndexTest extends TestCase
{
    use InteractsWithMasterData;
    use InteractsWithSchedules;
    use InteractsWithStaff;
    use RefreshDatabase;

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
    private function sessionOn(string $date, array $overrides = [], ?Doctor $doctor = null): PracticeSession
    {
        $doctor ??= $this->doctor;

        return $this->createSession(array_merge([
            'doctor_id' => $doctor->id,
            'clinic_id' => $doctor->clinic_id,
            'session_date' => $date,
            'start_time' => '09:00:00',
            'end_time' => '12:00:00',
            'quota' => 20,
            'doctor_schedule_id' => null,
        ], $overrides));
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

    /** @param  array<string, mixed>  $query */
    private function queue(array $query = [], ?User $user = null): TestResponse
    {
        return $this->actingAs($user ?? $this->officer)->get(route('admin.queue.index', $query));
    }

    /** @return list<string> nilai `to` dari semua field tersembunyi pada halaman, unik dan terurut */
    private function formTargets(string $html): array
    {
        preg_match_all('/<input type="hidden" name="to" value="([a-z_]+)">/', $html, $matches);
        $targets = array_values(array_unique($matches[1]));
        sort($targets);

        return $targets;
    }

    /** @return list<array{int, bool}> id sesi pada pemilih sesi, berurutan, dan apakah terpilih */
    private function sessionOptions(string $html): array
    {
        preg_match_all('/<option value="(\d+)"\s*(selected)?\s*>/', $html, $matches, PREG_SET_ORDER);

        return array_map(fn (array $match): array => [(int) $match[1], ($match[2] ?? '') === 'selected'], $matches);
    }

    private function selectedSessionId(string $html): ?int
    {
        foreach ($this->sessionOptions($html) as [$id, $selected]) {
            if ($selected) {
                return $id;
            }
        }

        return null;
    }

    // --- akses -------------------------------------------------------------------------------

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
        $this->queue([], $this->createStaff($role))->assertStatus($status);
    }

    public function test_a_patient_gets_403_and_a_guest_is_sent_to_the_admin_login(): void
    {
        $this->queue([], User::factory()->patient()->create())->assertForbidden();

        auth()->logout();
        $this->get(route('admin.queue.index'))->assertRedirect(route('admin.login'));
    }

    public function test_the_url_uses_the_indonesian_segment(): void
    {
        $this->assertSame(url('/admin/antrean'), route('admin.queue.index'));
        $this->assertSame(url('/admin/antrean/7/tidak-hadir'), route('admin.queue.mark-no-show', ['session' => 7]));
    }

    public function test_a_staff_member_with_only_the_view_permission_sees_the_queue_without_any_action(): void
    {
        $this->seedRoles();
        $viewer = User::factory()->staff()->create();
        $viewer->givePermissionTo('appointments.view');
        $session = $this->sessionOn('2026-10-06');
        $this->appointmentOn($session, 'Siti Rahmawati');
        $this->travelTo('2026-10-06 12:01:00');

        $html = $this->queue([], $viewer)->assertOk()->assertSee('Siti Rahmawati')->getContent();

        $this->assertSame([], $this->formTargets($html));
        $this->assertStringNotContainsString('dilakukan di halaman detail', $html);
        $this->assertStringNotContainsString('modal-tidak-hadir', $html);
        $this->assertStringNotContainsString('Tandai tidak hadir (', $html);
    }

    // --- pemilih tanggal dan sesi ------------------------------------------------------------

    public function test_the_default_is_today_and_the_first_session_by_start_time_then_clinic_name(): void
    {
        $surgery = $this->createDoctor(['name' => 'Budi Hartono'], $this->createClinic(['name' => 'Poli Bedah', 'code' => 'BDH']));
        $afternoon = $this->sessionOn('2026-10-06', ['start_time' => '13:00:00', 'end_time' => '15:00:00']);
        $bedah = $this->sessionOn('2026-10-06', [], $surgery);
        $anak = $this->sessionOn('2026-10-06');
        $this->sessionOn('2026-10-07');

        $html = $this->queue()->assertOk()->assertSee('Selasa, 6 Oktober 2026')->assertSee('id="filter-tanggal"', false)->assertSee('value="2026-10-06"', false)->getContent();

        $this->assertSame([[$anak->id, true], [$bedah->id, false], [$afternoon->id, false]], $this->sessionOptions($html));
    }

    public function test_the_session_options_show_clinic_doctor_time_and_booked_of_quota(): void
    {
        $session = $this->sessionOn('2026-10-06');
        $this->appointmentOn($session, 'Pasien Satu');
        $this->appointmentOn($session, 'Pasien Dua', AppointmentStatus::Cancelled);

        $this->queue()
            ->assertOk()
            ->assertSee('Poli Anak - dr. Sinta Maharani, Sp.A - 09.00–12.00 - 1/20 terisi');
    }

    public function test_tanggal_shows_another_date_including_the_past_and_the_last_day_of_the_window(): void
    {
        $this->appointmentOn($this->sessionOn('2026-10-05'), 'Pasien Kemarin');
        $this->appointmentOn($this->sessionOn('2026-10-06'), 'Pasien Hari Ini');
        $this->appointmentOn($this->sessionOn('2026-11-05'), 'Pasien Batas Jendela');

        $this->queue(['tanggal' => '2026-10-05'])->assertOk()->assertSee('Pasien Kemarin')->assertDontSee('Pasien Hari Ini')->assertDontSee('Filter tidak valid');
        $this->queue(['tanggal' => '2026-11-05'])->assertOk()->assertSee('Pasien Batas Jendela')->assertDontSee('Filter tidak valid');
        $this->queue(['tanggal' => '2026-11-06'])->assertOk()->assertSee('Filter tidak valid dan diabaikan.')->assertSee('Pasien Hari Ini');
    }

    public function test_a_session_id_from_another_date_falls_back_to_the_first_session_of_the_date(): void
    {
        $this->appointmentOn($this->sessionOn('2026-10-06'), 'Pasien Hari Ini');
        $tomorrow = $this->sessionOn('2026-10-07');
        $this->appointmentOn($tomorrow, 'Pasien Besok');

        $this->queue(['sesi' => $tomorrow->id])
            ->assertOk()
            ->assertSee('Pasien Hari Ini')
            ->assertDontSee('Pasien Besok')
            ->assertDontSee('Filter tidak valid');
    }

    public function test_poli_and_dokter_narrow_the_session_options(): void
    {
        $surgeryClinic = $this->createClinic(['name' => 'Poli Bedah', 'code' => 'BDH']);
        $surgeon = $this->createDoctor(['name' => 'Budi Hartono'], $surgeryClinic);
        $anak = $this->sessionOn('2026-10-06');
        $bedah = $this->sessionOn('2026-10-06', [], $surgeon);

        $html = $this->queue(['poli' => $surgeryClinic->slug])->assertOk()->getContent();
        $this->assertSame([[$bedah->id, true]], $this->sessionOptions($html));

        $html = $this->queue(['dokter' => $this->doctor->slug])->assertOk()->getContent();
        $this->assertSame([[$anak->id, true]], $this->sessionOptions($html));

        $this->queue(['poli' => $surgeryClinic->slug, 'dokter' => $this->doctor->slug])
            ->assertOk()
            ->assertSee('Tidak ada sesi praktik pada tanggal ini');
    }

    public function test_every_session_status_is_listed_with_its_label_when_not_open(): void
    {
        $this->sessionOn('2026-10-06', ['status' => SessionStatus::NeedsReview]);

        $this->queue()->assertOk()->assertSee('(Perlu tindak lanjut)');
    }

    // --- tabel, ringkasan, urutan ------------------------------------------------------------

    public function test_the_rows_are_ordered_by_queue_number(): void
    {
        $session = $this->sessionOn('2026-10-06');
        $this->appointmentOn($session, 'Pasien Tiga', AppointmentStatus::Confirmed, ['queue_number' => 3]);
        $this->appointmentOn($session, 'Pasien Satu', AppointmentStatus::Confirmed, ['queue_number' => 1]);
        $this->appointmentOn($session, 'Pasien Dua', AppointmentStatus::Confirmed, ['queue_number' => 2]);

        $html = $this->queue()->assertOk()->getContent();
        $positions = array_map(fn (string $name): int => (int) strpos($html, $name), ['Pasien Satu', 'Pasien Dua', 'Pasien Tiga']);
        $sorted = $positions;
        sort($sorted);

        $this->assertNotContains(0, $positions);
        $this->assertSame($sorted, $positions);
    }

    public function test_the_table_has_the_agreed_columns_and_a_link_to_the_detail_with_kembali(): void
    {
        $session = $this->sessionOn('2026-10-06');
        $appointment = $this->appointmentOn($session, 'Siti Rahmawati', AppointmentStatus::Confirmed, ['booking_code' => 'UPH-ABCD-2345'], ['patient_number' => 'P000123']);
        $detail = route('admin.appointments.show', ['appointment' => 'UPH-ABCD-2345', 'kembali' => '/admin/antrean?tanggal=2026-10-06&sesi='.$session->id]);

        $this->queue()
            ->assertOk()
            ->assertSee('data-label="Antrean"', false)
            ->assertSee('data-label="Kode booking"', false)
            ->assertSee('data-label="Pasien"', false)
            ->assertSee('data-label="Pembayaran"', false)
            ->assertSee('data-label="Status"', false)
            ->assertSee('data-label="Aksi"', false)
            ->assertSee('ANK-001')
            ->assertSee('UPH-ABCD-2345')
            ->assertSee('Siti Rahmawati')
            ->assertSee('P000123')
            ->assertSee('Umum/Pribadi')
            ->assertSee('Dikonfirmasi')
            ->assertSee('href="'.e($detail).'"', false)
            ->assertSee('<tr id="baris-'.$appointment->id.'">', false);
    }

    public function test_the_summary_shows_booked_of_quota_and_the_count_per_status(): void
    {
        $session = $this->sessionOn('2026-10-06');

        foreach ([
            AppointmentStatus::Pending, AppointmentStatus::Confirmed, AppointmentStatus::Confirmed, AppointmentStatus::CheckedIn,
            AppointmentStatus::Completed, AppointmentStatus::NoShow, AppointmentStatus::Cancelled,
        ] as $index => $status) {
            $this->appointmentOn($session, 'Pasien '.($index + 1), $status);
        }

        $html = $this->queue()->assertOk()->assertSee('Terisi 6 dari 20')->getContent();

        preg_match_all('/data-status-count="([a-z_]+)">.*?<span class="fw-semibold">(\d+)<\/span>/s', $html, $matches, PREG_SET_ORDER);
        $counts = [];

        foreach ($matches as $match) {
            $counts[] = [$match[1], (int) $match[2]];
        }

        $this->assertSame([
            ['pending', 1], ['confirmed', 2], ['checked_in', 1], ['completed', 1], ['no_show', 1], ['cancelled', 1],
        ], $counts);
    }

    public function test_many_rows_of_every_status_render_in_strict_mode_without_lazy_loading(): void
    {
        $session = $this->sessionOn('2026-10-06');

        foreach (AppointmentStatus::cases() as $status) {
            $this->appointmentOn($session, 'Pasien '.$status->value, $status);
            $this->appointmentOn($session, 'Pasien Lagi '.$status->value, $status);
        }

        $this->travelTo('2026-10-06 08:30:00');
        $html = $this->queue()->assertOk()->getContent();

        $this->assertSame(12, substr_count($html, 'data-label="Kode booking"'));
    }

    public function test_the_page_shows_the_load_time_and_a_reload_link_to_the_same_queue(): void
    {
        $session = $this->sessionOn('2026-10-06');
        $this->appointmentOn($session, 'Siti Rahmawati');
        $this->travelTo('2026-10-06 10:42:00');

        $this->queue(['tanggal' => '2026-10-06', 'sesi' => $session->id])
            ->assertOk()
            ->assertSee('Dimuat pukul 10.42 WIB')
            ->assertSee('Muat ulang')
            ->assertSee('href="'.e('/admin/antrean?tanggal=2026-10-06&sesi='.$session->id).'"', false);
    }

    public function test_the_kembali_of_the_row_forms_is_the_current_queue_url_with_the_active_filters_and_never_the_code(): void
    {
        $session = $this->sessionOn('2026-10-06');
        $this->appointmentOn($session, 'Siti Rahmawati', AppointmentStatus::Confirmed, ['booking_code' => 'UPH-ABCD-2345']);
        $this->travelTo('2026-10-06 08:30:00');

        $expected = route('admin.queue.index', ['tanggal' => '2026-10-06', 'sesi' => $session->id, 'poli' => $this->clinic->slug, 'dokter' => $this->doctor->slug], false);

        $this->queue(['tanggal' => '2026-10-06', 'poli' => $this->clinic->slug, 'dokter' => $this->doctor->slug, 'kode' => 'UPH-ABCD-2345'])
            ->assertOk()
            ->assertSee('<input type="hidden" name="kembali" value="'.e($expected).'">', false)
            ->assertDontSee('kode=', false);
    }

    public function test_a_row_form_returns_to_the_queue_and_the_row_changes(): void
    {
        $session = $this->sessionOn('2026-10-06');
        $this->appointmentOn($session, 'Siti Rahmawati', AppointmentStatus::Confirmed, ['booking_code' => 'UPH-ABCD-2345']);
        $this->travelTo('2026-10-06 08:30:00');
        Notification::fake();

        $html = $this->queue(['sesi' => $session->id])->assertOk()->getContent();
        $this->assertSame(1, preg_match('/<input type="hidden" name="kembali" value="([^"]+)">/', $html, $matches));
        $kembali = html_entity_decode($matches[1]);
        $this->assertSame('/admin/antrean?tanggal=2026-10-06&sesi='.$session->id, $kembali);

        $this->flushSession();
        $this->actingAs($this->officer)
            ->post(route('admin.appointments.transition', ['appointment' => 'UPH-ABCD-2345']), ['to' => 'checked_in', 'expected_status' => 'confirmed', 'kembali' => $kembali])
            ->assertRedirect($kembali)
            ->assertSessionHas('success', 'ANK-001 check-in berhasil.');

        $this->get($kembali)->assertOk()->assertSee('ANK-001 check-in berhasil.')->assertSee('Hadir/check-in');
    }

    // --- E1: aksi baris vs aturan server -----------------------------------------------------

    /**
     * @return array<string, array{AppointmentStatus, string, string, list<string>, list<string>, ?string}>
     *                                                                                                      status, jam sekarang, tanggal sesi, tombol form (terurut), tautan ke detail (label), jam check-in dibuka bila tombol check-in nonaktif
     */
    public static function actionMatrix(): array
    {
        $today = '2026-10-06';

        return [
            'pending, semenit sebelum jendela check-in' => [AppointmentStatus::Pending, $today.' 07:59:00', $today, ['confirmed'], ['Batalkan'], '08.00'],
            'pending, tepat jendela dibuka' => [AppointmentStatus::Pending, $today.' 08:00:00', $today, ['checked_in', 'confirmed'], ['Batalkan'], null],
            'pending, tengah sesi (check-in langsung)' => [AppointmentStatus::Pending, $today.' 10:00:00', $today, ['checked_in', 'confirmed'], ['Batalkan'], null],
            'pending, tepat akhir sesi' => [AppointmentStatus::Pending, $today.' 12:00:00', $today, ['checked_in'], ['Batalkan'], null],
            'pending, semenit setelah akhir sesi' => [AppointmentStatus::Pending, $today.' 12:01:00', $today, [], ['Batalkan'], null],
            'confirmed, semenit sebelum jendela check-in' => [AppointmentStatus::Confirmed, $today.' 07:59:00', $today, [], ['Batalkan'], '08.00'],
            'confirmed, tepat jendela dibuka' => [AppointmentStatus::Confirmed, $today.' 08:00:00', $today, ['checked_in'], ['Batalkan'], null],
            'confirmed, tepat akhir sesi' => [AppointmentStatus::Confirmed, $today.' 12:00:00', $today, ['checked_in'], ['Batalkan'], null],
            'confirmed, semenit setelah akhir sesi' => [AppointmentStatus::Confirmed, $today.' 12:01:00', $today, [], ['Batalkan'], null],
            'confirmed, sesi besok' => [AppointmentStatus::Confirmed, $today.' 07:00:00', '2026-10-07', [], ['Batalkan'], null],
            'checked_in, hari yang sama' => [AppointmentStatus::CheckedIn, $today.' 10:00:00', $today, ['completed'], ['Koreksi Check-in'], null],
            'checked_in, sesi kemarin (tanpa koreksi)' => [AppointmentStatus::CheckedIn, $today.' 10:00:00', '2026-10-05', ['completed'], [], null],
            'completed' => [AppointmentStatus::Completed, $today.' 10:00:00', $today, [], [], null],
            'cancelled' => [AppointmentStatus::Cancelled, $today.' 10:00:00', $today, [], [], null],
            'no_show' => [AppointmentStatus::NoShow, $today.' 10:00:00', $today, [], [], null],
        ];
    }

    /**
     * @param  list<string>  $forms
     * @param  list<string>  $links
     */
    #[DataProvider('actionMatrix')]
    public function test_the_row_actions_are_exactly_what_the_server_accepts(AppointmentStatus $status, string $now, string $sessionDate, array $forms, array $links, ?string $opensAt): void
    {
        $session = $this->sessionOn($sessionDate);
        $appointment = $this->appointmentOn($session, 'Pasien Uji', $status);
        $this->travelTo($now);
        Notification::fake();

        $html = $this->queue(['tanggal' => $sessionDate, 'sesi' => $session->id])->assertOk()->getContent();

        // Tombol form (konfirmasi, check-in, selesai) dan tautan ke detail (batal, koreksi).
        $this->assertSame($forms, $this->formTargets($html), 'Tombol form.');
        $this->assertSame(count($forms), substr_count($html, 'name="to"'), 'Tidak ada field `to` lain.');
        $this->assertSame(count($links), substr_count($html, 'dilakukan di halaman detail'), 'Tautan ke detail.');

        foreach (['Batalkan', 'Koreksi Check-in'] as $label) {
            if (in_array($label, $links, true)) {
                $this->assertStringContainsString($label, $html);
            } else {
                $this->assertStringNotContainsString($label, $html);
            }
        }

        // Tidak pernah tombol no_show (atau pending) per baris: hanya field `to` yang dibatasi (`expected_status` boleh `pending`).
        $this->assertStringNotContainsString('name="to" value="no_show"', $html);
        $this->assertStringNotContainsString('name="to" value="pending"', $html);

        if ($opensAt === null) {
            $this->assertStringNotContainsString('aria-disabled="true"', $html);
            $this->assertStringNotContainsString('Dibuka ', $html);
        } else {
            $this->assertSame(1, substr_count($html, 'aria-disabled="true"'));
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

        $shown = $forms;

        foreach ($links as $label) {
            $shown[] = $label === 'Batalkan' ? 'cancelled' : 'confirmed';
        }

        sort($shown);
        sort($accepted);

        $this->assertSame($shown, $accepted, 'Tujuan yang diterima server.');
    }

    public function test_a_check_in_before_the_window_is_a_focusable_aria_disabled_button_with_visible_text(): void
    {
        $session = $this->sessionOn('2026-10-06');
        $appointment = $this->appointmentOn($session, 'Siti Rahmawati', AppointmentStatus::Confirmed);
        $this->travelTo('2026-10-06 07:59:00');

        $html = $this->queue()->assertOk()->getContent();

        $this->assertSame(1, preg_match('/<button[^>]*aria-disabled="true"[^>]*>/', $html, $button));
        $this->assertStringContainsString('type="button"', $button[0]);
        $this->assertStringContainsString('aria-describedby="checkin-open-'.$appointment->id.'"', $button[0]);
        $this->assertDoesNotMatchRegularExpression('/<button[^>]*\sdisabled[\s>=]/', $html);
        $this->assertStringContainsString('id="checkin-open-'.$appointment->id.'"', $html);
        $this->assertStringContainsString('Dibuka 08.00', $html);
    }

    public function test_cancel_and_correction_are_links_to_the_detail_that_carry_the_queue_as_kembali(): void
    {
        $session = $this->sessionOn('2026-10-06');
        $confirmed = $this->appointmentOn($session, 'Pasien Konfirmasi', AppointmentStatus::Confirmed);
        $checkedIn = $this->appointmentOn($session, 'Pasien Hadir', AppointmentStatus::CheckedIn);
        $this->travelTo('2026-10-06 09:30:00');

        $kembali = '/admin/antrean?tanggal=2026-10-06&sesi='.$session->id;
        $html = $this->queue()->assertOk()->getContent();

        foreach ([$confirmed, $checkedIn] as $appointment) {
            $this->assertStringContainsString(
                'href="'.e(route('admin.appointments.show', ['appointment' => $appointment->booking_code, 'kembali' => $kembali])).'"',
                $html,
            );
        }

        // Alasan dikumpulkan di halaman detail: antrean tidak memuat textarea alasan atau modal batal/koreksi.
        $this->assertStringNotContainsString('id="modal-batal"', $html);
        $this->assertStringNotContainsString('id="modal-koreksi"', $html);
        $this->assertStringNotContainsString('name="reason" rows="3" minlength', $html);
    }

    public function test_scanning_does_not_check_in_it_only_finds_and_highlights(): void
    {
        $session = $this->sessionOn('2026-10-06');
        $appointment = $this->appointmentOn($session, 'Siti Rahmawati', AppointmentStatus::Confirmed, ['booking_code' => 'UPH-ABCD-2345']);
        $this->travelTo('2026-10-06 08:30:00');

        $this->queue(['kode' => 'UPH-ABCD-2345'])->assertOk()->assertSee('table-warning', false);

        $this->assertSame('confirmed', DB::table('appointments')->where('id', $appointment->id)->value('status'));
        $this->assertSame(0, AppointmentStatusHistory::query()->count());
    }

    // --- kode booking: tiga cabang (F7A-R23, BR-69) ------------------------------------------

    public function test_a_code_in_another_session_of_the_same_date_moves_the_session_and_highlights_the_row(): void
    {
        $morning = $this->sessionOn('2026-10-06');
        $afternoon = $this->sessionOn('2026-10-06', ['start_time' => '13:00:00', 'end_time' => '15:00:00']);
        $this->appointmentOn($morning, 'Pasien Pagi');
        $target = $this->appointmentOn($afternoon, 'Pasien Siang', AppointmentStatus::Confirmed, ['booking_code' => 'UPH-ABCD-2345']);

        $html = $this->queue(['tanggal' => '2026-10-06', 'sesi' => $morning->id, 'kode' => 'uph abcd 2345'])
            ->assertOk()
            ->assertSee('data-scan-result="found">Kode booking UPH-ABCD-2345 ditemukan pada antrean ANK-001.', false)
            ->assertSee('Pasien Siang')
            ->assertDontSee('Pasien Pagi')
            ->getContent();

        $this->assertSame($afternoon->id, $this->selectedSessionId($html));
        $this->assertStringContainsString('<tr id="baris-'.$target->id.'" class="table-warning" aria-current="true">', $html);
        $this->assertStringContainsString('role="status" data-scan-result="found"', $html);
        $this->assertSame(1, substr_count($html, 'aria-current="true"'));
    }

    public function test_a_code_in_the_displayed_session_is_highlighted_and_other_rows_are_not(): void
    {
        $session = $this->sessionOn('2026-10-06');
        $other = $this->appointmentOn($session, 'Pasien Lain');
        $target = $this->appointmentOn($session, 'Pasien Dicari', AppointmentStatus::Confirmed, ['booking_code' => 'UPH-ABCD-2345']);

        $html = $this->queue(['kode' => 'UPH-ABCD-2345'])->assertOk()->assertSee('ditemukan pada antrean ANK-002.')->getContent();

        $this->assertStringContainsString('<tr id="baris-'.$target->id.'" class="table-warning" aria-current="true">', $html);
        $this->assertStringContainsString('<tr id="baris-'.$other->id.'">', $html);
    }

    public function test_a_code_hidden_by_the_poli_filter_releases_the_filter_instead_of_hiding_the_row(): void
    {
        $surgeryClinic = $this->createClinic(['name' => 'Poli Bedah', 'code' => 'BDH']);
        $surgeon = $this->createDoctor(['name' => 'Budi Hartono'], $surgeryClinic);
        $this->appointmentOn($this->sessionOn('2026-10-06', [], $surgeon), 'Pasien Bedah');
        $anak = $this->sessionOn('2026-10-06');
        $this->appointmentOn($anak, 'Pasien Anak', AppointmentStatus::Confirmed, ['booking_code' => 'UPH-ABCD-2345']);

        $html = $this->queue(['poli' => $surgeryClinic->slug, 'kode' => 'UPH-ABCD-2345'])->assertOk()->assertSee('Pasien Anak')->getContent();

        $this->assertSame($anak->id, $this->selectedSessionId($html));
    }

    public function test_a_code_for_another_date_says_so_with_a_link_and_changes_nothing_else(): void
    {
        $today = $this->sessionOn('2026-10-06');
        $this->appointmentOn($today, 'Pasien Hari Ini');
        $tomorrow = $this->sessionOn('2026-10-07');
        $this->appointmentOn($tomorrow, 'Pasien Besok', AppointmentStatus::Confirmed, ['booking_code' => 'UPH-ABCD-2345']);

        $html = $this->queue(['kode' => 'UPH-ABCD-2345'])
            ->assertOk()
            ->assertSee('data-scan-result="other-date">Pendaftaran ini untuk tanggal 07/10/2026, bukan tanggal yang sedang ditampilkan.', false)
            ->assertSee('href="'.e(route('admin.queue.index', ['tanggal' => '2026-10-07', 'sesi' => $tomorrow->id, 'kode' => 'UPH-ABCD-2345'])).'"', false)
            ->assertSee('Pasien Hari Ini')
            ->assertDontSee('Pasien Besok')
            ->getContent();

        $this->assertStringNotContainsString('table-warning', $html);
        $this->assertSame($today->id, $this->selectedSessionId($html));
    }

    /** @return array<string, array{string}> */
    public static function missingCodes(): array
    {
        return [
            'format sah, tidak ada' => ['UPH-ZZZZ-ZZZZ'],
            'huruf kecil dan spasi, tidak ada' => ['uph zzzz zzzz'],
            'format salah' => ['abc'],
            'terlalu pendek' => ['UPH-AB'],
            'karakter terlarang' => ['UPH-ABCD-EFG!'],
            'tag skrip pendek' => ['<script>'],
            'angka saja' => ['123456789012'],
        ];
    }

    #[DataProvider('missingCodes')]
    public function test_an_unknown_or_malformed_code_says_not_found(string $code): void
    {
        $this->appointmentOn($this->sessionOn('2026-10-06'), 'Pasien Hari Ini');

        $this->queue(['kode' => $code])
            ->assertOk()
            ->assertSee('Kode booking tidak ditemukan.')
            ->assertSee('role="alert" data-scan-result="missing"', false)
            ->assertDontSee('table-warning', false)
            ->assertSee('Pasien Hari Ini');
    }

    public function test_the_typed_code_is_escaped_in_the_search_box(): void
    {
        $this->appointmentOn($this->sessionOn('2026-10-06'), 'Pasien Hari Ini');

        $this->queue(['kode' => '<script>alert(1)</script>'])
            ->assertOk()
            ->assertSee('Kode booking tidak ditemukan.')
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    public function test_the_search_box_is_labelled_autofocused_and_has_no_autocomplete(): void
    {
        $this->sessionOn('2026-10-06');

        $html = $this->queue()->assertOk()->assertSee('Cari atau pindai kode booking')->getContent();

        $this->assertSame(1, preg_match('/<input[^>]*name="kode"[^>]*>/', $html, $input));
        $this->assertStringContainsString('autofocus', $input[0]);
        $this->assertStringContainsString('autocomplete="off"', $input[0]);
        $this->assertStringContainsString('id="filter-kode"', $input[0]);
        $this->assertStringContainsString('for="filter-kode"', $html);
        $this->assertStringContainsString('aria-live="polite"', $html);
    }

    // --- VAL-07 e E3: query liar -------------------------------------------------------------

    /** @return array<string, array{string, bool}> query mentah, apakah flash "Filter tidak valid" muncul */
    public static function hostileQueries(): array
    {
        return [
            'tanggal tidak ada' => ['tanggal=2099-13-40', true],
            'tanggal huruf' => ['tanggal=kemarin', true],
            'tanggal melewati jendela' => ['tanggal=2099-12-31', true],
            'tanggal larik' => ['tanggal[]=2026-10-06', true],
            'sesi huruf' => ['sesi=abc', true],
            'sesi larik' => ['sesi[]=1', true],
            'sesi nol' => ['sesi=0', true],
            'sesi sangat besar' => ['sesi=99999999999999999999', true],
            'poli larik' => ['poli[]=x', true],
            'poli tidak ada' => ['poli=tidak-ada', true],
            'dokter larik berkunci' => ['dokter[a]=b', true],
            'kode larik' => ['kode[]=x', true],
            'kode terlalu panjang' => ['kode='.str_repeat('UPH-ABCD-2345-', 8), true],
            'per halaman liar' => ['per_page=7', true],
            'halaman negatif' => ['page=-3', true],
            'q larik' => ['q[]=1', true],
            'status liar diabaikan diam-diam' => ['status=xyz', false],
            'kembali eksternal diabaikan diam-diam' => ['kembali=//evil.test', false],
        ];
    }

    #[DataProvider('hostileQueries')]
    public function test_hostile_query_values_are_ignored_without_a_server_error(string $query, bool $warns): void
    {
        $this->appointmentOn($this->sessionOn('2026-10-06'), 'Pasien Hari Ini');

        $response = $this->actingAs($this->officer)->get('/admin/antrean?'.$query)
            ->assertOk()
            ->assertSee('Pasien Hari Ini')
            ->assertDontSee('evil.test')
            ->assertDontSee('Kode booking tidak ditemukan.');

        if ($warns) {
            $response->assertSee('Filter tidak valid dan diabaikan.');
        } else {
            $response->assertDontSee('Filter tidak valid dan diabaikan.');
        }
    }

    public function test_all_hostile_values_at_once_still_render_the_default_queue(): void
    {
        $this->appointmentOn($this->sessionOn('2026-10-06'), 'Pasien Hari Ini');

        $this->actingAs($this->officer)
            ->get('/admin/antrean?tanggal=2099-13-40&sesi=abc&sesi[]=1&poli[]=x&dokter[a]=b&kode[]=x&per_page=999&page=-3&kembali=//evil.test&status[]=x&urut=-x')
            ->assertOk()
            ->assertSee('Filter tidak valid dan diabaikan.')
            ->assertSee('Pasien Hari Ini');
    }

    // --- EMP-03: kosong ----------------------------------------------------------------------

    public function test_a_date_without_sessions_shows_the_empty_state(): void
    {
        $this->queue()
            ->assertOk()
            ->assertSee('Tidak ada sesi praktik pada tanggal ini')
            ->assertDontSee('Terisi ')
            ->assertDontSee('data-label="Kode booking"', false);
    }

    public function test_a_session_without_appointments_shows_the_empty_state_and_an_empty_summary(): void
    {
        $this->sessionOn('2026-10-06');

        $this->queue()
            ->assertOk()
            ->assertSee('Belum ada pendaftaran pada sesi ini')
            ->assertSee('Terisi 0 dari 20')
            ->assertDontSee('data-label="Kode booking"', false)
            ->assertDontSee('data-no-show-banner', false);
    }

    // --- navigasi ----------------------------------------------------------------------------

    public function test_the_page_has_the_module_tabs_with_the_queue_tab_active(): void
    {
        $this->queue()
            ->assertOk()
            ->assertSee('aria-label="Tab modul"', false)
            ->assertSee('aria-current="page">Antrean Hari Ini</a>', false)
            ->assertSee('<h1', false)
            ->assertSee('Antrean Hari Ini');

        $labels = array_column(NavigationMenu::items('modules.appointments', $this->officer), 'label');
        $this->assertSame(['Pendaftaran', 'Antrean Hari Ini'], $labels);
        $this->assertSame([], NavigationMenu::items('modules.appointments', $this->createStaff('content-admin')));
    }

    public function test_the_sidebar_shows_the_queue_item_to_the_officer_only(): void
    {
        $labelsOf = function (User $user): array {
            $labels = [];

            foreach (NavigationMenu::items('admin', $user) as $entry) {
                foreach ($entry['items'] ?? [] as $item) {
                    $labels[] = $item['label'];
                }
            }

            return $labels;
        };

        $this->assertContains('Antrean Hari Ini', $labelsOf($this->officer));
        $this->assertNotContains('Antrean Hari Ini', $labelsOf($this->createStaff('content-admin')));
    }

    // --- SEK-15, E4 --------------------------------------------------------------------------

    public function test_patient_names_are_escaped_in_the_rows(): void
    {
        $this->appointmentOn($this->sessionOn('2026-10-06'), '<script>alert(1)</script>');

        $this->queue()
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    public function test_the_page_never_shows_the_bpjs_number_or_the_policy_number(): void
    {
        $session = $this->sessionOn('2026-10-06');
        $this->appointmentOn(
            $session,
            'Siti Rahmawati',
            AppointmentStatus::Confirmed,
            ['payment_type' => PaymentType::Bpjs, 'payment_reference' => '0009900000011', 'payment_reference_last4' => '0011'],
            ['bpjs_number' => '0009900000099'],
        );

        $this->queue()
            ->assertOk()
            ->assertSee('BPJS Kesehatan')
            ->assertDontSee('0009900000011')
            ->assertDontSee('0009900000099')
            ->assertDontSee('000990000');
    }
}
