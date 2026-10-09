<?php

namespace Tests\Feature\Admin;

use App\Enums\AppointmentStatus;
use App\Enums\BookingChannel;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\PatientProfile;
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
 * Hubungkan profil walk-in ke akun online (#37, F7A-R12, V-17): WLK-05 (sukses lewat HTTP), WLK-06 (semua penolakan,
 * Admin Konten 403, tanpa pencocokan otomatis saat registrasi), panel GET murni baca, profil nonaktif ditutup,
 * parameter `hubungkan` liar tanpa 500.
 */
class AdminPatientLinkAccountTest extends TestCase
{
    use InteractsWithMasterData;
    use InteractsWithPatients;
    use InteractsWithSchedules;
    use InteractsWithStaff;
    use RefreshDatabase;

    private const ALREADY_LINKED = 'Profil ini sudah terhubung ke sebuah akun.';

    private const NOT_FOUND = 'Akun pasien dengan email tersebut tidak ditemukan.';

    private const INACTIVE_ACCOUNT = 'Akun pasien tidak aktif.';

    private const HAS_HISTORY = 'Akun ini sudah memiliki riwayat pendaftaran. Penggabungan dua profil berriwayat belum didukung.';

    private const IDENTITY = 'Anda harus menegaskan bahwa identitas pasien telah diverifikasi di loket.';

