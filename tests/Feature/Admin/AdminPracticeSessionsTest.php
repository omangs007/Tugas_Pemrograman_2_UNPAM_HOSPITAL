<?php

namespace Tests\Feature\Admin;

use App\Enums\AppointmentStatus;
use App\Enums\ExceptionType;
use App\Enums\SessionStatus;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\PatientProfile;
use App\Models\PracticeSession;
use App\Models\User;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InteractsWithMasterData;
use Tests\Support\InteractsWithSchedules;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

/**
 * Halaman #35, tab Sesi per Tanggal (T12): daftar tanpa data pasien (JDW-14, F3-R30), sesi tambahan manual
 * (JDW-13), kuota dan jam (JDW-07/15), status sesi, hapus (JDW-19 c-f, F3-R20), sesi lampau (F3-R27),
 * akses per peran (AKS 35, 35b, 35d), mode ketat.
 */
class AdminPracticeSessionsTest extends TestCase
{
    use InteractsWithMasterData;
    use InteractsWithSchedules;
    use InteractsWithStaff;
    use RefreshDatabase;

    private const PAST = 'Sesi yang sudah lewat tidak dapat diubah.';

    private Clinic $clinic;

    private Doctor $doctor;

    protected function setUp(): void
    {
        parent::setUp();

        // Senin, 5 Oktober 2026 pukul 07.00 WIB.
        $this->travelTo(Carbon::parse('2026-10-05 07:00:00'));
        $this->seed(SettingsSeeder::class);

        $this->clinic = $this->createClinic(['name' => 'Poliklinik Anak']);
        $this->doctor = $this->createDoctor(['name' => 'Raka Wirawan'], $this->clinic);
    }

    /**
     * Sesi tambahan manual (tanpa templat) dokter uji, pukul 09.00-12.00, kuota 20.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function manualSession(string $date = '2026-10-08', array $overrides = []): PracticeSession
    {
        return $this->createSession(array_merge([
            'doctor_schedule_id' => null,
            'doctor_id' => $this->doctor->id,
            'clinic_id' => $this->clinic->id,
            'session_date' => $date,
            'start_time' => '09:00:00',
            'end_time' => '12:00:00',
            'quota' => 20,
            'is_manually_modified' => false,
        ], $overrides));
    }

    /**
     * Sesi hasil templat (doctor_schedule_id terisi).
     *
     * @param  array<string, mixed>  $overrides
     */
    private function templateSession(string $date = '2026-10-12', array $overrides = []): PracticeSession
    {
        $schedule = $this->createSchedule([
            'doctor_id' => $this->doctor->id,
            'clinic_id' => $this->clinic->id,
            'day_of_week' => 1,
            'start_time' => '09:00:00',
            'end_time' => '12:00:00',
            'quota' => 20,
            'valid_from' => '2026-10-05',
            'valid_until' => null,
        ]);

        return $this->manualSession($date, array_merge(['doctor_schedule_id' => $schedule->id], $overrides));
    }

    /**
     * Sesi yang sudah berakhir pada 5 Okt 2026 pukul 06.00 (sekarang 07.00).
     *
     * @param  array<string, mixed>  $overrides
     */
    private function endedSession(array $overrides = []): PracticeSession
    {
        return $this->manualSession('2026-10-05', array_merge(['start_time' => '04:00:00', 'end_time' => '06:00:00'], $overrides));
    }

    /**
     * @return array<string, string>
     */
    private function createPayload(array $overrides = []): array
    {
        return array_merge([
            'doctor_id' => (string) $this->doctor->id,
            'clinic_id' => (string) $this->clinic->id,
            'session_date' => '2026-10-08',
            'start_time' => '13:00',
            'end_time' => '15:00',
            'quota' => '10',
        ], $overrides);
    }

    // --- Akses (AKS 35, 35b, 35d) ---------------------------------------------------------------------

    /**
     * @return array<string, array{string, int, int}>
     */
    public static function staffRoles(): array
    {
        return [
            'super admin' => ['super-admin', 200, 200],
            'admin konten' => ['content-admin', 200, 200],
            'petugas pendaftaran' => ['registration-officer', 200, 403],
        ];
    }

    #[DataProvider('staffRoles')]
    public function test_access_per_staff_role(string $role, int $indexStatus, int $manageStatus): void
    {
        $session = $this->manualSession();
        $this->actingAs($this->createStaff($role));

        $this->get(route('admin.sessions.index'))->assertStatus($indexStatus);
        $this->get(route('admin.sessions.create'))->assertStatus($manageStatus);
        $this->get(route('admin.sessions.edit', $session))->assertStatus($manageStatus);
    }

    /**
     * @return array<string, array{string, int}>
     */
    public static function destroyRoles(): array
    {
        return [
            'super admin' => ['super-admin', 302],
            'admin konten' => ['content-admin', 302],
            'petugas pendaftaran' => ['registration-officer', 403],
        ];
    }

