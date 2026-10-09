<?php

namespace Tests\Feature\Admin;

use App\Enums\AppointmentStatus;
use App\Models\AuditLog;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\PatientProfile;
use App\Models\PracticeSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InteractsWithMasterData;
use Tests\Support\InteractsWithPatients;
use Tests\Support\InteractsWithSchedules;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

/**
 * Nonaktifkan dan aktifkan kembali pasien (#37, F7A-R10, V-18): WLK-07 lewat HTTP (alasan wajib 5-255, ditolak bila ada
 * pendaftaran aktif dengan pesan berjumlah, aktifkan kembali, profil berakun menyinkronkan `users.is_active` dan mengakhiri
 * sesi login), audit, akses, dan tampilan tombol serta modal di halaman detail.
 */
class AdminPatientStatusTest extends TestCase
{
    use InteractsWithMasterData;
    use InteractsWithPatients;
    use InteractsWithSchedules;
    use InteractsWithStaff;
    use RefreshDatabase;

    private User $officer;

    private Clinic $clinic;

    private Doctor $doctor;

    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->officer = $this->createStaff('registration-officer');
        $this->clinic = $this->createClinic(['name' => 'Poli Anak', 'code' => 'ANK']);
        $this->doctor = $this->createDoctor(['name' => 'Sinta Maharani'], $this->clinic);
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
            'doctor_schedule_id' => null,
        ]);
    }

    private function book(PatientProfile $profile, AppointmentStatus $status): void
    {
        $this->bookAppointment($this->sessionOn('2026-10-'.str_pad((string) (10 + ++$this->sequence), 2, '0', STR_PAD_LEFT)), $status, [
            'booking_code' => sprintf('UPH-ABCD-%04d', $this->sequence),
            'patient_profile_id' => $profile->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function patchStatus(PatientProfile $profile, array $data, ?User $user = null): TestResponse
    {
        return $this->actingAs($user ?? $this->officer)
            ->from(route('admin.patients.show', $profile))
            ->patch(route('admin.patients.status', $profile), $data);
    }

    private function walkIn(array $attributes = []): PatientProfile
    {
        return PatientProfile::factory()->create(array_merge(['patient_number' => 'P000700', 'full_name' => 'Siti Rahma'], $attributes));
    }

    // --- nonaktifkan -------------------------------------------------------------------------

    public function test_a_walk_in_profile_is_deactivated_with_a_reason_wlk_07(): void
    {
        $profile = $this->walkIn();

        $this->patchStatus($profile, ['is_active' => '0', 'reason' => 'Data ganda dengan profil lain'])
            ->assertRedirect(route('admin.patients.show', $profile))
            ->assertSessionHas('success', 'Profil pasien P000700 dinonaktifkan.');

        $this->assertFalse($profile->fresh()->is_active);

        $log = AuditLog::query()->where('action', 'patient.status_changed')->sole();

        $this->assertTrue($log->old_values['is_active']);
        $this->assertFalse($log->new_values['is_active']);
        $this->assertSame('Data ganda dengan profil lain', $log->new_values['reason']);
        $this->assertSame('Profil pasien P000700 dinonaktifkan.', $log->description);
        $this->assertSame($this->officer->id, (int) $log->user_id);
    }

    public function test_the_history_is_kept_and_the_profile_can_be_activated_again(): void
    {
        $profile = $this->walkIn();
        $this->book($profile, AppointmentStatus::Completed);

        $this->patchStatus($profile, ['is_active' => '0', 'reason' => 'Pasien pindah domisili'])->assertSessionHasNoErrors();
        $this->assertFalse($profile->fresh()->is_active);
        $this->assertSame(1, $profile->appointments()->count());

        $this->patchStatus($profile, ['is_active' => '1'])
            ->assertRedirect(route('admin.patients.show', $profile))
            ->assertSessionHas('success', 'Profil pasien P000700 diaktifkan kembali.');

        $this->assertTrue($profile->fresh()->is_active);
        $this->assertSame(1, $profile->appointments()->count());
        $this->assertSame(2, AuditLog::query()->where('action', 'patient.status_changed')->count());
    }

    public function test_activating_with_an_optional_reason_is_accepted(): void
    {
        $profile = $this->walkIn(['is_active' => false]);

        $this->patchStatus($profile, ['is_active' => '1', 'reason' => 'Data sudah diperbaiki'])->assertSessionHasNoErrors();

        $this->assertTrue($profile->fresh()->is_active);
    }

    public function test_setting_the_same_status_writes_nothing_and_is_not_audited(): void
    {
        $profile = $this->walkIn();

        $this->patchStatus($profile, ['is_active' => '1'])->assertSessionHasNoErrors();

        $this->assertTrue($profile->fresh()->is_active);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'patient.status_changed']);
    }

    // --- V-18: alasan -------------------------------------------------------------------------

    /**
     * @return array<string, array{array<string, mixed>, string, string}>
     */
    public static function invalidInputs(): array
    {
        return [
            'tanpa alasan' => [['is_active' => '0'], 'reason', 'Alasan penonaktifan wajib diisi.'],
            'alasan kosong' => [['is_active' => '0', 'reason' => ''], 'reason', 'Alasan penonaktifan wajib diisi.'],
            'alasan spasi saja' => [['is_active' => '0', 'reason' => '      '], 'reason', 'Alasan penonaktifan wajib diisi.'],
            'alasan 4 karakter' => [['is_active' => '0', 'reason' => 'abcd'], 'reason', 'Alasan minimal 5 karakter.'],
            'alasan 256 karakter' => [['is_active' => '0', 'reason' => 'x256'], 'reason', 'Alasan maksimal 255 karakter.'],
            'alasan larik' => [['is_active' => '0', 'reason' => ['abcde']], 'reason', 'Alasan harus berupa teks.'],
            'status kosong' => [['reason' => 'Alasan yang cukup'], 'is_active', 'Status pasien wajib dipilih.'],
            'status asing' => [['is_active' => 'mungkin', 'reason' => 'Alasan yang cukup'], 'is_active', 'Status pasien tidak valid.'],
            'status larik' => [['is_active' => ['0'], 'reason' => 'Alasan yang cukup'], 'is_active', 'Status pasien tidak valid.'],
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    #[DataProvider('invalidInputs')]
    public function test_invalid_status_input_is_rejected_and_shown_as_a_flash_error(array $input, string $field, string $message): void
    {
        $profile = $this->walkIn();
        $input = array_map(fn ($value) => $value === 'x256' ? str_repeat('x', 256) : $value, $input);

        $this->patchStatus($profile, $input)
            ->assertRedirect(route('admin.patients.show', $profile))
            ->assertSessionHasErrors([$field => $message])
            ->assertSessionHas('error', $message);

        $this->assertTrue($profile->fresh()->is_active);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'patient.status_changed']);
    }

    public function test_a_reason_of_exactly_five_and_255_characters_is_accepted(): void
    {
        $first = $this->walkIn(['patient_number' => 'P000701', 'full_name' => 'Pasien Satu']);
        $second = $this->walkIn(['patient_number' => 'P000702', 'full_name' => 'Pasien Dua']);

        $this->patchStatus($first, ['is_active' => '0', 'reason' => 'abcde'])->assertSessionHasNoErrors();
        $this->patchStatus($second, ['is_active' => '0', 'reason' => str_repeat('x', 255)])->assertSessionHasNoErrors();

        $this->assertFalse($first->fresh()->is_active);
        $this->assertFalse($second->fresh()->is_active);
    }

    // --- BR-105: pendaftaran aktif -------------------------------------------------------------

    public function test_deactivation_is_refused_while_the_patient_has_active_appointments(): void
    {
        $profile = $this->walkIn();
        $this->book($profile, AppointmentStatus::Pending);
        $this->book($profile, AppointmentStatus::Confirmed);
        $this->book($profile, AppointmentStatus::Cancelled);
        $this->book($profile, AppointmentStatus::Completed);

        $message = 'Pasien masih memiliki 2 pendaftaran aktif. Batalkan atau selesaikan terlebih dahulu.';

        $this->patchStatus($profile, ['is_active' => '0', 'reason' => 'Uji penonaktifan'])
            ->assertRedirect(route('admin.patients.show', $profile))
            ->assertSessionHasErrors(['is_active' => $message])
            ->assertSessionHas('error', $message);

        $this->assertTrue($profile->fresh()->is_active);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'patient.status_changed']);
    }

    public function test_a_checked_in_appointment_also_blocks_the_deactivation(): void
    {
        $profile = $this->walkIn();
        $this->book($profile, AppointmentStatus::CheckedIn);

        $this->patchStatus($profile, ['is_active' => '0', 'reason' => 'Uji penonaktifan'])
            ->assertSessionHasErrors(['is_active' => 'Pasien masih memiliki 1 pendaftaran aktif. Batalkan atau selesaikan terlebih dahulu.']);

        $this->assertTrue($profile->fresh()->is_active);
    }

    public function test_finished_cancelled_and_no_show_appointments_do_not_block_the_deactivation(): void
    {
        $profile = $this->walkIn();
        $this->book($profile, AppointmentStatus::Completed);
        $this->book($profile, AppointmentStatus::Cancelled);
        $this->book($profile, AppointmentStatus::NoShow);

        $this->patchStatus($profile, ['is_active' => '0', 'reason' => 'Uji penonaktifan'])->assertSessionHasNoErrors();

        $this->assertFalse($profile->fresh()->is_active);
    }

    // --- profil berakun --------------------------------------------------------------------------

    public function test_deactivating_a_linked_profile_deactivates_the_account_and_ends_its_login_sessions(): void
    {
        $user = $this->createPatient(['email' => 'akun@contoh.test'], ['patient_number' => 'P000710']);
        $profile = $user->patientProfile;

        DB::table('sessions')->insert([
            'id' => 'sesi-uji-pasien-0000000000000000000001',
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'payload' => 'x',
            'last_activity' => time(),
        ]);

        $this->patchStatus($profile, ['is_active' => '0', 'reason' => 'Akun disalahgunakan'])
            ->assertSessionHas('success', 'Profil pasien P000710 dinonaktifkan.');

        $this->assertFalse($profile->fresh()->is_active);
        $this->assertFalse($user->fresh()->is_active);
        $this->assertSame(0, DB::table('sessions')->where('user_id', $user->id)->count());

        $this->app['auth']->forgetGuards();

        $this->actingAs($user->fresh())
            ->get(route('patient.dashboard'))
            ->assertRedirect(route('login'));
    }

    public function test_activating_a_linked_profile_activates_the_account_again(): void
    {
        $user = $this->createPatient(['email' => 'akun@contoh.test', 'is_active' => false], ['patient_number' => 'P000711', 'is_active' => false]);

        $this->patchStatus($user->patientProfile, ['is_active' => '1'])->assertSessionHasNoErrors();

        $this->assertTrue($user->patientProfile->fresh()->is_active);
        $this->assertTrue($user->fresh()->is_active);
    }

    public function test_deactivating_a_walk_in_profile_does_not_touch_other_accounts_or_sessions(): void
    {
        $other = $this->createPatient(['email' => 'lain@contoh.test']);
        $profile = $this->walkIn();

        DB::table('sessions')->insert([
            'id' => 'sesi-uji-pasien-0000000000000000000002',
            'user_id' => $other->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'payload' => 'x',
            'last_activity' => time(),
        ]);

        $this->patchStatus($profile, ['is_active' => '0', 'reason' => 'Uji penonaktifan'])->assertSessionHasNoErrors();

        $this->assertTrue($other->fresh()->is_active);
        $this->assertSame(1, DB::table('sessions')->where('user_id', $other->id)->count());
    }

    // --- akses -------------------------------------------------------------------------------

    public function test_super_admin_can_change_the_status(): void
    {
        $profile = $this->walkIn();

        $this->patchStatus($profile, ['is_active' => '0', 'reason' => 'Uji oleh super admin'], $this->createStaff('super-admin'))
            ->assertSessionHasNoErrors();

        $this->assertFalse($profile->fresh()->is_active);
    }

    public function test_admin_konten_patients_and_guests_cannot_change_the_status(): void
    {
        $profile = $this->walkIn();
        $url = route('admin.patients.status', $profile);

        $this->actingAs($this->createStaff('content-admin'))
            ->patch($url, ['is_active' => '0', 'reason' => 'Uji penonaktifan'])
            ->assertForbidden();

        $this->actingAs($this->createPatient())
            ->patch($url, ['is_active' => '0', 'reason' => 'Uji penonaktifan'])
            ->assertForbidden();

        $this->app['auth']->forgetGuards();

        $this->patch($url, ['is_active' => '0', 'reason' => 'Uji penonaktifan'])->assertRedirect(route('admin.login'));

        $this->assertTrue($profile->fresh()->is_active);
    }

    public function test_an_unknown_or_malformed_patient_id_answers_404(): void
    {
        $this->actingAs($this->officer);

        $this->patch('/admin/pasien/999999/status', ['is_active' => '0', 'reason' => 'Uji penonaktifan'])->assertNotFound();
        $this->patch('/admin/pasien/abc/status', ['is_active' => '0', 'reason' => 'Uji penonaktifan'])->assertNotFound();
    }

    // --- tampilan di halaman detail ------------------------------------------------------------

    public function test_the_detail_page_offers_the_deactivate_button_and_a_modal_with_a_required_reason(): void
    {
        $profile = $this->walkIn(['full_name' => 'Siti Rahma']);

        $this->actingAs($this->officer)
            ->get(route('admin.patients.show', $profile))
            ->assertOk()
            ->assertSee('data-bs-target="#modal-patient-deactivate"', false)
            ->assertSee('id="modal-patient-deactivate"', false)
            ->assertSee('action="'.route('admin.patients.status', $profile).'"', false)
            ->assertSee('name="is_active" value="0"', false)
            ->assertSee('name="reason" rows="3" minlength="5" maxlength="255" required', false)
            ->assertSee('Wajib diisi, 5 sampai 255 karakter.')
            ->assertSee('href="'.route('admin.patients.edit', $profile).'"', false)
            ->assertDontSee('id="modal-patient-activate"', false);
    }

    public function test_the_detail_page_of_an_inactive_profile_offers_the_activate_button(): void
    {
        $profile = $this->walkIn(['is_active' => false]);

        $this->actingAs($this->officer)
            ->get(route('admin.patients.show', $profile))
            ->assertOk()
            ->assertSee('data-bs-target="#modal-patient-activate"', false)
            ->assertSee('id="modal-patient-activate"', false)
            ->assertSee('name="is_active" value="1"', false)
            ->assertSee('Aktifkan Kembali')
            ->assertDontSee('id="modal-patient-deactivate"', false);
    }

    public function test_the_deactivate_modal_mentions_the_account_only_for_a_linked_profile(): void
    {
        $user = $this->createPatient();
        $walkIn = $this->walkIn();

        $this->actingAs($this->officer);

        $this->get(route('admin.patients.show', $user->patientProfile))
            ->assertSee('Akun login pasien ikut dinonaktifkan');

        $this->get(route('admin.patients.show', $walkIn))
            ->assertDontSee('Akun login pasien ikut dinonaktifkan');
    }
}