    private const INACTIVE_PROFILE = 'Profil pasien tidak aktif. Aktifkan profil terlebih dahulu.';

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

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function walkIn(array $attributes = []): PatientProfile
    {
        return PatientProfile::factory()->create(array_merge([
            'full_name' => 'Siti Rahma',
            'patient_number' => 'P000201',
            'birth_date' => '1990-03-12',
            'phone' => '081200000001',
        ], $attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function account(array $attributes = []): User
    {
        return User::factory()->patient()->create(array_merge([
            'email' => 'siti@contoh.test',
            'name' => 'Siti Akun',
            'phone' => '081200000009',
        ], $attributes));
    }

    private function book(PatientProfile $profile, AppointmentStatus $status = AppointmentStatus::Confirmed): Appointment
    {
        $session = $this->createSession([
            'doctor_id' => $this->doctor->id,
            'clinic_id' => $this->clinic->id,
            'session_date' => '2026-10-'.str_pad((string) (10 + ++$this->sequence), 2, '0', STR_PAD_LEFT),
            'start_time' => '09:00:00',
            'end_time' => '12:00:00',
            'doctor_schedule_id' => null,
        ]);

        return $this->bookAppointment($session, $status, [
            'booking_code' => sprintf('UPH-ABCD-%04d', $this->sequence),
            'patient_profile_id' => $profile->id,
            'channel' => BookingChannel::Loket,
        ]);
    }

    private function panelUrl(PatientProfile $profile, string $email): string
    {
        return route('admin.patients.show', ['patient' => $profile, 'hubungkan' => $email]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function link(PatientProfile $profile, array $overrides = [], ?User $user = null, string $email = 'siti@contoh.test'): TestResponse
    {
        return $this->actingAs($user ?? $this->officer)
            ->from($this->panelUrl($profile, $email))
            ->post(route('admin.patients.link-account', $profile), array_merge([
                'account_email' => $email,
                'identity_verified' => '1',
            ], $overrides));
    }

    private function assertNothingLinked(PatientProfile $profile): void
    {
        $this->assertNull($profile->fresh()->user_id);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'patient.account_linked']);
    }

    // --- WLK-05: sukses ----------------------------------------------------------------------

    public function test_linking_succeeds_and_the_patient_sees_the_walk_in_history_wlk_05(): void
    {
        $walkIn = $this->walkIn();
        $appointment = $this->book($walkIn);
        $account = $this->createPatient(['email' => 'siti@contoh.test', 'name' => 'Siti Akun'], ['patient_number' => 'P000300']);
        $emptyProfileId = $account->patientProfile->id;

        $this->link($walkIn)
            ->assertRedirect(route('admin.patients.show', $walkIn))
            ->assertSessionHas('success', 'Profil pasien P000201 berhasil dihubungkan ke akun siti@contoh.test.');

        $fresh = $walkIn->fresh();

        $this->assertSame($account->id, (int) $fresh->user_id);
        $this->assertSame('siti@contoh.test', $fresh->email);
        $this->assertSame('P000201', $fresh->patient_number);
        $this->assertDatabaseMissing('patient_profiles', ['id' => $emptyProfileId]);
        $this->assertSame($walkIn->id, $appointment->fresh()->patient_profile_id);

        $this->app['auth']->forgetGuards();

        $this->actingAs($account->fresh())
            ->get(route('patient.appointments.index'))
            ->assertOk()
            ->assertSee($appointment->booking_code);
    }

    public function test_the_audit_row_records_the_removed_profile_and_carries_no_personal_data(): void
    {
        $walkIn = $this->walkIn();
        $account = $this->createPatient(['email' => 'siti@contoh.test', 'name' => 'Siti Akun'], ['patient_number' => 'P000300']);

        $this->link($walkIn)->assertSessionHasNoErrors();

        $log = AuditLog::query()->where('action', 'patient.account_linked')->sole();
        $encoded = (string) json_encode($log->toArray());

        $this->assertSame($this->officer->id, (int) $log->user_id);
        $this->assertSame('P000300', $log->old_values['removed_profile']['patient_number']);
        $this->assertSame($walkIn->id, $log->new_values['profile_id']);
        $this->assertSame($account->id, $log->new_values['user_id']);
        $this->assertSame('Profil pasien P000201 dihubungkan ke akun pasien.', $log->description);
        $this->assertStringNotContainsString('siti@contoh.test', $encoded);
        $this->assertStringNotContainsString('Siti Akun', $encoded);
    }

    public function test_an_account_without_any_profile_can_be_linked(): void
    {
        $walkIn = $this->walkIn();
        $account = $this->account();

        $this->link($walkIn)->assertSessionHasNoErrors();

        $this->assertSame($account->id, (int) $walkIn->fresh()->user_id);
        $this->assertNull(AuditLog::query()->where('action', 'patient.account_linked')->sole()->old_values['removed_profile']);
    }

    public function test_the_email_is_normalised_before_the_lookup(): void
    {
        $walkIn = $this->walkIn();
        $this->account();

        $this->link($walkIn, ['account_email' => '  SITI@Contoh.Test '])->assertSessionHasNoErrors();

        $this->assertNotNull($walkIn->fresh()->user_id);
    }

    public function test_super_admin_can_link(): void
    {
        $walkIn = $this->walkIn();
        $this->account();

        $this->link($walkIn, [], $this->createStaff('super-admin'))->assertSessionHasNoErrors();

        $this->assertNotNull($walkIn->fresh()->user_id);
    }

    // --- panel (GET) -------------------------------------------------------------------------

    public function test_the_detail_page_offers_the_lookup_form_and_the_action_button_for_an_unlinked_active_profile(): void
    {
        $walkIn = $this->walkIn();

        $this->actingAs($this->officer)
            ->get(route('admin.patients.show', $walkIn))
            ->assertOk()
            ->assertSee('href="#hubungkan-akun"', false)
            ->assertSee('id="hubungkan-akun"', false)
            ->assertSee('Hubungkan ke akun online')
            ->assertSee('name="hubungkan"', false)
            ->assertSee('Email akun pasien')
            ->assertDontSee('Periksa kecocokan identitas')
            ->assertDontSee('name="account_email"', false);
    }

    public function test_an_eligible_account_shows_the_comparison_and_the_confirmation_form(): void
    {
        $walkIn = $this->walkIn();
        $this->createPatient(
            ['email' => 'siti@contoh.test', 'name' => 'Siti Akun', 'phone' => '081200000009'],
            ['patient_number' => 'P000300', 'birth_date' => '1991-04-15'],
        );

        $this->actingAs($this->officer)
            ->get($this->panelUrl($walkIn, 'siti@contoh.test'))
            ->assertOk()
            ->assertSee('Periksa kecocokan identitas')
            ->assertSee('<th scope="col">Profil walk-in</th>', false)
            ->assertSee('<th scope="col">Akun online</th>', false)
            ->assertSee('Siti Rahma')
            ->assertSee('Siti Akun')
            ->assertSee('12/03/1990')
            ->assertSee('15/04/1991')
            ->assertSee('081200000001')
            ->assertSee('081200000009')
            ->assertSee('P000300')
            ->assertSee('action="'.route('admin.patients.link-account', $walkIn).'"', false)
            ->assertSee('name="account_email" value="siti@contoh.test"', false)
            ->assertSee('name="identity_verified"', false)
            ->assertSee('Saya telah memverifikasi identitas pasien di loket')
            ->assertSee('Hubungkan Akun');
    }

    public function test_the_get_panel_writes_nothing(): void
    {
        $walkIn = $this->walkIn();
        $this->createPatient(['email' => 'siti@contoh.test']);
        $snapshot = fn (): string => json_encode([
            DB::table('patient_profiles')->orderBy('id')->get(),
            DB::table('users')->orderBy('id')->get(),
            DB::table('audit_logs')->count(),
            DB::table('appointments')->count(),
        ]);
        $before = $snapshot();

        $this->actingAs($this->officer);
        $this->get($this->panelUrl($walkIn, 'siti@contoh.test'))->assertOk();
        $this->get($this->panelUrl($walkIn, 'tidak-ada@contoh.test'))->assertOk();
        $this->get($this->panelUrl($walkIn, 'bukan-email'))->assertOk();

        $this->assertSame($before, $snapshot());
    }

    public function test_an_unknown_email_shows_only_the_not_found_message(): void
    {
        $walkIn = $this->walkIn();

        $this->actingAs($this->officer)
            ->get($this->panelUrl($walkIn, 'tidak-ada@contoh.test'))
            ->assertOk()
            ->assertSee(self::NOT_FOUND)
            ->assertDontSee('Periksa kecocokan identitas')
            ->assertDontSee('name="account_email"', false);
    }

    public function test_a_staff_email_is_reported_as_not_found_without_exposing_the_staff_account(): void
    {
        $walkIn = $this->walkIn();
        $this->createStaff('super-admin', ['email' => 'staf@contoh.test', 'name' => 'Nama Staf Rahasia']);

        $this->actingAs($this->officer)
            ->get($this->panelUrl($walkIn, 'staf@contoh.test'))
            ->assertOk()
            ->assertSee(self::NOT_FOUND)
            ->assertDontSee('Nama Staf Rahasia')
            ->assertDontSee('Periksa kecocokan identitas');
    }

    public function test_an_inactive_account_shows_only_the_inactive_message(): void
    {
        $walkIn = $this->walkIn();
        User::factory()->patient()->inactive()->create(['email' => 'mati@contoh.test', 'name' => 'Nama Akun Rahasia']);

        $this->actingAs($this->officer)
            ->get($this->panelUrl($walkIn, 'mati@contoh.test'))
            ->assertOk()
            ->assertSee(self::INACTIVE_ACCOUNT)
            ->assertDontSee('Nama Akun Rahasia')
            ->assertDontSee('Periksa kecocokan identitas')
            ->assertDontSee('name="account_email"', false);
    }

    public function test_an_account_with_history_shows_only_the_history_message(): void
    {
        $walkIn = $this->walkIn();
        $account = $this->createPatient(['email' => 'riwayat@contoh.test', 'name' => 'Nama Riwayat Rahasia']);
        $this->book($account->patientProfile, AppointmentStatus::Cancelled);

        $this->actingAs($this->officer)
            ->get($this->panelUrl($walkIn, 'riwayat@contoh.test'))
            ->assertOk()
            ->assertSee(self::HAS_HISTORY)
            ->assertDontSee('Nama Riwayat Rahasia')
            ->assertDontSee('Periksa kecocokan identitas')
            ->assertDontSee('name="account_email"', false);
    }

    public function test_an_invalid_email_format_is_reported_without_a_lookup(): void
    {
        $walkIn = $this->walkIn();

        $this->actingAs($this->officer)
            ->get($this->panelUrl($walkIn, 'bukan-email'))
            ->assertOk()
            ->assertSee('Format email tidak valid.')
            ->assertDontSee(self::NOT_FOUND);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function hostileLookups(): array
    {
        return [
            'larik' => ['hubungkan[]=x'],
            'hash larik' => ['hubungkan[a][b]=x'],
            'kosong' => ['hubungkan='],
            'spasi' => ['hubungkan=%20%20'],
            'null byte' => ['hubungkan=%00'],
            'sangat panjang' => ['hubungkan='.str_repeat('a', 400).'%40contoh.test'],
            'markup' => ['hubungkan=%3Cscript%3Ealert(1)%3C%2Fscript%3E'],
        ];
    }

    #[DataProvider('hostileLookups')]
    public function test_a_hostile_lookup_parameter_never_causes_a_500_or_leaks_markup(string $query): void
    {
        $walkIn = $this->walkIn();

        $response = $this->actingAs($this->officer)
            ->get('/admin/pasien/'.$walkIn->id.'?'.$query)
            ->assertOk();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $response->getContent());
    }

    public function test_the_lookup_term_is_echoed_escaped(): void
    {
        $walkIn = $this->walkIn();

        $response = $this->actingAs($this->officer)
            ->get($this->panelUrl($walkIn, '"><script>alert(1)</script>'))
            ->assertOk();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $response->getContent());
    }

    // --- profil sudah terhubung atau nonaktif ---------------------------------------------------

    public function test_a_linked_profile_shows_no_button_and_no_panel(): void
    {
        $linked = PatientProfile::factory()->withAccount()->create(['full_name' => 'Pasien Berakun']);
        $this->account();

        $this->actingAs($this->officer)
            ->get($this->panelUrl($linked, 'siti@contoh.test'))
            ->assertOk()
            ->assertDontSee('href="#hubungkan-akun"', false)
            ->assertDontSee('id="hubungkan-akun"', false)
            ->assertDontSee('name="hubungkan"', false)
            ->assertDontSee('Siti Akun');
    }

    public function test_an_inactive_profile_shows_only_the_activate_first_text_and_no_panel(): void
    {
        $walkIn = $this->walkIn(['is_active' => false]);
        $this->account();

        $this->actingAs($this->officer)
            ->get($this->panelUrl($walkIn, 'siti@contoh.test'))
            ->assertOk()
            ->assertSee('Aktifkan profil terlebih dahulu untuk menghubungkannya ke akun online.')
            ->assertDontSee('href="#hubungkan-akun"', false)
            ->assertDontSee('id="hubungkan-akun"', false)
            ->assertDontSee('name="hubungkan"', false)
            ->assertDontSee('Periksa kecocokan identitas')
            ->assertDontSee('Siti Akun');
    }

    public function test_posting_for_an_inactive_profile_is_refused_without_calling_the_linker(): void
    {
        $walkIn = $this->walkIn(['is_active' => false]);
        $this->account();

        $this->link($walkIn)
            ->assertRedirect($this->panelUrl($walkIn, 'siti@contoh.test'))
            ->assertSessionHasErrors(['account_email' => self::INACTIVE_PROFILE]);

        $this->assertNothingLinked($walkIn);
        $this->assertFalse($walkIn->fresh()->is_active);

        $this->link($walkIn, [], $this->createStaff('super-admin'))
            ->assertSessionHasErrors(['account_email' => self::INACTIVE_PROFILE]);

        $this->assertNothingLinked($walkIn);
    }

    public function test_posting_for_a_linked_profile_is_forbidden_for_the_officer_and_refused_for_super_admin(): void
    {
        $linked = PatientProfile::factory()->withAccount()->create();
        $other = $this->account();

        $this->link($linked)->assertForbidden();

        $this->link($linked, [], $this->createStaff('super-admin'))
            ->assertSessionHasErrors(['account_email' => self::ALREADY_LINKED]);

        $this->assertNotSame($other->id, (int) $linked->fresh()->user_id);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'patient.account_linked']);
    }

