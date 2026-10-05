<?php

namespace Tests\Feature\Admin;

use App\Enums\AppointmentStatus;
use App\Enums\ExceptionType;
use App\Enums\SessionStatus;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\PracticeSession;
use App\Models\ScheduleException;
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
 * Halaman #35, tab Pengecualian (T11): CRUD dengan flash laporan dampak (F3-R23), JDW-04/09/10/18 lewat HTTP,
 * F3-R21 (ubah dengan start_date lampau yang tidak berubah), akses per peran (AKS 35/35b), mode ketat.
 */
class AdminScheduleExceptionsTest extends TestCase
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

        // Senin, 5 Oktober 2026 pukul 07.00 WIB.
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
            'type' => 'doctor_leave',
            'doctor_id' => (string) $this->doctor->id,
            'start_date' => '2026-10-07',
            'end_date' => '2026-10-09',
            'reason' => 'Cuti tahunan',
        ], $overrides);
    }

    /**
     * Sesi dokter uji pada tanggal tertentu (tanpa templat), pukul 09.00-12.00.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function sessionOn(string $date, array $overrides = []): PracticeSession
    {
        return $this->createSession(array_merge([
            'doctor_id' => $this->doctor->id,
            'clinic_id' => $this->clinic->id,
            'doctor_schedule_id' => null,
            'session_date' => $date,
            'start_time' => '09:00:00',
            'end_time' => '12:00:00',
            'quota' => 20,
        ], $overrides));
    }

    /**
     * @return list<string>
     */
    private function rowReasons(string $html): array
    {
        preg_match_all('/data-label="Alasan">(.*?)<\/td>/s', $html, $matches);

        return array_map(fn (string $reason): string => html_entity_decode($reason), $matches[1]);
    }

    // --- Akses (AKS 35 dan 35b) ---------------------------------------------------------------------

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
        $exception = $this->createException();
        $this->actingAs($this->createStaff($role));

        $this->get(route('admin.exceptions.index'))->assertStatus($indexStatus);
        $this->get(route('admin.exceptions.create'))->assertStatus($manageStatus);
        $this->get(route('admin.exceptions.edit', $exception))->assertStatus($manageStatus);
    }

    public function test_patient_gets_403(): void
    {
        $this->actingAs(User::factory()->patient()->create())
            ->get(route('admin.exceptions.index'))
            ->assertForbidden();
    }

    public function test_guest_is_sent_to_admin_login(): void
    {
        $this->get(route('admin.exceptions.index'))->assertRedirect(route('admin.login'));
    }

    public function test_urls_use_the_indonesian_segments_and_there_is_no_show_route(): void
    {
        $exception = $this->createException();

        $this->assertSame(url('/admin/jadwal/pengecualian'), route('admin.exceptions.index'));
        $this->assertSame(url('/admin/jadwal/pengecualian/buat'), route('admin.exceptions.create'));
        $this->assertSame(url('/admin/jadwal/pengecualian/'.$exception->id.'/ubah'), route('admin.exceptions.edit', $exception));
        $this->assertFalse(Route::has('admin.exceptions.show'));
    }

    public function test_non_numeric_id_is_a_404(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->get('/admin/jadwal/pengecualian/abc/ubah')
            ->assertNotFound();
    }

    // --- Daftar -----------------------------------------------------------------------------------------

    public function test_index_renders_many_rows_in_strict_mode_with_tab_and_breadcrumb(): void
    {
        $other = $this->createDoctor(['name' => 'Sinta Maharani']);
        $this->createException(['type' => ExceptionType::DoctorLeave, 'doctor_id' => $this->doctor->id, 'start_date' => '2026-10-07', 'end_date' => '2026-10-09', 'reason' => 'Cuti tahunan']);
        $this->createException(['type' => ExceptionType::DoctorLeave, 'doctor_id' => $other->id, 'start_date' => '2026-10-12', 'end_date' => '2026-10-12', 'reason' => 'Menikah']);
        $this->createException(['type' => ExceptionType::HospitalHoliday, 'doctor_id' => null, 'start_date' => '2026-10-20', 'end_date' => '2026-10-20', 'reason' => 'Hari ulang tahun RS']);
        $this->createException(['type' => ExceptionType::NationalHoliday, 'doctor_id' => null, 'start_date' => '2026-12-25', 'end_date' => '2026-12-25', 'reason' => 'Natal']);

        $html = $this->actingAs($this->createStaff('content-admin'))
            ->get(route('admin.exceptions.index'))
            ->assertOk()
            ->assertSee('Jadwal Praktik')
            ->assertSee('Pengecualian')
            ->assertSee('aria-current="page"', false)
            ->assertSee('Cuti dokter')
            ->assertSee('Libur rumah sakit')
            ->assertSee('Libur nasional')
            ->assertSee($this->doctor->display_name)
            ->assertSee($other->display_name)
            ->assertSee('Semua dokter')
            ->assertSee('7 Okt 2026 – 9 Okt 2026')
            ->assertSee('12 Okt 2026')
            ->assertSee('href="'.route('admin.exceptions.create').'"', false)
            ->assertSee('Tambah Pengecualian')
            ->getContent();

        $this->assertSame(['Cuti tahunan', 'Menikah', 'Hari ulang tahun RS', 'Natal'], $this->rowReasons($html));
    }

    public function test_index_hides_manage_actions_from_the_registration_officer(): void
    {
        $exception = $this->createException();

        $this->actingAs($this->createStaff('registration-officer'))
            ->get(route('admin.exceptions.index'))
            ->assertOk()
            ->assertDontSee('Tambah Pengecualian')
            ->assertDontSee(route('admin.exceptions.edit', $exception), false)
            ->assertDontSee('data-confirm-action', false);
    }

    public function test_index_has_edit_and_delete_actions_per_row_for_managers(): void
    {
        $first = $this->createException(['start_date' => '2026-10-07', 'end_date' => '2026-10-07']);
        $second = $this->createException(['start_date' => '2026-10-08', 'end_date' => '2026-10-08']);

        $this->actingAs($this->createStaff('content-admin'))
            ->get(route('admin.exceptions.index'))
            ->assertOk()
            ->assertSee('href="'.route('admin.exceptions.edit', $first).'"', false)
            ->assertSee('data-confirm-action="'.route('admin.exceptions.destroy', $first).'"', false)
            ->assertSee('data-confirm-action="'.route('admin.exceptions.destroy', $second).'"', false);
    }

    public function test_index_empty_state(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->get(route('admin.exceptions.index'))
            ->assertOk()
            ->assertSee('Belum ada cuti atau libur terdaftar');
    }

    public function test_index_shows_upcoming_by_default_and_filters_by_time_type_and_keyword(): void
    {
        $this->createException(['type' => ExceptionType::Other, 'doctor_id' => null, 'start_date' => '2026-09-01', 'end_date' => '2026-09-03', 'reason' => 'Pelatihan lama']);
        $this->createException(['type' => ExceptionType::Other, 'doctor_id' => null, 'start_date' => '2026-10-01', 'end_date' => '2026-10-05', 'reason' => 'Pelatihan berjalan']);
        $this->createException(['type' => ExceptionType::HospitalHoliday, 'doctor_id' => null, 'start_date' => '2026-10-20', 'end_date' => '2026-10-20', 'reason' => 'Libur RS']);
        $admin = $this->createStaff('content-admin');

        $reasons = fn (array $query): array => $this->rowReasons(
            $this->actingAs($admin)->get(route('admin.exceptions.index', $query))->assertOk()->getContent(),
        );

        $this->assertSame(['Pelatihan berjalan', 'Libur RS'], $reasons([]));
        $this->assertSame(['Pelatihan lama'], $reasons(['status' => 'lampau']));
        $this->assertSame(['Pelatihan lama', 'Pelatihan berjalan', 'Libur RS'], $reasons(['status' => 'semua']));
        $this->assertSame(['Libur RS'], $reasons(['jenis' => 'hospital_holiday']));
        $this->assertSame(['Pelatihan berjalan'], $reasons(['q' => 'berjalan']));
        $this->assertSame(['Pelatihan berjalan', 'Libur RS'], $reasons(['status' => 'zzz', 'jenis' => 'libur-bebas']));

        $this->get(route('admin.exceptions.index', ['status' => 'zzz']))
            ->assertOk()
            ->assertSee('Filter tidak valid dan diabaikan.');
    }

    // --- Tambah -----------------------------------------------------------------------------------------

    public function test_create_form_renders_all_fields_and_offers_all_doctors_except_archived(): void
    {
        $archived = $this->createDoctor(['name' => 'Dokter Arsip', 'status' => 'archived', 'archived_at' => now()->subDay()]);
        $this->createDoctor(['name' => 'Dokter Nonaktif', 'status' => 'inactive']);

        $this->actingAs($this->createStaff('content-admin'))
            ->get(route('admin.exceptions.create'))
            ->assertOk()
            ->assertSee('Tambah Pengecualian')
            ->assertSee('name="type"', false)
            ->assertSee('name="doctor_id"', false)
            ->assertSee('name="start_date"', false)
            ->assertSee('name="end_date"', false)
            ->assertSee('name="reason"', false)
            ->assertSee('Cuti dokter')
            ->assertSee('Libur rumah sakit')
            ->assertSee('Libur nasional')
            ->assertSee('Lainnya')
            ->assertSee('Semua dokter')
            ->assertSee('Raka Wirawan')
            ->assertSee('Dokter Nonaktif')
            ->assertDontSee($archived->name)
            ->assertSee('action="'.route('admin.exceptions.store').'"', false);
    }

    public function test_jdw_09_doctor_leave_cancels_empty_sessions_and_flags_those_with_active_appointments(): void
    {
        $empty = $this->sessionOn('2026-10-07');
        $booked = $this->sessionOn('2026-10-08');
        $outside = $this->sessionOn('2026-10-12');
        $appointment = $this->bookAppointment($booked, AppointmentStatus::Confirmed);

        $this->actingAs($this->createStaff('content-admin'))
            ->post(route('admin.exceptions.store'), $this->payload())
            ->assertRedirect(route('admin.exceptions.index'));

        $exception = ScheduleException::query()->sole();
        $this->assertSame(ExceptionType::DoctorLeave, $exception->type);
        $this->assertSame($this->doctor->id, $exception->doctor_id);

        $empty->refresh();
        $this->assertSame(SessionStatus::Cancelled, $empty->status);
        $this->assertSame($exception->id, $empty->schedule_exception_id);
        $this->assertSame('Cuti tahunan', $empty->status_reason);

        $booked->refresh();
        $this->assertSame(SessionStatus::NeedsReview, $booked->status);
        $this->assertEquals(['source' => 'exception', 'exception_id' => $exception->id], $booked->pending_change);
        $this->assertSame(AppointmentStatus::Confirmed, $appointment->fresh()->status, 'Appointment lama tetap aktif.');

        $this->assertSame(SessionStatus::Open, $outside->fresh()->status);

        $flash = (string) session('success');
        $this->assertStringStartsWith('Pengecualian jadwal berhasil ditambahkan.', $flash);
        $this->assertStringContainsString('1 sesi dibatalkan', $flash);
        $this->assertStringContainsString('1 sesi perlu ditindaklanjuti petugas pendaftaran', $flash);
        $this->assertDatabaseHas('audit_logs', ['action' => 'exception.created']);
    }

    public function test_jdw_04_hospital_holiday_cancels_every_doctors_sessions_that_day(): void
    {
        $other = $this->createDoctor(['name' => 'Sinta Maharani']);
        $first = $this->sessionOn('2026-10-08');
        $second = $this->sessionOn('2026-10-08', ['doctor_id' => $other->id, 'clinic_id' => $other->clinic_id]);
        $nextDay = $this->sessionOn('2026-10-09');

        $this->actingAs($this->createStaff('super-admin'))
            ->post(route('admin.exceptions.store'), $this->payload([
                'type' => 'hospital_holiday',
                'doctor_id' => '',
                'start_date' => '2026-10-08',
                'end_date' => '2026-10-08',
                'reason' => 'Hari ulang tahun RS',
            ]))
            ->assertRedirect(route('admin.exceptions.index'));

        $exception = ScheduleException::query()->sole();
        $this->assertNull($exception->doctor_id);
        $this->assertSame(SessionStatus::Cancelled, $first->fresh()->status);
        $this->assertSame(SessionStatus::Cancelled, $second->fresh()->status);
        $this->assertSame('Hari ulang tahun RS', $second->fresh()->status_reason);
        $this->assertSame(SessionStatus::Open, $nextDay->fresh()->status);
        $this->assertStringContainsString('2 sesi dibatalkan', (string) session('success'));
    }

    /**
     * @return array<string, array{array<string, mixed>, string, string}>
     */
    public static function invalidCombinations(): array
    {
        return [
            'cuti dokter tanpa dokter' => [['type' => 'doctor_leave', 'doctor_id' => ''], 'doctor_id', 'Pilih dokter untuk cuti dokter.'],
            'libur rumah sakit dengan dokter' => [['type' => 'hospital_holiday'], 'doctor_id', 'Libur rumah sakit atau libur nasional berlaku untuk semua dokter. Kosongkan pilihan dokter.'],
            'libur nasional dengan dokter' => [['type' => 'national_holiday'], 'doctor_id', 'Libur rumah sakit atau libur nasional berlaku untuk semua dokter. Kosongkan pilihan dokter.'],
            'mulai di masa lalu' => [['start_date' => '2026-10-04', 'end_date' => '2026-10-06'], 'start_date', 'Tanggal mulai tidak boleh di masa lalu.'],
            'selesai sebelum mulai' => [['start_date' => '2026-10-09', 'end_date' => '2026-10-08'], 'end_date', 'Tanggal selesai tidak boleh sebelum tanggal mulai.'],
            'alasan kosong' => [['reason' => ''], 'reason', 'Alasan wajib diisi.'],
        ];
    }

    /**
     * @param  array<string, mixed>  $override
     */
    #[DataProvider('invalidCombinations')]
    public function test_jdw_18_invalid_input_is_rejected_over_http_and_nothing_is_saved(array $override, string $field, string $message): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->from(route('admin.exceptions.create'))
            ->post(route('admin.exceptions.store'), $this->payload($override))
            ->assertRedirect(route('admin.exceptions.create'))
            ->assertSessionHasErrors([$field => $message]);

        $this->assertSame(0, ScheduleException::query()->count());
    }

    public function test_jdw_18_other_type_is_accepted_with_and_without_a_doctor(): void
    {
        $admin = $this->createStaff('content-admin');

        $this->actingAs($admin)
            ->post(route('admin.exceptions.store'), $this->payload(['type' => 'other']))
            ->assertRedirect(route('admin.exceptions.index'));
        $this->post(route('admin.exceptions.store'), $this->payload(['type' => 'other', 'doctor_id' => '', 'start_date' => '2026-10-20', 'end_date' => '2026-10-20']))
            ->assertRedirect(route('admin.exceptions.index'));

        $this->assertSame(2, ScheduleException::query()->count());
    }

    public function test_registration_officer_cannot_store(): void
    {
        $this->actingAs($this->createStaff('registration-officer'))
            ->post(route('admin.exceptions.store'), $this->payload())
            ->assertForbidden();

        $this->assertSame(0, ScheduleException::query()->count());
    }

    // --- Ubah ---------------------------------------------------------------------------------------------

    public function test_edit_form_shows_current_values(): void
    {
        $exception = $this->createException([
            'type' => ExceptionType::DoctorLeave,
            'doctor_id' => $this->doctor->id,
            'start_date' => '2026-10-07',
            'end_date' => '2026-10-09',
            'reason' => 'Cuti tahunan',
        ]);

        $this->actingAs($this->createStaff('super-admin'))
            ->get(route('admin.exceptions.edit', $exception))
            ->assertOk()
            ->assertSee('Ubah Pengecualian')
            ->assertSee('value="2026-10-07"', false)
            ->assertSee('value="2026-10-09"', false)
            ->assertSee('value="Cuti tahunan"', false)
            ->assertSee('action="'.route('admin.exceptions.update', $exception).'"', false)
            ->assertSee('name="_method" value="PUT"', false);
    }

    public function test_f3_r21_update_may_keep_a_past_start_date_when_it_is_unchanged(): void
    {
        $exception = $this->createException([
            'type' => ExceptionType::DoctorLeave,
            'doctor_id' => $this->doctor->id,
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-10',
            'reason' => 'Cuti panjang',
        ]);

        $this->actingAs($this->createStaff('content-admin'))
            ->put(route('admin.exceptions.update', $exception), $this->payload(['start_date' => '2026-10-01', 'end_date' => '2026-10-12', 'reason' => 'Cuti diperpanjang']))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.exceptions.index'));

        $this->assertStringStartsWith('Pengecualian jadwal berhasil diperbarui.', (string) session('success'));
        $exception->refresh();
        $this->assertSame('2026-10-01', $exception->start_date->toDateString());
        $this->assertSame('2026-10-12', $exception->end_date->toDateString());
        $this->assertDatabaseHas('audit_logs', ['action' => 'exception.updated']);
    }

    public function test_f3_r21_update_that_moves_the_start_date_into_the_past_is_rejected(): void
    {
        $exception = $this->createException([
            'type' => ExceptionType::DoctorLeave,
            'doctor_id' => $this->doctor->id,
            'start_date' => '2026-10-07',
            'end_date' => '2026-10-10',
        ]);

        $this->actingAs($this->createStaff('content-admin'))
            ->from(route('admin.exceptions.edit', $exception))
            ->put(route('admin.exceptions.update', $exception), $this->payload(['start_date' => '2026-10-02', 'end_date' => '2026-10-10']))
            ->assertSessionHasErrors(['start_date' => 'Tanggal mulai tidak boleh di masa lalu.']);

        $this->assertSame('2026-10-07', $exception->fresh()->start_date->toDateString());
    }

    public function test_jdw_10_narrowing_the_range_reopens_the_sessions_that_fall_outside_it(): void
    {
        $day7 = $this->sessionOn('2026-10-07');
        $day8 = $this->sessionOn('2026-10-08');
        $day9 = $this->sessionOn('2026-10-09');
        $admin = $this->createStaff('content-admin');

        $this->actingAs($admin)->post(route('admin.exceptions.store'), $this->payload())->assertRedirect(route('admin.exceptions.index'));
        $exception = ScheduleException::query()->sole();

        foreach ([$day7, $day8, $day9] as $session) {
            $this->assertSame(SessionStatus::Cancelled, $session->fresh()->status);
        }

        $this->put(route('admin.exceptions.update', $exception), $this->payload(['end_date' => '2026-10-07']))
            ->assertRedirect(route('admin.exceptions.index'));

        $this->assertSame(SessionStatus::Cancelled, $day7->fresh()->status);
        $this->assertSame(SessionStatus::Open, $day8->fresh()->status);
        $this->assertNull($day8->fresh()->schedule_exception_id);
        $this->assertSame(SessionStatus::Open, $day9->fresh()->status);
        $this->assertStringContainsString('2 sesi dibuka kembali', (string) session('success'));
    }

    public function test_registration_officer_cannot_update(): void
    {
        $exception = $this->createException(['reason' => 'Alasan asli']);

        $this->actingAs($this->createStaff('registration-officer'))
            ->put(route('admin.exceptions.update', $exception), $this->payload(['reason' => 'Diubah petugas']))
            ->assertForbidden();

        $this->assertSame('Alasan asli', $exception->fresh()->reason);
    }

    // --- Hapus = cabut ------------------------------------------------------------------------------------

    public function test_jdw_10_deleting_an_exception_reopens_its_sessions_and_removes_the_row(): void
    {
        $day7 = $this->sessionOn('2026-10-07');
        $day8 = $this->sessionOn('2026-10-08');
        $booked = $this->sessionOn('2026-10-09');
        $appointment = $this->bookAppointment($booked, AppointmentStatus::Confirmed);
        $admin = $this->createStaff('content-admin');

        $this->actingAs($admin)->post(route('admin.exceptions.store'), $this->payload())->assertRedirect(route('admin.exceptions.index'));
        $exception = ScheduleException::query()->sole();
        $this->assertSame(SessionStatus::NeedsReview, $booked->fresh()->status);

        $this->delete(route('admin.exceptions.destroy', $exception))
            ->assertRedirect(route('admin.exceptions.index'));

        $this->assertModelMissing($exception);
        $this->assertSame(SessionStatus::Open, $day7->fresh()->status);
        $this->assertSame(SessionStatus::Open, $day8->fresh()->status);
        $this->assertNull($day7->fresh()->schedule_exception_id);

        $booked->refresh();
        $this->assertSame(SessionStatus::Open, $booked->status);
        $this->assertNull($booked->pending_change);
        $this->assertNull($booked->schedule_exception_id);
        $this->assertSame(AppointmentStatus::Confirmed, $appointment->fresh()->status);

        $flash = (string) session('success');
        $this->assertStringStartsWith('Pengecualian jadwal berhasil dihapus.', $flash);
        $this->assertStringContainsString('3 sesi dibuka kembali', $flash);
        $this->assertDatabaseHas('audit_logs', ['action' => 'exception.deleted']);
    }

    public function test_deleting_an_exception_does_not_reopen_sessions_that_have_already_ended(): void
    {
        $exception = $this->createException([
            'type' => ExceptionType::DoctorLeave,
            'doctor_id' => $this->doctor->id,
            'start_date' => '2026-10-05',
            'end_date' => '2026-10-06',
            'reason' => 'Cuti singkat',
        ]);
        $ended = $this->sessionOn('2026-10-05', [
            'start_time' => '04:00:00',
            'end_time' => '06:00:00',
            'status' => SessionStatus::Cancelled,
            'schedule_exception_id' => $exception->id,
            'status_reason' => 'Cuti singkat',
        ]);
        $future = $this->sessionOn('2026-10-06', [
            'status' => SessionStatus::Cancelled,
            'schedule_exception_id' => $exception->id,
            'status_reason' => 'Cuti singkat',
        ]);

        $this->actingAs($this->createStaff('super-admin'))
            ->delete(route('admin.exceptions.destroy', $exception))
            ->assertRedirect(route('admin.exceptions.index'));

        $this->assertSame(SessionStatus::Cancelled, $ended->fresh()->status);
        $this->assertSame(SessionStatus::Open, $future->fresh()->status);
    }

    public function test_registration_officer_cannot_delete(): void
    {
        $exception = $this->createException();

        $this->actingAs($this->createStaff('registration-officer'))
            ->delete(route('admin.exceptions.destroy', $exception))
            ->assertForbidden();

        $this->assertModelExists($exception);
    }

    public function test_index_does_not_fail_on_array_valued_filters(): void
    {
        $this->createException();

        $this->actingAs($this->createStaff('content-admin'))
            ->get(route('admin.exceptions.index', ['jenis' => ['x'], 'status' => ['x'], 'q' => ['x']]))
            ->assertOk();
    }

    public function test_edit_keeps_an_archived_doctor_selected_and_saving_does_not_widen_to_all_doctors(): void
    {
        $archived = $this->createDoctor(['name' => 'Dokter Arsip', 'status' => 'archived', 'archived_at' => now()->subDay()]);
        $exception = $this->createException([
            'type' => ExceptionType::Other,
            'doctor_id' => $archived->id,
            'start_date' => '2026-10-07',
            'end_date' => '2026-10-08',
            'reason' => 'Pelatihan',
        ]);
        $admin = $this->createStaff('content-admin');

        $html = $this->actingAs($admin)
            ->get(route('admin.exceptions.edit', $exception))
            ->assertOk()
            ->getContent();
        $this->assertMatchesRegularExpression('/<option value="'.$archived->id.'"\s+selected/', $html);

        $this->from(route('admin.exceptions.edit', $exception))
            ->put(route('admin.exceptions.update', $exception), $this->payload([
                'type' => 'other',
                'doctor_id' => (string) $archived->id,
                'reason' => 'Pelatihan diubah',
            ]))
            ->assertSessionHasErrors('doctor_id');

        $exception->refresh();
        $this->assertSame($archived->id, $exception->doctor_id);
        $this->assertSame('Pelatihan', $exception->reason);
    }
}