    #[DataProvider('destroyRoles')]
    public function test_aks_35d_delete_of_a_manual_session_per_staff_role(string $role, int $status): void
    {
        $session = $this->manualSession();

        $this->actingAs($this->createStaff($role))
            ->delete(route('admin.sessions.destroy', $session))
            ->assertStatus($status);

        $this->assertSame($status === 302 ? 0 : 1, PracticeSession::query()->whereKey($session->id)->count());
    }

    public function test_patient_gets_403_on_every_session_route(): void
    {
        $session = $this->manualSession();
        $this->actingAs(User::factory()->patient()->create());

        $this->get(route('admin.sessions.index'))->assertForbidden();
        $this->post(route('admin.sessions.store'), $this->createPayload())->assertForbidden();
        $this->patch(route('admin.sessions.status', $session), ['status' => 'closed'])->assertForbidden();
        $this->delete(route('admin.sessions.destroy', $session))->assertForbidden();
    }

    public function test_guest_is_sent_to_admin_login(): void
    {
        $session = $this->manualSession();

        $this->get(route('admin.sessions.index'))->assertRedirect(route('admin.login'));
        $this->delete(route('admin.sessions.destroy', $session))->assertRedirect(route('admin.login'));
    }

    public function test_urls_use_the_indonesian_segments_and_there_is_no_show_route(): void
    {
        $session = $this->manualSession();

        $this->assertSame(url('/admin/jadwal/sesi'), route('admin.sessions.index'));
        $this->assertSame(url('/admin/jadwal/sesi/buat'), route('admin.sessions.create'));
        $this->assertSame(url('/admin/jadwal/sesi/'.$session->id.'/ubah'), route('admin.sessions.edit', $session));
        $this->assertSame(url('/admin/jadwal/sesi/'.$session->id.'/status'), route('admin.sessions.status', $session));
        $this->assertSame(url('/admin/jadwal/sesi/'.$session->id), route('admin.sessions.destroy', $session));
        $this->assertFalse(Route::has('admin.sessions.show'));
    }

    public function test_non_numeric_ids_are_a_404(): void
    {
        $this->actingAs($this->createStaff('content-admin'));

        $this->get('/admin/jadwal/sesi/abc/ubah')->assertNotFound();
        $this->patch('/admin/jadwal/sesi/abc/status', ['status' => 'closed'])->assertNotFound();
        $this->delete('/admin/jadwal/sesi/abc')->assertNotFound();
    }

    // --- Daftar -----------------------------------------------------------------------------------------

    public function test_index_renders_many_rows_in_strict_mode_with_status_badges_and_counts(): void
    {
        $other = $this->createDoctor(['name' => 'Sinta Maharani']);
        $this->manualSession('2026-10-07', ['booked_count' => 3, 'last_queue_number' => 3]);
        $this->templateSession('2026-10-12', ['status' => SessionStatus::Closed]);
        $this->manualSession('2026-10-13', ['doctor_id' => $other->id, 'clinic_id' => $other->clinic_id, 'status' => SessionStatus::Cancelled, 'status_reason' => 'Cuti dokter']);
        $this->manualSession('2026-10-14', ['status' => SessionStatus::NeedsReview, 'pending_change' => ['source' => 'manual_time', 'new_start_time' => '10:00', 'new_end_time' => '13:00']]);
        $this->manualSession('2026-10-25');

        $html = $this->actingAs($this->createStaff('content-admin'))
            ->get(route('admin.sessions.index'))
            ->assertOk()
            ->assertSee('Jadwal Praktik')
            ->assertSee('Sesi per Tanggal')
            ->assertSee('aria-current="page"', false)
            ->assertSee('data-label="Terisi">3/20</td>', false)
            ->assertSee('09.00–12.00')
            ->assertSee('Senin, 12 Okt 2026')
            ->assertSee('Dibuka')
            ->assertSee('Ditutup')
            ->assertSee('Dibatalkan')
            ->assertSee('Cuti dokter')
            ->assertSee('Perlu tindak lanjut')
            ->assertSee($other->display_name)
            ->assertSee('Poliklinik Anak')
            ->assertSee('href="'.route('admin.sessions.create').'"', false)
            ->assertSee('Tambah Sesi')
            ->assertDontSee('Minggu, 25 Okt 2026', false)
            ->getContent();

        // Lencana "Sesi tambahan" hanya untuk tiga sesi manual dalam rentang (bukan sesi templat).
        $this->assertSame(3, substr_count($html, 'badge text-bg-light border">Sesi tambahan'));
        $this->assertSame(4, substr_count($html, 'data-label="Terisi"'));
    }