    // --- WLK-06: penolakan lewat POST ---------------------------------------------------------

    public function test_an_account_with_history_is_refused(): void
    {
        $walkIn = $this->walkIn();
        $account = $this->createPatient(['email' => 'siti@contoh.test']);
        $this->book($account->patientProfile);

        $this->link($walkIn)
            ->assertRedirect($this->panelUrl($walkIn, 'siti@contoh.test'))
            ->assertSessionHasErrors(['account_email' => self::HAS_HISTORY]);

        $this->assertNothingLinked($walkIn);
        $this->assertDatabaseHas('patient_profiles', ['id' => $account->patientProfile->id]);
    }

    public function test_an_inactive_account_is_refused(): void
    {
        $walkIn = $this->walkIn();
        User::factory()->patient()->inactive()->create(['email' => 'siti@contoh.test']);

        $this->link($walkIn)->assertSessionHasErrors(['account_email' => self::INACTIVE_ACCOUNT]);

        $this->assertNothingLinked($walkIn);
    }

    public function test_a_staff_or_unknown_email_is_refused_as_not_found(): void
    {
        $walkIn = $this->walkIn();
        $this->createStaff('super-admin', ['email' => 'staf@contoh.test']);

        $this->link($walkIn, [], null, 'staf@contoh.test')->assertSessionHasErrors(['account_email' => self::NOT_FOUND]);
        $this->link($walkIn, [], null, 'tidak-ada@contoh.test')->assertSessionHasErrors(['account_email' => self::NOT_FOUND]);

        $this->assertNothingLinked($walkIn);
    }

