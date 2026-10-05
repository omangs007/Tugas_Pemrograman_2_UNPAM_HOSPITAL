<?php

namespace Tests\Feature\Admin;

use App\Enums\AppointmentStatus;
use App\Enums\SessionStatus;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\PatientProfile;
use App\Models\PracticeSession;
use App\Models\User;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InteractsWithMasterData;
use Tests\Support\InteractsWithSchedules;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

/**
 * Halaman #35, tab Templat Mingguan (T10): akses per peran (AKS 35/35b), CRUD dengan flash laporan dampak
 * (F3-R23), JDW-06/08/14/19 lewat HTTP, banner sesi perlu tindak lanjut (F3-R19), mode ketat.
 */
class AdminScheduleTemplatesTest extends TestCase
{
    use InteractsWithMasterData;
    use InteractsWithSchedules;
    use InteractsWithStaff;
    use RefreshDatabase;

    private Clinic $clinic;

    private Doctor $doctor;

    protected function setUp(): void
    {
        parent::setUp();

        // Senin, 5 Oktober 2026 pukul 07.00 WIB; jendela pendaftaran 30 hari (SettingsSeeder).
        $this->travelTo(Carbon::parse('2026-10-05 07:00:00'));
        $this->seed(SettingsSeeder::class);

        $this->clinic = $this->createClinic(['name' => 'Poliklinik Anak']);
        $this->doctor = $this->createDoctor(['name' => 'Raka Wirawan'], $this->clinic);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'doctor_id' => (string) $this->doctor->id,
            'clinic_id' => (string) $this->clinic->id,
            'day_of_week' => '1',
            'start_time' => '09:00',
            'end_time' => '12:00',
            'quota' => '20',
            'valid_from' => '2026-10-05',
            'valid_until' => '',
            'is_active' => '1',
            'notes' => '',
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function template(array $overrides = []): DoctorSchedule
    {
        return $this->createSchedule(array_merge([
            'doctor_id' => $this->doctor->id,
            'clinic_id' => $this->clinic->id,
            'day_of_week' => 1,
            'start_time' => '09:00:00',
            'end_time' => '12:00:00',
            'quota' => 20,
            'valid_from' => '2026-10-05',
            'valid_until' => null,
            'is_active' => true,
        ], $overrides));
    }

    /**
     * Sesi mendatang milik templat (12 Okt 2026), kuota 20.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function sessionOf(DoctorSchedule $schedule, array $overrides = []): PracticeSession
    {
        return $this->createSession(array_merge([
            'doctor_schedule_id' => $schedule->id,
            'doctor_id' => $schedule->doctor_id,
            'clinic_id' => $schedule->clinic_id,
            'session_date' => '2026-10-12',
            'start_time' => '09:00:00',
            'end_time' => '12:00:00',
            'quota' => 20,
            'is_manually_modified' => false,
        ], $overrides));
    }

    /**
     * Nama dokter pada baris tabel, berurutan. Filter dokter di atas tabel memuat semua dokter, sehingga
     * nama dokter di seluruh HTML tidak dapat dipakai sebagai bukti baris.
     *
     * @return list<string>
     */
    private function rowDoctors(string $html): array
    {
        preg_match_all('/data-label="Dokter"><span class="fw-semibold">(.*?)<\/span>/s', $html, $matches);

        return array_map(fn (string $name): string => html_entity_decode($name), $matches[1]);
    }

    /**
     * Nama hari pada baris tabel, berurutan.
     *
     * @return list<string>
     */
    private function rowDays(string $html): array
    {
        preg_match_all('/data-label="Hari">(.*?)<\/td>/s', $html, $matches);

        return $matches[1];
    }

    private function fakeRoute(string $name, string $uri): void
    {
        if (! Route::has($name)) {
            Route::middleware('web')->get($uri, fn () => 'x')->name($name);
            Route::getRoutes()->refreshNameLookups();
        }
    }

    // --- Akses (AKS 35 dan 35b) ---------------------------------------------------------------------

    /**
     * @return array<string, array{string, int, int}>
     */
    public static function staffRoles(): array
    {
        // [peran, status daftar (schedules.view), status form tambah dan ubah (schedules.manage)]
        return [
            'super admin' => ['super-admin', 200, 200],
            'admin konten' => ['content-admin', 200, 200],
            'petugas pendaftaran' => ['registration-officer', 200, 403],
        ];
    }

    #[DataProvider('staffRoles')]
    public function test_access_per_staff_role(string $role, int $indexStatus, int $manageStatus): void
    {
        $schedule = $this->template();
        $this->actingAs($this->createStaff($role));

        $this->get(route('admin.schedules.index'))->assertStatus($indexStatus);
        $this->get(route('admin.schedules.create'))->assertStatus($manageStatus);
        $this->get(route('admin.schedules.edit', $schedule))->assertStatus($manageStatus);
    }

    public function test_patient_gets_403(): void
    {
        $this->actingAs(User::factory()->patient()->create())
            ->get(route('admin.schedules.index'))
            ->assertForbidden();
    }

    public function test_guest_is_sent_to_admin_login(): void
    {
        $this->get(route('admin.schedules.index'))->assertRedirect(route('admin.login'));
        $this->get(route('admin.schedules.create'))->assertRedirect(route('admin.login'));
    }

    public function test_urls_use_the_indonesian_segments_and_there_is_no_show_route(): void
    {
        $schedule = $this->template();

        $this->assertSame(url('/admin/jadwal'), route('admin.schedules.index'));
        $this->assertSame(url('/admin/jadwal/buat'), route('admin.schedules.create'));
        $this->assertSame(url('/admin/jadwal/'.$schedule->id.'/ubah'), route('admin.schedules.edit', $schedule));
        $this->assertFalse(Route::has('admin.schedules.show'));
    }

    public function test_non_numeric_id_is_a_404(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->get('/admin/jadwal/abc/ubah')
            ->assertNotFound();
    }

    // --- Daftar -----------------------------------------------------------------------------------------

    public function test_index_renders_many_rows_in_strict_mode_with_tabs_and_breadcrumb(): void
    {
        $sinta = $this->createDoctor(['name' => 'Sinta Maharani']);
        $anita = $this->createDoctor(['name' => 'Anita Lestari']);

        $this->template();
        $this->template(['day_of_week' => 3, 'start_time' => '13:00:00', 'end_time' => '16:00:00', 'quota' => 15]);
        $this->template(['doctor_id' => $sinta->id, 'clinic_id' => $sinta->clinic_id, 'day_of_week' => 2, 'start_time' => '10:00:00', 'end_time' => '14:00:00']);
        $this->template(['doctor_id' => $anita->id, 'clinic_id' => $anita->clinic_id, 'day_of_week' => 5, 'valid_until' => '2026-12-31', 'is_active' => false]);

        $html = $this->actingAs($this->createStaff('content-admin'))
            ->get(route('admin.schedules.index'))
            ->assertOk()
            ->assertSee('Jadwal Praktik')
            ->assertSee('Poliklinik Anak')
            ->assertSee('10.00–14.00')
            ->assertSee('13.00–16.00')
            ->assertSee('5 Okt 2026')
            ->assertSee('terbuka')
            ->assertSee('31 Des 2026')
            ->assertSee('badge text-bg-success">Aktif', false)
            ->assertSee('badge text-bg-secondary">Nonaktif', false)
            ->assertSee('Templat Mingguan')
            ->assertSee('aria-current="page"', false)
            ->assertSee('href="'.route('admin.schedules.create').'"', false)
            ->assertSee('Tambah Templat')
            ->getContent();

        $this->assertSame(
            [$anita->display_name, $this->doctor->display_name, $this->doctor->display_name, $sinta->display_name],
            $this->rowDoctors($html),
        );
        $this->assertSame(['Jumat', 'Senin', 'Rabu', 'Selasa'], $this->rowDays($html));
    }

    public function test_index_hides_manage_actions_from_the_registration_officer(): void
    {
        $schedule = $this->template();

        $this->actingAs($this->createStaff('registration-officer'))
            ->get(route('admin.schedules.index'))
            ->assertOk()
            ->assertSee('Poliklinik Anak')
            ->assertDontSee('Tambah Templat')
            ->assertDontSee(route('admin.schedules.edit', $schedule), false)
            ->assertDontSee('data-confirm-action', false);
    }

    public function test_index_has_a_delete_button_per_row_for_managers(): void
    {
        $first = $this->template();
        $second = $this->template(['day_of_week' => 2]);

        $this->actingAs($this->createStaff('content-admin'))
            ->get(route('admin.schedules.index'))
            ->assertOk()
            ->assertSee('data-confirm-action="'.route('admin.schedules.destroy', $first).'"', false)
            ->assertSee('data-confirm-action="'.route('admin.schedules.destroy', $second).'"', false);
    }

    public function test_index_empty_state(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->get(route('admin.schedules.index'))
            ->assertOk()
            ->assertSee('Belum ada templat jadwal');
    }

    public function test_index_filters_by_doctor_day_and_status_and_ignores_invalid_values(): void
    {
        $sinta = $this->createDoctor(['name' => 'Sinta Maharani']);
        $this->template();
        $this->template(['day_of_week' => 2, 'is_active' => false]);
        $this->template(['doctor_id' => $sinta->id, 'clinic_id' => $sinta->clinic_id, 'day_of_week' => 4]);
        $admin = $this->createStaff('content-admin');

        $rows = fn (array $query): array => $this->rowDays(
            $this->actingAs($admin)->get(route('admin.schedules.index', $query))->assertOk()->getContent(),
        );

        $this->assertSame(['Kamis'], $rows(['dokter' => $sinta->slug]));
        $this->assertSame(['Selasa'], $rows(['hari' => 2]));
        $this->assertSame(['Selasa'], $rows(['status' => 'nonaktif']));
        $this->assertSame(['Senin', 'Kamis'], $rows(['status' => 'aktif']));
        $this->assertCount(3, $rows(['dokter' => 'tidak-ada', 'hari' => 9, 'status' => 'zzz', 'urut' => 'kuota']));

        $this->get(route('admin.schedules.index', ['hari' => 9]))
            ->assertOk()
            ->assertSee('Filter tidak valid dan diabaikan.');
    }

    public function test_index_sorts_by_day_with_the_urut_parameter(): void
    {
        $anita = $this->createDoctor(['name' => 'Anita Lestari']);
        $this->template(['doctor_id' => $anita->id, 'clinic_id' => $anita->clinic_id, 'day_of_week' => 5]);
        $this->template(['day_of_week' => 2]);
        $this->template(['day_of_week' => 4]);
        $admin = $this->createStaff('content-admin');

        $byDoctor = $this->actingAs($admin)->get(route('admin.schedules.index'))->assertOk()->getContent();
        $byDay = $this->get(route('admin.schedules.index', ['urut' => 'hari']))->assertOk()->getContent();

        $this->assertSame(['Jumat', 'Selasa', 'Kamis'], $this->rowDays($byDoctor), 'Bawaan: nama dokter, lalu hari.');
        $this->assertSame(['Selasa', 'Kamis', 'Jumat'], $this->rowDays($byDay), 'urut=hari: hari lebih dulu.');
    }

    // --- Banner sesi perlu tindak lanjut (F3-R19) -----------------------------------------------------

    private function needsReviewSession(string $date, string $start, string $end): PracticeSession
    {
        return $this->createSession([
            'doctor_id' => $this->doctor->id,
            'clinic_id' => $this->clinic->id,
            'session_date' => $date,
            'start_time' => $start,
            'end_time' => $end,
            'status' => SessionStatus::NeedsReview,
            'pending_change' => ['source' => 'exception', 'exception_id' => 1],
        ]);
    }

    public function test_banner_counts_only_needs_review_sessions_that_have_not_ended(): void
    {
        $this->needsReviewSession('2026-10-06', '08:00:00', '10:00:00');
        $this->needsReviewSession('2026-10-05', '08:00:00', '10:00:00');
        $this->needsReviewSession('2026-10-04', '08:00:00', '10:00:00');
        $this->needsReviewSession('2026-10-05', '04:00:00', '06:00:00');
        $this->createSession(['doctor_id' => $this->doctor->id, 'clinic_id' => $this->clinic->id, 'session_date' => '2026-10-07', 'status' => SessionStatus::Cancelled]);

        $this->actingAs($this->createStaff('content-admin'))
            ->get(route('admin.schedules.index'))
            ->assertOk()
            ->assertSee('2 sesi perlu ditindaklanjuti petugas pendaftaran.');
    }

    public function test_banner_is_hidden_when_nothing_needs_review(): void
    {
        $this->template();

        $this->actingAs($this->createStaff('content-admin'))
            ->get(route('admin.schedules.index'))
            ->assertOk()
            ->assertDontSee('perlu ditindaklanjuti petugas pendaftaran')
            ->assertDontSee('data-review-banner', false);
    }

    public function test_banner_links_to_the_review_screen_only_for_permitted_users_when_the_route_exists(): void
    {
        if (Route::has('admin.sessions.review')) {
            $this->markTestSkipped('Route admin.sessions.review sudah ada; tautan diuji dengan route nyata di fase 7.');
        }

        $this->needsReviewSession('2026-10-06', '08:00:00', '10:00:00');

        $this->actingAs($this->createStaff('registration-officer'))
            ->get(route('admin.schedules.index'))
            ->assertOk()
            ->assertSee('1 sesi perlu ditindaklanjuti petugas pendaftaran.')
            ->assertDontSee('Buka Sesi Terdampak');

        $this->fakeRoute('admin.sessions.review', '/admin/_uji/sesi-terdampak');

        $this->get(route('admin.schedules.index'))->assertOk()->assertSee('Buka Sesi Terdampak');

        $this->actingAs($this->createStaff('content-admin'))
            ->get(route('admin.schedules.index'))
            ->assertOk()
            ->assertSee('1 sesi perlu ditindaklanjuti petugas pendaftaran.')
            ->assertDontSee('Buka Sesi Terdampak');
    }

    // --- Tambah -----------------------------------------------------------------------------------------

    public function test_create_form_renders_all_fields(): void
    {
        $inactiveClinic = $this->createClinic(['name' => 'Poliklinik Tutup', 'is_active' => false]);
        $archived = $this->createDoctor(['name' => 'Dokter Arsip', 'status' => 'archived', 'archived_at' => now()->subDay()]);

        $this->actingAs($this->createStaff('content-admin'))
            ->get(route('admin.schedules.create'))
            ->assertOk()
            ->assertSee('Tambah Templat Jadwal')
            ->assertSee('name="doctor_id"', false)
            ->assertSee('name="clinic_id"', false)
            ->assertSee('name="day_of_week"', false)
            ->assertSee('name="start_time"', false)
            ->assertSee('name="end_time"', false)
            ->assertSee('name="quota"', false)
            ->assertSee('name="valid_from"', false)
            ->assertSee('name="valid_until"', false)
            ->assertSee('name="notes"', false)
            ->assertSee('name="is_active"', false)
            ->assertSee('Raka Wirawan')
            ->assertSee('Poliklinik Anak')
            ->assertDontSee($archived->name)
            ->assertDontSee($inactiveClinic->name)
            ->assertSee('action="'.route('admin.schedules.store').'"', false);
    }

    public function test_store_creates_the_template_generates_sessions_and_flashes_the_impact(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->post(route('admin.schedules.store'), $this->payload())
            ->assertRedirect(route('admin.schedules.index'))
            ->assertSessionHas('success');

        $schedule = DoctorSchedule::query()->sole();
        $this->assertSame($this->doctor->id, $schedule->doctor_id);
        $this->assertSame($this->clinic->id, $schedule->clinic_id);
        $this->assertSame(1, $schedule->day_of_week);
        $this->assertSame(20, $schedule->quota);
        $this->assertNull($schedule->valid_until);
        $this->assertTrue($schedule->is_active);

        // Senin 5, 12, 19, 26 Okt dan 2 Nov 2026 ada dalam jendela 30 hari.
        $this->assertSame(5, PracticeSession::query()->where('doctor_schedule_id', $schedule->id)->count());

        $flash = (string) session('success');
        $this->assertStringStartsWith('Templat jadwal berhasil ditambahkan.', $flash);
        $this->assertStringContainsString('5 sesi dibangkitkan', $flash);
        $this->assertDatabaseHas('audit_logs', ['action' => 'schedule.created']);
    }

    public function test_store_for_an_inactive_doctor_saves_the_template_without_generating_sessions(): void
    {
        $inactive = $this->createDoctor(['name' => 'Dokter Nonaktif', 'status' => 'inactive'], $this->clinic);

        $this->actingAs($this->createStaff('content-admin'))
            ->post(route('admin.schedules.store'), $this->payload(['doctor_id' => (string) $inactive->id]))
            ->assertRedirect(route('admin.schedules.index'));

        $schedule = DoctorSchedule::query()->sole();
        $this->assertSame(0, PracticeSession::query()->where('doctor_schedule_id', $schedule->id)->count());
    }

    public function test_jdw_06_overlapping_template_is_rejected_over_http_and_a_non_overlapping_one_is_saved(): void
    {
        $this->template(['valid_until' => '2026-10-31']);
        $admin = $this->createStaff('content-admin');

        $this->actingAs($admin)
            ->from(route('admin.schedules.create'))
            ->post(route('admin.schedules.store'), $this->payload(['start_time' => '11:00', 'end_time' => '13:00', 'valid_from' => '2026-10-19']))
            ->assertRedirect(route('admin.schedules.create'))
            ->assertSessionHasErrors('start_time');

        $this->assertSame(1, DoctorSchedule::query()->count());

        $this->post(route('admin.schedules.store'), $this->payload(['start_time' => '11:00', 'end_time' => '13:00', 'valid_from' => '2026-11-02']))
            ->assertRedirect(route('admin.schedules.index'));

        $this->assertSame(2, DoctorSchedule::query()->count());
    }

    public function test_store_rejects_an_archived_doctor(): void
    {
        $archived = $this->createDoctor(['status' => 'archived', 'archived_at' => now()->subDay()], $this->clinic);

        $this->actingAs($this->createStaff('super-admin'))
            ->from(route('admin.schedules.create'))
            ->post(route('admin.schedules.store'), $this->payload(['doctor_id' => (string) $archived->id]))
            ->assertRedirect(route('admin.schedules.create'))
            ->assertSessionHasErrors(['doctor_id' => 'Dokter yang dipilih tidak tersedia.']);

        $this->assertSame(0, DoctorSchedule::query()->count());
    }

    public function test_store_validation_messages_and_old_input_are_preserved(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->from(route('admin.schedules.create'))
            ->post(route('admin.schedules.store'), $this->payload(['start_time' => '12:00', 'end_time' => '09:00', 'quota' => '0']))
            ->assertRedirect(route('admin.schedules.create'))
            ->assertSessionHasErrors([
                'end_time' => 'Jam selesai harus setelah jam mulai.',
                'quota' => 'Kuota minimal 1 dan maksimal 200.',
            ])
            ->assertSessionHasInput('start_time', '12:00');

        $this->assertSame(0, DoctorSchedule::query()->count());
    }

    public function test_registration_officer_cannot_store_and_nothing_is_saved(): void
    {
        $this->actingAs($this->createStaff('registration-officer'))
            ->post(route('admin.schedules.store'), $this->payload())
            ->assertForbidden();

        $this->assertSame(0, DoctorSchedule::query()->count());
    }

    // --- Ubah ---------------------------------------------------------------------------------------------

    public function test_edit_form_shows_current_values_and_a_read_only_doctor(): void
    {
        $schedule = $this->template(['quota' => 25, 'notes' => 'Ruang 2', 'valid_until' => '2026-12-31']);

        $this->actingAs($this->createStaff('super-admin'))
            ->get(route('admin.schedules.edit', $schedule))
            ->assertOk()
            ->assertSee('Ubah Templat Jadwal')
            ->assertSee('Raka Wirawan')
            ->assertSee('name="doctor_id" value="'.$this->doctor->id.'"', false)
            ->assertSee('value="09:00"', false)
            ->assertSee('value="12:00"', false)
            ->assertSee('value="25"', false)
            ->assertSee('value="2026-10-05"', false)
            ->assertSee('value="2026-12-31"', false)
            ->assertSee('value="Ruang 2"', false)
            ->assertSee('action="'.route('admin.schedules.update', $schedule).'"', false)
            ->assertSee('name="_method" value="PUT"', false);
    }

    public function test_update_saves_the_changes_and_flashes(): void
    {
        $schedule = $this->template();

        $this->actingAs($this->createStaff('content-admin'))
            ->put(route('admin.schedules.update', $schedule), $this->payload(['quota' => '30', 'notes' => 'Lantai 2']))
            ->assertRedirect(route('admin.schedules.index'));

        $this->assertStringStartsWith('Templat jadwal berhasil diperbarui.', (string) session('success'));
        $schedule->refresh();
        $this->assertSame(30, $schedule->quota);
        $this->assertSame('Lantai 2', $schedule->notes);
        $this->assertDatabaseHas('audit_logs', ['action' => 'schedule.updated']);
    }

    public function test_jdw_08b_changing_the_time_turns_a_session_with_an_active_appointment_into_needs_review(): void
    {
        $schedule = $this->template();
        $session = $this->sessionOf($schedule);
        $this->bookAppointment($session, AppointmentStatus::Confirmed);

        $this->actingAs($this->createStaff('content-admin'))
            ->put(route('admin.schedules.update', $schedule), $this->payload(['start_time' => '10:00', 'end_time' => '13:00']))
            ->assertRedirect(route('admin.schedules.index'));

        $this->assertStringContainsString('1 sesi perlu ditindaklanjuti petugas pendaftaran', (string) session('success'));

        $session->refresh();
        $this->assertSame(SessionStatus::NeedsReview, $session->status);
        $this->assertSame('template_time', $session->pending_change['source']);
        $this->assertSame('09:00:00', (string) $session->start_time, 'Jam lama tetap berlaku sampai ditindaklanjuti.');
    }

    public function test_jdw_08c_deactivating_a_template_marks_sessions_with_active_appointments_for_review(): void
    {
        $schedule = $this->template();
        $session = $this->sessionOf($schedule);
        $this->bookAppointment($session, AppointmentStatus::Pending);

        $this->actingAs($this->createStaff('super-admin'))
            ->put(route('admin.schedules.update', $schedule), $this->payload(['is_active' => '0']))
            ->assertRedirect(route('admin.schedules.index'));

        $this->assertStringContainsString('1 sesi perlu ditindaklanjuti petugas pendaftaran', (string) session('success'));
        $session->refresh();
        $this->assertSame(SessionStatus::NeedsReview, $session->status);
        $this->assertSame('template_removed', $session->pending_change['source']);
        $this->assertFalse($schedule->fresh()->is_active);
    }

    public function test_jdw_08d_changing_the_clinic_is_rejected_when_a_session_has_an_active_appointment(): void
    {
        $schedule = $this->template();
        $this->bookAppointment($this->sessionOf($schedule), AppointmentStatus::Confirmed);
        $other = $this->createClinic(['name' => 'Poliklinik Bedah']);

        $this->actingAs($this->createStaff('super-admin'))
            ->from(route('admin.schedules.edit', $schedule))
            ->put(route('admin.schedules.update', $schedule), $this->payload(['clinic_id' => (string) $other->id]))
            ->assertRedirect(route('admin.schedules.edit', $schedule))
            ->assertSessionHasErrors(['clinic_id' => 'Poliklinik tidak dapat diganti karena masih ada sesi mendatang dengan pendaftaran aktif. Buat jadwal baru, lalu nonaktifkan jadwal lama.']);

        $this->assertSame($this->clinic->id, $schedule->fresh()->clinic_id);
    }

    public function test_the_doctor_of_a_template_cannot_be_changed_through_the_form(): void
    {
        $schedule = $this->template();
        $other = $this->createDoctor(['name' => 'Dokter Lain'], $this->clinic);

        $this->actingAs($this->createStaff('content-admin'))
            ->from(route('admin.schedules.edit', $schedule))
            ->put(route('admin.schedules.update', $schedule), $this->payload(['doctor_id' => (string) $other->id]))
            ->assertSessionHasErrors('doctor_id');

        $this->assertSame($this->doctor->id, $schedule->fresh()->doctor_id);
    }

    public function test_registration_officer_cannot_update(): void
    {
        $schedule = $this->template();

        $this->actingAs($this->createStaff('registration-officer'))
            ->put(route('admin.schedules.update', $schedule), $this->payload(['quota' => '99']))
            ->assertForbidden();

        $this->assertSame(20, $schedule->fresh()->quota);
    }

    // --- JDW-14: Admin Konten tidak melihat data pasien ----------------------------------------------------

    public function test_jdw_14_content_admin_sees_the_summary_after_a_change_but_no_patient_data(): void
    {
        $schedule = $this->template();
        $session = $this->sessionOf($schedule);
        $patient = PatientProfile::factory()->create([
            'full_name' => 'Zulkarnain Rahasiawan',
            'phone' => '081377770042',
        ]);
        $appointment = $this->bookAppointment($session, AppointmentStatus::Confirmed, ['patient_profile_id' => $patient->id]);

        $html = $this->actingAs($this->createStaff('content-admin'))
            ->followingRedirects()
            ->put(route('admin.schedules.update', $schedule), $this->payload(['start_time' => '10:00', 'end_time' => '13:00']))
            ->assertOk()
            ->assertSee('1 sesi perlu ditindaklanjuti petugas pendaftaran')
            ->getContent();

        $this->assertStringNotContainsString('Zulkarnain', $html);
        $this->assertStringNotContainsString('081377770042', $html);
        $this->assertStringNotContainsString($appointment->booking_code, $html);
    }

    // --- Hapus (JDW-19 a, b, f) -----------------------------------------------------------------------------

    public function test_jdw_19a_template_without_appointments_is_deleted_together_with_its_sessions(): void
    {
        $schedule = $this->template();
        $this->sessionOf($schedule);
        $this->sessionOf($schedule, ['session_date' => '2026-10-19']);

        $this->actingAs($this->createStaff('content-admin'))
            ->delete(route('admin.schedules.destroy', $schedule))
            ->assertRedirect(route('admin.schedules.index'))
            ->assertSessionHas('success', 'Templat jadwal berhasil dihapus.');

        $this->assertModelMissing($schedule);
        $this->assertSame(0, PracticeSession::query()->count(), 'Tidak ada sesi yatim.');
        $this->assertDatabaseHas('audit_logs', ['action' => 'schedule.deleted']);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function managers(): array
    {
        return ['super admin' => ['super-admin'], 'admin konten' => ['content-admin']];
    }

    #[DataProvider('managers')]
    public function test_jdw_19b_template_with_a_session_that_has_appointments_cannot_be_deleted_even_by_super_admin(string $role): void
    {
        $schedule = $this->template();
        $this->bookAppointment($this->sessionOf($schedule), AppointmentStatus::Confirmed);
        $this->sessionOf($schedule, ['session_date' => '2026-10-19']);

        $this->actingAs($this->createStaff($role))
            ->delete(route('admin.schedules.destroy', $schedule))
            ->assertRedirect(route('admin.schedules.index'))
            ->assertSessionHas('error', 'Jadwal ini sudah memiliki sesi dengan pendaftaran dan hanya dapat dinonaktifkan.');

        $this->assertModelExists($schedule);
        $this->assertSame(2, PracticeSession::query()->where('doctor_schedule_id', $schedule->id)->count());
    }

    public function test_jdw_19b_history_of_cancelled_appointments_also_blocks_deletion(): void
    {
        $schedule = $this->template();
        $this->bookAppointment($this->sessionOf($schedule), AppointmentStatus::Cancelled);

        $this->actingAs($this->createStaff('super-admin'))
            ->delete(route('admin.schedules.destroy', $schedule))
            ->assertSessionHas('error', 'Jadwal ini sudah memiliki sesi dengan pendaftaran dan hanya dapat dinonaktifkan.');

        $this->assertModelExists($schedule);
    }

    public function test_jdw_19f_registration_officer_cannot_delete_a_template(): void
    {
        $schedule = $this->template();

        $this->actingAs($this->createStaff('registration-officer'))
            ->delete(route('admin.schedules.destroy', $schedule))
            ->assertForbidden();

        $this->assertModelExists($schedule);
    }
}