    public function test_index_default_range_is_today_through_thirteen_days_and_dari_sampai_widen_it(): void
    {
        $this->manualSession('2026-10-18');
        $this->manualSession('2026-10-19');
        $admin = $this->createStaff('content-admin');

        $count = fn (array $query): int => substr_count(
            $this->actingAs($admin)->get(route('admin.sessions.index', $query))->assertOk()->getContent(),
            'data-label="Terisi"',
        );

        $this->assertSame(1, $count([]));
        $this->assertSame(2, $count(['sampai' => '2026-10-19']));
        $this->assertSame(1, $count(['dari' => '2026-10-19', 'sampai' => '2026-10-19']));
    }

    public function test_index_filters_by_doctor_clinic_and_status_and_ignores_invalid_values(): void
    {
        $otherClinic = $this->createClinic(['name' => 'Poliklinik Bedah']);
        $other = $this->createDoctor(['name' => 'Sinta Maharani'], $otherClinic);
        $this->manualSession('2026-10-07');
        $this->manualSession('2026-10-08', ['status' => SessionStatus::Closed]);
        $this->manualSession('2026-10-09', ['doctor_id' => $other->id, 'clinic_id' => $otherClinic->id]);
        $admin = $this->createStaff('content-admin');

        $rows = fn (array $query): int => substr_count(
            $this->actingAs($admin)->get(route('admin.sessions.index', $query))->assertOk()->getContent(),
            'data-label="Terisi"',
        );

        $this->assertSame(3, $rows([]));
        $this->assertSame(1, $rows(['dokter' => $other->slug]));
        $this->assertSame(1, $rows(['poli' => $otherClinic->slug]));
        $this->assertSame(1, $rows(['status' => 'closed']));
        $this->assertSame(3, $rows(['dokter' => 'tidak-ada', 'poli' => 'tidak-ada', 'status' => 'zzz', 'dari' => 'kemarin']));

        $this->get(route('admin.sessions.index', ['status' => 'zzz']))
            ->assertOk()
            ->assertSee('Filter tidak valid dan diabaikan.');
    }

    public function test_index_with_array_valued_filters_is_200(): void
    {
        $this->manualSession('2026-10-07');

        $this->actingAs($this->createStaff('content-admin'))
            ->get(route('admin.sessions.index', [
                'status' => ['x'],
                'dokter' => ['x'],
                'poli' => ['x'],
                'dari' => ['x'],
                'sampai' => ['x'],
            ]))
            ->assertOk();
    }

    public function test_index_empty_state(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->get(route('admin.sessions.index'))
            ->assertOk()
            ->assertSee('Tidak ada sesi pada rentang ini');
    }

    public function test_banner_is_shown_on_the_sessions_tab_too(): void
    {
        $this->manualSession('2026-10-08', ['status' => SessionStatus::NeedsReview, 'pending_change' => ['source' => 'exception', 'exception_id' => 1]]);

        $this->actingAs($this->createStaff('content-admin'))
            ->get(route('admin.sessions.index'))
            ->assertOk()
            ->assertSee('1 sesi perlu ditindaklanjuti petugas pendaftaran.');
    }

    // --- JDW-14 dan F3-R30: tanpa data pasien -------------------------------------------------------------

    public function test_jdw_14_content_admin_html_never_contains_patient_name_phone_or_booking_code(): void
    {
        $session = $this->manualSession('2026-10-08', ['quota' => 20]);
        $second = $this->manualSession('2026-10-09');
        $patient = PatientProfile::factory()->create([
            'full_name' => 'Zulkarnain Rahasiawan',
            'phone' => '081377770042',
        ]);
        $appointment = $this->bookAppointment($session, AppointmentStatus::Confirmed, ['patient_profile_id' => $patient->id]);
        $this->bookAppointment($second, AppointmentStatus::Pending);
        $admin = $this->createStaff('content-admin', ['name' => 'Admin Konten Uji']);

        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        $html = $this->actingAs($admin)->get(route('admin.sessions.index'))
            ->assertOk()
            ->assertSee('data-label="Terisi">1/20</td>', false)
            ->getContent();

        $this->assertStringNotContainsString('Zulkarnain', $html);
        $this->assertStringNotContainsString('Rahasiawan', $html);
        $this->assertStringNotContainsString('081377770042', $html);
        $this->assertStringNotContainsString($appointment->booking_code, $html);

        foreach ($queries as $sql) {
            $this->assertStringNotContainsString('patient_profiles', $sql, 'Daftar sesi tidak boleh memuat profil pasien.');
            $this->assertStringNotContainsString('booking_code', $sql);
            $this->assertStringNotContainsString('complaint', $sql);
        }
    }