    public function test_the_identity_confirmation_is_required(): void
    {
        $walkIn = $this->walkIn();
        $this->account();

        foreach ([null, '', '0', 'false'] as $value) {
            $this->link($walkIn, ['identity_verified' => $value])
                ->assertRedirect($this->panelUrl($walkIn, 'siti@contoh.test'))
                ->assertSessionHasErrors(['identity_verified' => self::IDENTITY]);
        }

        $this->assertNothingLinked($walkIn);
    }

    /**
     * @return array<string, array{mixed, string, string}>
     */
    public static function invalidEmails(): array
    {
        return [
            'kosong' => ['', 'account_email', 'Email akun wajib diisi.'],
            'bukan email' => ['bukan-email', 'account_email', 'Format email tidak valid.'],
            'larik' => [['siti@contoh.test'], 'account_email', 'Format email tidak valid.'],
            'terlalu panjang' => [str_repeat('a', 250).'@contoh.test', 'account_email', 'Email maksimal 255 karakter.'],
        ];
    }

    #[DataProvider('invalidEmails')]
    public function test_the_account_email_is_validated(mixed $email, string $field, string $message): void
    {
        $walkIn = $this->walkIn();

        $this->link($walkIn, ['account_email' => $email])->assertSessionHasErrors([$field => $message]);

        $this->assertNothingLinked($walkIn);
    }

    public function test_the_panel_renders_the_post_error_when_the_form_is_resubmitted_without_the_checkbox(): void
    {
        $walkIn = $this->walkIn();
        $this->account();

        $this->actingAs($this->officer)
            ->followingRedirects()
            ->from($this->panelUrl($walkIn, 'siti@contoh.test'))
            ->post(route('admin.patients.link-account', $walkIn), ['account_email' => 'siti@contoh.test'])
            ->assertOk()
            ->assertSee(self::IDENTITY)
            ->assertSee('Periksa kecocokan identitas');
    }

    // --- akses -------------------------------------------------------------------------------

    public function test_admin_konten_patients_and_guests_cannot_post(): void
    {
        $walkIn = $this->walkIn();
        $this->account();
        $url = route('admin.patients.link-account', $walkIn);
        $data = ['account_email' => 'siti@contoh.test', 'identity_verified' => '1'];

        $this->actingAs($this->createStaff('content-admin'))->post($url, $data)->assertForbidden();
        $this->actingAs($this->createPatient())->post($url, $data)->assertForbidden();

        $this->app['auth']->forgetGuards();

        $this->post($url, $data)->assertRedirect(route('admin.login'));

        $this->assertNothingLinked($walkIn);
    }

    public function test_an_unknown_or_malformed_patient_id_answers_404(): void
    {
        $this->actingAs($this->officer);

        $data = ['account_email' => 'siti@contoh.test', 'identity_verified' => '1'];

        $this->post('/admin/pasien/999999/hubungkan-akun', $data)->assertNotFound();
        $this->post('/admin/pasien/abc/hubungkan-akun', $data)->assertNotFound();
    }

    public function test_the_route_uses_the_indonesian_segment(): void
    {
        $walkIn = $this->walkIn();

        $this->assertSame(url('/admin/pasien/'.$walkIn->id.'/hubungkan-akun'), route('admin.patients.link-account', $walkIn));
    }

    // --- WLK-06: tanpa pencocokan otomatis ----------------------------------------------------

    public function test_registering_with_the_same_name_and_birth_date_never_links_automatically(): void
    {
        $walkIn = $this->walkIn(['phone' => '081234567890']);

        $this->post(route('register.store'), $this->registrationPayload([
            'full_name' => 'Siti Rahma',
            'birth_date' => '1990-03-12',
            'phone' => '0812-3456-7890',
            'email' => 'baru@contoh.test',
        ]));

        $this->assertNull($walkIn->fresh()->user_id);
        $this->assertSame(2, PatientProfile::query()->count());
        $this->assertDatabaseMissing('audit_logs', ['action' => 'patient.account_linked']);
    }

    // --- HTML ---------------------------------------------------------------------------------

    public function test_the_panel_markup_has_labels_and_no_raw_output(): void
    {
        $walkIn = $this->walkIn();
        $this->account();

        $this->actingAs($this->officer)
            ->get($this->panelUrl($walkIn, 'siti@contoh.test'))
            ->assertOk()
            ->assertSee('aria-labelledby="hubungkan-akun-judul"', false)
            ->assertSee('<caption class="visually-hidden">Perbandingan data profil walk-in dan akun online</caption>', false);

        $source = (string) file_get_contents(resource_path('views/admin/patients/_link-panel.blade.php'));

        $this->assertStringNotContainsString('{!!', $source);
    }
}