    public function test_jdw_14_edit_form_shows_only_the_count_of_registrations(): void
    {
        $session = $this->manualSession('2026-10-08');
        $patient = PatientProfile::factory()->create(['full_name' => 'Zulkarnain Rahasiawan', 'phone' => '081377770042']);
        $this->bookAppointment($session, AppointmentStatus::Confirmed, ['patient_profile_id' => $patient->id]);

        $html = $this->actingAs($this->createStaff('content-admin', ['name' => 'Admin Konten Uji']))
            ->get(route('admin.sessions.edit', $session))
            ->assertOk()
            ->assertSee('1 dari 20')
            ->getContent();

        $this->assertStringNotContainsString('Zulkarnain', $html);
        $this->assertStringNotContainsString('081377770042', $html);
    }

    // --- Aksi per baris (F3-R20, F3-R27) -----------------------------------------------------------------

    public function test_f3_r20_delete_button_only_for_manual_sessions_without_any_appointment(): void
    {
        $deletable = $this->manualSession('2026-10-07');
        $withAppointment = $this->manualSession('2026-10-08');
        $this->bookAppointment($withAppointment, AppointmentStatus::Cancelled);
        $fromTemplate = $this->templateSession('2026-10-12');
        $ended = $this->endedSession();

        $this->actingAs($this->createStaff('content-admin'))
            ->get(route('admin.sessions.index'))
            ->assertOk()
            ->assertSee('data-confirm-action="'.route('admin.sessions.destroy', $deletable).'"', false)
            ->assertDontSee('data-confirm-action="'.route('admin.sessions.destroy', $withAppointment).'"', false)
            ->assertDontSee('data-confirm-action="'.route('admin.sessions.destroy', $fromTemplate).'"', false)
            ->assertDontSee('data-confirm-action="'.route('admin.sessions.destroy', $ended).'"', false)
            ->assertSee('Sesi dari templat tidak dapat dihapus: tutup atau batalkan sesi.');
    }

    public function test_status_buttons_follow_the_session_status_and_ended_sessions_have_no_actions(): void
    {
        $open = $this->manualSession('2026-10-07');
        $closed = $this->manualSession('2026-10-08', ['status' => SessionStatus::Closed]);
        $reopenable = $this->manualSession('2026-10-09', ['status' => SessionStatus::Cancelled, 'status_reason' => 'Dokter sakit']);
        $exception = $this->createException(['type' => ExceptionType::DoctorLeave, 'doctor_id' => $this->doctor->id, 'start_date' => '2026-10-10', 'end_date' => '2026-10-10']);
        $byException = $this->manualSession('2026-10-10', ['status' => SessionStatus::Cancelled, 'schedule_exception_id' => $exception->id]);
        $review = $this->manualSession('2026-10-11', ['status' => SessionStatus::NeedsReview, 'pending_change' => ['source' => 'exception', 'exception_id' => $exception->id]]);
        $ended = $this->endedSession();

        $html = $this->actingAs($this->createStaff('super-admin'))
            ->get(route('admin.sessions.index'))
            ->assertOk()
            ->assertSee('Menunggu petugas pendaftaran')
            ->assertSee('Sudah lewat')
            ->assertSee('data-bs-target="#modal-sesi-tutup"', false)
            ->assertSee('data-bs-target="#modal-sesi-buka"', false)
            ->assertSee('data-bs-target="#modal-sesi-batalkan"', false)
            ->getContent();

        $this->assertSame(1, substr_count($html, 'data-bs-target="#modal-sesi-tutup" data-confirm-action="'.route('admin.sessions.status', $open).'"'));
        $this->assertSame(1, substr_count($html, 'data-bs-target="#modal-sesi-buka" data-confirm-action="'.route('admin.sessions.status', $closed).'"'));
        $this->assertSame(1, substr_count($html, 'data-bs-target="#modal-sesi-buka" data-confirm-action="'.route('admin.sessions.status', $reopenable).'"'));
        $this->assertSame(0, substr_count($html, 'data-confirm-action="'.route('admin.sessions.status', $byException).'"'), 'Sesi yang dibatalkan pengecualian tidak dibuka dari sini.');
        $this->assertSame(0, substr_count($html, 'data-confirm-action="'.route('admin.sessions.status', $review).'"'));
        $this->assertSame(0, substr_count($html, 'data-confirm-action="'.route('admin.sessions.status', $ended).'"'));
        $this->assertStringNotContainsString(route('admin.sessions.edit', $ended), $html);
    }

    public function test_registration_officer_sees_the_list_without_any_manage_action(): void
    {
        $session = $this->manualSession('2026-10-07');

        $this->actingAs($this->createStaff('registration-officer'))
            ->get(route('admin.sessions.index'))
            ->assertOk()
            ->assertSee('data-label="Terisi"', false)
            ->assertDontSee('Tambah Sesi')
            ->assertDontSee(route('admin.sessions.edit', $session), false)
            ->assertDontSee('data-confirm-action', false)
            ->assertDontSee('modal-sesi-tutup', false);
    }

    // --- Tambah sesi manual (JDW-13) -----------------------------------------------------------------------

    public function test_create_form_renders_fields_with_active_doctors_and_bookable_clinics_only(): void
    {
        $this->createDoctor(['name' => 'Dokter Nonaktif', 'status' => 'inactive']);
        $this->createDoctor(['name' => 'Dokter Arsip', 'status' => 'archived', 'archived_at' => now()->subDay()]);
        $this->createClinic(['name' => 'Layanan Penunjang', 'is_bookable' => false]);

        $this->actingAs($this->createStaff('content-admin'))
            ->get(route('admin.sessions.create'))
            ->assertOk()
            ->assertSee('Tambah Sesi')
            ->assertSee('name="doctor_id"', false)
            ->assertSee('name="clinic_id"', false)
            ->assertSee('name="session_date"', false)
            ->assertSee('name="start_time"', false)
            ->assertSee('name="end_time"', false)
            ->assertSee('name="quota"', false)
            ->assertSee('Raka Wirawan')
            ->assertSee('Poliklinik Anak')
            ->assertDontSee('Dokter Nonaktif')
            ->assertDontSee('Dokter Arsip')
            ->assertDontSee('Layanan Penunjang')
            ->assertSee('action="'.route('admin.sessions.store').'"', false);
    }

    public function test_jdw_13_a_manual_session_is_created_without_a_template_and_can_be_repeated(): void
    {
        $admin = $this->createStaff('content-admin');

        $this->actingAs($admin)
            ->post(route('admin.sessions.store'), $this->createPayload())
            ->assertRedirect(route('admin.sessions.index', ['dari' => '2026-10-08', 'sampai' => '2026-10-08']))
            ->assertSessionHas('success', 'Sesi praktik berhasil ditambahkan.');

        $this->post(route('admin.sessions.store'), $this->createPayload(['start_time' => '16:00', 'end_time' => '18:00']))
            ->assertSessionHasNoErrors();

        $this->assertSame(2, PracticeSession::query()->whereNull('doctor_schedule_id')->count());

        $session = PracticeSession::query()->orderBy('id')->first();
        $this->assertSame($this->doctor->id, $session->doctor_id);
        $this->assertSame($this->clinic->id, $session->clinic_id);
        $this->assertSame('2026-10-08', $session->session_date->toDateString());
        $this->assertSame(10, $session->quota);
        $this->assertSame(0, $session->booked_count);
        $this->assertSame(SessionStatus::Open, $session->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'session.created']);
    }

    /**
     * @return array<string, array{array<string, mixed>, string, string}>
     */
    public static function invalidCreateInputs(): array
    {
        return [
            'tanggal di masa lalu' => [['session_date' => '2026-10-04'], 'session_date', 'Tanggal sesi tidak boleh di masa lalu.'],
            'tanggal lebih dari 365 hari' => [['session_date' => '2027-10-06'], 'session_date', 'Tanggal sesi maksimal 365 hari ke depan.'],
            'dokter kosong' => [['doctor_id' => ''], 'doctor_id', 'Dokter wajib dipilih.'],
            'jam selesai tidak setelah mulai' => [['start_time' => '15:00', 'end_time' => '14:00'], 'end_time', 'Jam selesai harus setelah jam mulai.'],
            'kuota nol' => [['quota' => '0'], 'quota', 'Kuota minimal 1 dan maksimal 200.'],
            'kuota 201' => [['quota' => '201'], 'quota', 'Kuota minimal 1 dan maksimal 200.'],
        ];
    }

    /**
     * @param  array<string, mixed>  $override
     */
    #[DataProvider('invalidCreateInputs')]
    public function test_store_rejects_invalid_input_with_indonesian_messages(array $override, string $field, string $message): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->from(route('admin.sessions.create'))
            ->post(route('admin.sessions.store'), $this->createPayload($override))
            ->assertRedirect(route('admin.sessions.create'))
            ->assertSessionHasErrors([$field => $message]);

        $this->assertSame(0, PracticeSession::query()->count());
    }

    public function test_store_rejects_an_inactive_doctor_an_overlap_and_a_date_inside_an_exception(): void
    {
        $inactive = $this->createDoctor(['status' => 'inactive'], $this->clinic);
        $admin = $this->createStaff('super-admin');
        $this->actingAs($admin)->from(route('admin.sessions.create'));

        $this->post(route('admin.sessions.store'), $this->createPayload(['doctor_id' => (string) $inactive->id]))
            ->assertSessionHasErrors(['doctor_id' => 'Dokter yang dipilih tidak tersedia.']);

        $this->manualSession('2026-10-08', ['start_time' => '13:00:00', 'end_time' => '16:00:00']);
        $this->post(route('admin.sessions.store'), $this->createPayload(['start_time' => '15:00', 'end_time' => '17:00']))
            ->assertSessionHasErrors(['start_time' => 'Jam sesi bertabrakan dengan sesi lain milik dokter ini pada tanggal tersebut.']);

        $this->createException(['type' => ExceptionType::DoctorLeave, 'doctor_id' => $this->doctor->id, 'start_date' => '2026-10-20', 'end_date' => '2026-10-21', 'reason' => 'Cuti tahunan']);
        $this->post(route('admin.sessions.store'), $this->createPayload(['session_date' => '2026-10-20']))
            ->assertSessionHasErrors(['session_date' => 'Tanggal ini berada dalam pengecualian jadwal (Cuti tahunan). Hapus atau ubah pengecualian terlebih dahulu.']);

        $this->assertSame(1, PracticeSession::query()->count(), 'Hanya sesi awal yang ada.');
    }

    public function test_registration_officer_cannot_store(): void
    {
        $this->actingAs($this->createStaff('registration-officer'))
            ->post(route('admin.sessions.store'), $this->createPayload())
            ->assertForbidden();

        $this->assertSame(0, PracticeSession::query()->count());
    }

    // --- Ubah sesi (JDW-07, JDW-15, F3-R27) -----------------------------------------------------------------

    public function test_edit_form_shows_current_values_and_read_only_identity(): void
    {
        $session = $this->manualSession('2026-10-08', ['quota' => 25]);

        $this->actingAs($this->createStaff('super-admin'))
            ->get(route('admin.sessions.edit', $session))
            ->assertOk()
            ->assertSee('Ubah Sesi')
            ->assertSee('Raka Wirawan')
            ->assertSee('Poliklinik Anak')
            ->assertSee('Kamis, 8 Oktober 2026')
            ->assertSee('value="09:00"', false)
            ->assertSee('value="12:00"', false)
            ->assertSee('value="25"', false)
            ->assertDontSee('name="doctor_id"', false)
            ->assertDontSee('name="session_date"', false)
            ->assertSee('action="'.route('admin.sessions.update', $session).'"', false)
            ->assertSee('name="_method" value="PUT"', false);
    }

    public function test_f3_r27_edit_of_an_ended_session_is_redirected_with_an_error(): void
    {
        $ended = $this->endedSession();

        $this->actingAs($this->createStaff('super-admin'))
            ->get(route('admin.sessions.edit', $ended))
            ->assertRedirect(route('admin.sessions.index'))
            ->assertSessionHas('error', self::PAST);
    }

    public function test_jdw_07_quota_below_booked_count_is_rejected_and_equal_is_accepted(): void
    {
        $session = $this->manualSession('2026-10-08', ['quota' => 10, 'booked_count' => 5, 'last_queue_number' => 5]);
        $payload = ['start_time' => '09:00', 'end_time' => '12:00'];
        $admin = $this->createStaff('content-admin');

        $this->actingAs($admin)
            ->from(route('admin.sessions.edit', $session))
            ->put(route('admin.sessions.update', $session), $payload + ['quota' => '4'])
            ->assertRedirect(route('admin.sessions.edit', $session))
            ->assertSessionHasErrors(['quota' => 'Kuota tidak boleh kurang dari jumlah pendaftaran yang sudah ada (5).']);
        $this->assertSame(10, $session->fresh()->quota);

        $this->put(route('admin.sessions.update', $session), $payload + ['quota' => '5'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.sessions.index', ['dari' => '2026-10-08', 'sampai' => '2026-10-08']))
            ->assertSessionHas('success', 'Sesi praktik berhasil diperbarui.');

        $session->refresh();
        $this->assertSame(5, $session->quota);
        $this->assertTrue($session->is_manually_modified);
        $this->assertDatabaseHas('audit_logs', ['action' => 'session.updated']);
    }

    public function test_jdw_15_time_change_applies_directly_without_active_appointments(): void
    {
        $session = $this->manualSession('2026-10-08');
        $this->bookAppointment($session, AppointmentStatus::Cancelled);

        $this->actingAs($this->createStaff('content-admin'))
            ->put(route('admin.sessions.update', $session), ['start_time' => '10:00', 'end_time' => '13:00', 'quota' => '20'])
            ->assertSessionHasNoErrors()
            ->assertSessionMissing('warning');

        $session->refresh();
        $this->assertSame(SessionStatus::Open, $session->status);
        $this->assertSame('10:00:00', (string) $session->start_time);
        $this->assertSame('13:00:00', (string) $session->end_time);
        $this->assertTrue($session->is_manually_modified);
    }

    public function test_jdw_15_time_change_with_an_active_appointment_goes_to_review_as_manual_time(): void
    {
        $session = $this->manualSession('2026-10-08');
        $appointment = $this->bookAppointment($session, AppointmentStatus::Confirmed);

        $this->actingAs($this->createStaff('content-admin'))
            ->put(route('admin.sessions.update', $session), ['start_time' => '10:00', 'end_time' => '13:00', 'quota' => '25'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('warning', 'Sesi memiliki 1 pendaftaran aktif. Perubahan dikirim ke layar Sesi terdampak untuk diselesaikan petugas pendaftaran.');

        $session->refresh();
        $this->assertSame(SessionStatus::NeedsReview, $session->status);
        $this->assertEquals(['source' => 'manual_time', 'new_start_time' => '10:00', 'new_end_time' => '13:00'], $session->pending_change);
        $this->assertSame('09:00:00', (string) $session->start_time, 'Jam lama tetap berlaku sampai ditindaklanjuti.');
        $this->assertSame(25, $session->quota, 'Kuota langsung berlaku.');
        $this->assertTrue($session->is_manually_modified);
        $this->assertSame(AppointmentStatus::Confirmed, $appointment->fresh()->status);
    }

    public function test_f3_r27_an_ended_session_cannot_be_updated_even_by_super_admin(): void
    {
        $ended = $this->endedSession();

        $this->actingAs($this->createStaff('super-admin'))
            ->from(route('admin.sessions.index'))
            ->put(route('admin.sessions.update', $ended), ['start_time' => '04:00', 'end_time' => '06:00', 'quota' => '30'])
            ->assertSessionHasErrors(['start_time' => self::PAST]);

        $this->assertSame(20, $ended->fresh()->quota);
    }

    public function test_registration_officer_cannot_update(): void
    {
        $session = $this->manualSession();

        $this->actingAs($this->createStaff('registration-officer'))
            ->put(route('admin.sessions.update', $session), ['start_time' => '09:00', 'end_time' => '12:00', 'quota' => '99'])
            ->assertForbidden();

        $this->assertSame(20, $session->fresh()->quota);
    }

    // --- Status sesi -----------------------------------------------------------------------------------------

    public function test_session_can_be_closed_and_reopened(): void
    {
        $session = $this->templateSession('2026-10-12');
        $this->bookAppointment($session, AppointmentStatus::Confirmed);
        $admin = $this->createStaff('content-admin');

        $this->actingAs($admin)
            ->patch(route('admin.sessions.status', $session), ['status' => 'closed'])
            ->assertRedirect(route('admin.sessions.index'))
            ->assertSessionHas('success', 'Sesi ditutup untuk pendaftaran baru.');
        $this->assertSame(SessionStatus::Closed, $session->fresh()->status);

        $this->patch(route('admin.sessions.status', $session), ['status' => 'open'])
            ->assertSessionHas('success', 'Sesi dibuka kembali.');
        $this->assertSame(SessionStatus::Open, $session->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'session.status_changed']);
    }

    public function test_cancelling_requires_a_reason_of_at_least_five_characters(): void
    {
        $session = $this->manualSession('2026-10-08');
        $this->actingAs($this->createStaff('content-admin'));

        $this->patch(route('admin.sessions.status', $session), ['status' => 'cancelled'])
            ->assertSessionHasErrors(['reason' => 'Alasan pembatalan sesi wajib diisi.'])
            ->assertSessionHas('error', 'Alasan pembatalan sesi wajib diisi.');

        $this->patch(route('admin.sessions.status', $session), ['status' => 'cancelled', 'reason' => 'abcd'])
            ->assertSessionHasErrors(['reason' => 'Alasan minimal 5 karakter.']);

        $this->assertSame(SessionStatus::Open, $session->fresh()->status);
    }

    public function test_jdw_15_cancelling_a_session_without_active_appointments_cancels_it_directly(): void
    {
        $session = $this->manualSession('2026-10-08');

        $this->actingAs($this->createStaff('content-admin'))
            ->patch(route('admin.sessions.status', $session), ['status' => 'cancelled', 'reason' => 'Dokter sakit mendadak'])
            ->assertSessionHas('success', 'Sesi dibatalkan.');

        $session->refresh();
        $this->assertSame(SessionStatus::Cancelled, $session->status);
        $this->assertSame('Dokter sakit mendadak', $session->status_reason);
        $this->assertTrue($session->is_manually_modified);
    }

    public function test_jdw_15_cancelling_a_session_with_active_appointments_goes_to_review_as_manual_cancel(): void
    {
        $session = $this->manualSession('2026-10-08');
        $appointment = $this->bookAppointment($session, AppointmentStatus::Confirmed);

        $this->actingAs($this->createStaff('super-admin'))
            ->patch(route('admin.sessions.status', $session), ['status' => 'cancelled', 'reason' => 'Dokter sakit mendadak'])
            ->assertSessionHas('warning', 'Sesi memiliki 1 pendaftaran aktif. Perubahan dikirim ke layar Sesi terdampak untuk diselesaikan petugas pendaftaran.');

        $session->refresh();
        $this->assertSame(SessionStatus::NeedsReview, $session->status);
        $this->assertSame('manual_cancel', $session->pending_change['source']);
        $this->assertSame(AppointmentStatus::Confirmed, $appointment->fresh()->status, 'Appointment tidak dibatalkan otomatis.');
    }

    public function test_a_cancelled_session_covered_by_an_exception_cannot_be_reopened(): void
    {
        $exception = $this->createException(['type' => ExceptionType::DoctorLeave, 'doctor_id' => $this->doctor->id, 'start_date' => '2026-10-08', 'end_date' => '2026-10-08']);
        $session = $this->manualSession('2026-10-08', ['status' => SessionStatus::Cancelled, 'schedule_exception_id' => $exception->id, 'status_reason' => 'Cuti']);

        $this->actingAs($this->createStaff('super-admin'))
            ->patch(route('admin.sessions.status', $session), ['status' => 'open'])
            ->assertSessionHasErrors();

        $this->assertSame(SessionStatus::Cancelled, $session->fresh()->status);
    }

    public function test_a_manually_cancelled_session_can_be_reopened(): void
    {
        $session = $this->manualSession('2026-10-08', ['status' => SessionStatus::Cancelled, 'status_reason' => 'Dokter sakit']);

        $this->actingAs($this->createStaff('content-admin'))
            ->patch(route('admin.sessions.status', $session), ['status' => 'open'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Sesi dibuka kembali.');

        $this->assertSame(SessionStatus::Open, $session->fresh()->status);
    }

    public function test_f3_r27_status_of_an_ended_session_cannot_be_changed_even_by_super_admin(): void
    {
        $ended = $this->endedSession();

        $this->actingAs($this->createStaff('super-admin'))
            ->patch(route('admin.sessions.status', $ended), ['status' => 'closed'])
            ->assertSessionHasErrors(['status' => self::PAST])
            ->assertSessionHas('error', self::PAST);

        $this->assertSame(SessionStatus::Open, $ended->fresh()->status);
    }

    public function test_registration_officer_cannot_change_status(): void
    {
        $session = $this->manualSession('2026-10-08');

        $this->actingAs($this->createStaff('registration-officer'))
            ->patch(route('admin.sessions.status', $session), ['status' => 'closed'])
            ->assertForbidden();

        $this->assertSame(SessionStatus::Open, $session->fresh()->status);
    }

    // --- Hapus (JDW-19 c, d, e, f) --------------------------------------------------------------------------

    public function test_jdw_19c_a_manual_session_without_appointments_is_deleted_and_audited(): void
    {
        $session = $this->manualSession('2026-10-08');

        $this->actingAs($this->createStaff('content-admin'))
            ->delete(route('admin.sessions.destroy', $session))
            ->assertRedirect(route('admin.sessions.index'))
            ->assertSessionHas('success', 'Sesi praktik berhasil dihapus.');

        $this->assertModelMissing($session);
        $this->assertDatabaseHas('audit_logs', ['action' => 'session.deleted']);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function managers(): array
    {
        return ['super admin' => ['super-admin'], 'admin konten' => ['content-admin']];
    }

    #[DataProvider('managers')]
    public function test_jdw_19d_a_manual_session_with_an_appointment_is_not_deleted_even_by_super_admin(string $role): void
    {
        $session = $this->manualSession('2026-10-08');
        $this->bookAppointment($session, AppointmentStatus::Cancelled);

        $this->actingAs($this->createStaff($role))
            ->delete(route('admin.sessions.destroy', $session))
            ->assertSessionHas('error', 'Sesi ini sudah memiliki pendaftaran. Tutup atau batalkan sesi ini.');

        $this->assertModelExists($session);
    }

    #[DataProvider('managers')]
    public function test_jdw_19e_a_session_from_a_template_is_not_deleted_even_by_super_admin(string $role): void
    {
        $session = $this->templateSession('2026-10-12');

        $this->actingAs($this->createStaff($role))
            ->delete(route('admin.sessions.destroy', $session))
            ->assertSessionHas('error', 'Sesi dari templat tidak dapat dihapus. Tutup atau batalkan sesi ini.');

        $this->assertModelExists($session);
    }

    public function test_jdw_19f_registration_officer_cannot_delete_a_session(): void
    {
        $session = $this->manualSession('2026-10-08');

        $this->actingAs($this->createStaff('registration-officer'))
            ->delete(route('admin.sessions.destroy', $session))
            ->assertForbidden();

        $this->assertModelExists($session);
    }

    public function test_f3_r27_an_ended_manual_session_is_not_deleted(): void
    {
        $ended = $this->endedSession();

        $this->actingAs($this->createStaff('super-admin'))
            ->delete(route('admin.sessions.destroy', $ended))
            ->assertSessionHas('error', self::PAST);

        $this->assertModelExists($ended);
    }
}
