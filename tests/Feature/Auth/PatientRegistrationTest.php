<?php

namespace Tests\Feature\Auth;

use App\Enums\BookingChannel;
use App\Enums\Gender;
use App\Models\AuditLog;
use App\Models\PatientProfile;
use App\Models\User;
use App\Notifications\PatientWelcomeNotification;
use App\Services\Settings\SettingsService;
use Database\Seeders\SettingsSeeder;
use Exception;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use Tests\Support\InteractsWithPatients;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

/**
 * Registrasi pasien (#23, F5-R9): VAL-02, SEK-01, SEK-02, SEK-05, SEK-06, NTF-08 (HTTP), WLK-06 dan Review Focus
 * G.2, G.4, G.5. Layanan bersifat final sehingga galat dipicu lewat listener model/notifikasi (catatan teknis 5).
 */
class PatientRegistrationTest extends TestCase
{
    use InteractsWithPatients;
    use InteractsWithStaff;
    use RefreshDatabase;

    private const PASSWORD = 'Rahasia123';

    private const EMAIL_TAKEN = 'Email ini sudah terdaftar. Silakan masuk atau gunakan email lain.';

    private const SYSTEM_ERROR = 'Registrasi belum berhasil karena gangguan sistem. Silakan coba lagi.';

    /** @param  array<string, mixed>  $overrides */
    private function postRegister(array $overrides = []): TestResponse
    {
        return $this->from(route('register'))->post(route('register.store'), $this->registrationPayload($overrides));
    }

    private function registeredUser(): User
    {
        return User::query()->where('email', 'siti@contoh.test')->sole();
    }

    // --- tampilan form -----------------------------------------------------------------------

    public function test_register_page_renders_the_complete_form_for_guests(): void
    {
        $response = $this->get(route('register'));

        $response->assertOk()
            ->assertSee('<title>Daftar Akun Pasien — '.config('app.name').'</title>', false)
            ->assertSee('Daftar Akun Pasien')
            ->assertSee('Buat akun untuk mendaftar kunjungan rawat jalan secara online.')
            ->assertSee('action="'.route('register.store').'"', false)
            ->assertSee('<meta name="robots" content="noindex,nofollow">', false)
            ->assertSeeInOrder([
                'name="full_name"', 'name="birth_date"', 'name="gender"', 'name="phone"', 'name="email"',
                'name="address"', 'name="password"', 'name="password_confirmation"', 'name="consent"',
            ], false)
            ->assertSee('>Nama lengkap <span', false)
            ->assertSee('>Tanggal lahir <span', false)
            ->assertSee('Jenis kelamin')
            ->assertSee('>Nomor telepon <span', false)
            ->assertSee('>Email <span', false)
            ->assertSee('>Alamat <span', false)
            ->assertSee('>Password <span', false)
            ->assertSee('>Konfirmasi password <span', false)
            ->assertSee('Nomor seluler aktif, mis. 081234567890.')
            ->assertSee('Minimal 8 karakter, berisi huruf dan angka.')
            ->assertSee('autocomplete="name"', false)
            ->assertSee('autocomplete="bday"', false)
            ->assertSee('autocomplete="tel"', false)
            ->assertSee('autocomplete="email"', false)
            ->assertSee('autocomplete="new-password"', false)
            ->assertSee('max="'.now()->subDay()->toDateString().'"', false)
            ->assertSee('name="gender" value="L"', false)
            ->assertSee('name="gender" value="P"', false)
            ->assertSee('Laki-laki')
            ->assertSee('Perempuan');
        $this->assertSame(1, substr_count($response->getContent(), '<h1'));
        $this->assertSame(1, substr_count($response->getContent(), 'class="auth-card'), 'Satu permukaan kartu.');
        $response->assertSee('class="auth-card auth-card-wide mx-auto"', false);
    }

    public function test_register_page_has_the_privacy_consent_link_in_a_new_tab_and_no_identity_or_bpjs_fields(): void
    {
        $response = $this->get(route('register'))->assertOk();

        $response->assertSee('Saya telah membaca dan menyetujui')
            ->assertSee('<a href="'.route('privacy').'" target="_blank" rel="noopener">Kebijakan Privasi</a>', false)
            ->assertSee('(terbuka di tab baru)')
            ->assertSee('id="consent" name="consent" value="1"', false)
            ->assertDontSee('name="nik"', false)
            ->assertDontSee('name="bpjs_number"', false)
            ->assertDontSee('NIK')
            ->assertDontSee('BPJS');
        $this->assertDoesNotMatchRegularExpression('/<input[^>]*type="password"[^>]*\svalue=/', $response->getContent());
        $this->assertDoesNotMatchRegularExpression('/<input[^>]*name="consent"[^>]*\schecked/', $response->getContent());
    }

    public function test_register_and_login_pages_link_to_each_other(): void
    {
        $this->get(route('register'))
            ->assertSee('Sudah punya akun?')
            ->assertSee('<a href="'.route('login').'">Masuk</a>', false);

        $this->get(route('login'))
            ->assertSee('Belum punya akun?')
            ->assertSee('<a href="'.route('register').'">Daftar</a>', false);
    }

    public function test_registration_guide_now_offers_the_account_button(): void
    {
        $this->get(route('registration-guide'))
            ->assertOk()
            ->assertSee('href="'.route('register').'"', false)
            ->assertSee('>Daftar Akun</a>', false);
    }

    // --- registrasi sukses -------------------------------------------------------------------

    public function test_successful_registration_creates_the_account_and_profile_logs_in_and_redirects_to_the_dashboard(): void
    {
        Notification::fake();
        $this->travelTo('2026-10-08 09:30:00');

        $this->postRegister()
            ->assertRedirect(route('patient.dashboard'))
            ->assertSessionHas('success', 'Registrasi berhasil. Selamat datang, Siti Rahma.');

        $user = $this->registeredUser();
        $this->assertAuthenticatedAs($user);
        $this->assertSame('Siti Rahma', $user->name);
        $this->assertSame('081234567890', $user->phone);
        $this->assertTrue($user->isPatient());
        $this->assertTrue($user->is_active);
        $this->assertFalse($user->must_change_password);
        $this->assertNull($user->email_verified_at, 'K-22: tanpa verifikasi wajib.');
        $this->assertSame('2026-10-08 09:30:00', $user->last_login_at->format('Y-m-d H:i:s'));

        $profile = PatientProfile::query()->where('user_id', $user->id)->sole();
        $this->assertSame('Siti Rahma', $profile->full_name);
        $this->assertSame('1990-03-12', $profile->birth_date->format('Y-m-d'));
        $this->assertSame(Gender::Female, $profile->gender);
        $this->assertSame('081234567890', $profile->phone);
        $this->assertSame('siti@contoh.test', $profile->email);
        $this->assertSame('Jl. Merpati No. 5, Pamulang', $profile->address);
        $this->assertSame(BookingChannel::Online, $profile->registration_channel);
        $this->assertTrue($profile->is_active);
        $this->assertNull($profile->created_by_user_id);
        $this->assertSame('1.0', $profile->consent_version);
        $this->assertSame('2026-10-08 09:30:00', $profile->consent_at->format('Y-m-d H:i:s'));
        $this->assertSame('P'.str_pad((string) $profile->id, 6, '0', STR_PAD_LEFT), $profile->patient_number);
        $this->assertSame(1, User::query()->count());
        $this->assertSame(1, PatientProfile::query()->count());
    }

    public function test_after_registration_the_dashboard_greets_the_new_patient_with_the_flash_message(): void
    {
        Notification::fake();

        $html = $this->from(route('register'))->followingRedirects()
            ->post(route('register.store'), $this->registrationPayload())
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Registrasi berhasil. Selamat datang, Siti Rahma.', $html);
        $this->assertStringContainsString('Halo, Siti Rahma', $html);
        $this->assertStringContainsString('Belum ada pendaftaran', $html);
    }

    /** SEK-02: ID sesi berubah setelah registrasi. */
    public function test_session_id_changes_after_registration(): void
    {
        Notification::fake();
        $this->get(route('register'));
        $before = session()->getId();

        $this->postRegister()->assertRedirect(route('patient.dashboard'));

        $this->assertNotSame($before, session()->getId());
    }

    public function test_registration_returns_to_the_intended_protected_url_with_its_query_intact(): void
    {
        Notification::fake();

        $this->get('/pasien?contoh=utuh')->assertRedirect(route('login'));
        $this->postRegister()->assertRedirect(url('/pasien?contoh=utuh'));
    }

    public function test_registration_ignores_an_intended_admin_url(): void
    {
        Notification::fake();

        $this->get('/admin/dokter')->assertRedirect(route('admin.login'));
        $this->postRegister()->assertRedirect(route('patient.dashboard'));
    }

    public function test_consent_version_comes_from_the_privacy_setting(): void
    {
        Notification::fake();
        $this->seed(SettingsSeeder::class);
        $settings = app(SettingsService::class);
        $settings->forget();
        $settings->set('privacy.consent_version', '2.3');

        $this->postRegister()->assertRedirect(route('patient.dashboard'));

        $this->assertSame('2.3', PatientProfile::query()->sole()->consent_version);
    }

    public function test_normalised_values_are_what_gets_stored(): void
    {
        Notification::fake();

        $this->postRegister([
            'full_name' => '  Siti Rahma ',
            'email' => '  Siti@Contoh.TEST ',
            'phone' => '+62 812 3456 7890',
        ])->assertRedirect(route('patient.dashboard'));

        $user = $this->registeredUser();
        $this->assertSame('Siti Rahma', $user->name);
        $this->assertSame('+6281234567890', $user->phone);
        $this->assertSame('+6281234567890', PatientProfile::query()->sole()->phone);
        $this->assertSame('siti@contoh.test', PatientProfile::query()->sole()->email);
    }

    /** WLK-06: tidak ada pencocokan otomatis dengan profil walk-in bernama dan bertanggal lahir sama. */
    public function test_registration_never_merges_with_an_existing_walk_in_profile(): void
    {
        Notification::fake();
        $walkIn = PatientProfile::factory()->create(['full_name' => 'Siti Rahma', 'birth_date' => '1990-03-12', 'phone' => '081234567890']);

        $this->postRegister()->assertRedirect(route('patient.dashboard'));

        $this->assertNull($walkIn->fresh()->user_id);
        $this->assertSame(2, PatientProfile::query()->count());
        $this->assertSame($this->registeredUser()->id, PatientProfile::query()->whereKeyNot($walkIn->id)->sole()->user_id);
    }

    // --- SEK-01: password ------------------------------------------------------------------

    public function test_the_password_is_stored_as_a_bcrypt_hash_and_never_appears_anywhere(): void
    {
        Notification::fake();

        $html = $this->from(route('register'))->followingRedirects()
            ->post(route('register.store'), $this->registrationPayload())
            ->assertOk()
            ->getContent();

        $user = $this->registeredUser();
        $this->assertNotSame(self::PASSWORD, $user->getRawOriginal('password'));
        $this->assertStringStartsWith('$2y$', $user->getRawOriginal('password'));
        $this->assertTrue(Hash::check(self::PASSWORD, $user->password));

        $this->assertStringNotContainsString(self::PASSWORD, $html);
        $this->assertStringNotContainsString(self::PASSWORD, json_encode(session()->all(), JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString(self::PASSWORD, json_encode(DB::table('audit_logs')->get(), JSON_THROW_ON_ERROR));
        $this->assertGreaterThan(0, AuditLog::query()->where('action', 'patient.registered')->count(), 'Audit patient.registered dari Task 2 harus ada.');
    }

    public function test_a_failed_submission_never_flashes_the_password_back(): void
    {
        $html = $this->from(route('register'))->followingRedirects()
            ->post(route('register.store'), $this->registrationPayload(['consent' => null]))
            ->assertOk()
            ->getContent();

        $this->assertNull(session('_old_input.password'));
        $this->assertNull(session('_old_input.password_confirmation'));
        $this->assertStringNotContainsString(self::PASSWORD, $html);
        $this->assertStringNotContainsString(self::PASSWORD, json_encode(session()->all(), JSON_THROW_ON_ERROR));
    }

    // --- VAL-02: validasi di tingkat HTTP, tanpa baris parsial -------------------------------

    public function test_invalid_registration_shows_specific_messages_keeps_the_input_and_creates_no_rows(): void
    {
        Notification::fake();
        $this->createPatient(['email' => 'siti@contoh.test']);
        $users = User::query()->count();
        $profiles = PatientProfile::query()->count();

        $this->postRegister([
            'password' => 'abc',
            'password_confirmation' => 'abc',
            'birth_date' => now()->addDay()->toDateString(),
            'consent' => null,
        ])
            ->assertRedirect(route('register'))
            ->assertSessionHasErrors([
                'email' => self::EMAIL_TAKEN,
                'password' => 'Password minimal 8 karakter.',
                'birth_date' => 'Tanggal lahir harus sebelum hari ini.',
                'consent' => 'Anda harus menyetujui Kebijakan Privasi untuk melanjutkan.',
            ]);

        $this->assertSame($users, User::query()->count());
        $this->assertSame($profiles, PatientProfile::query()->count());
        $this->assertSame('Siti Rahma', session('_old_input.full_name'));
        $this->assertNull(session('_old_input.password'));
        Notification::assertNothingSent();
        $this->assertGuest();
    }

    public function test_the_form_shows_field_errors_and_keeps_choices_after_a_failed_submission(): void
    {
        $html = $this->from(route('register'))->followingRedirects()
            ->post(route('register.store'), $this->registrationPayload(['consent' => null]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-errors-summary', $html);
        $this->assertStringContainsString('id="consent-error"', $html);
        $this->assertStringContainsString('Anda harus menyetujui Kebijakan Privasi untuk melanjutkan.', $html);
        $this->assertMatchesRegularExpression('/<input[^>]*name="consent"[^>]*aria-invalid="true"/', $html);
        $this->assertMatchesRegularExpression('/<input[^>]*name="gender"[^>]*value="P"[^>]*\schecked/', $html);
        $this->assertStringContainsString('value="siti@contoh.test"', $html);
        $this->assertStringContainsString('value="0812-3456-7890"', $html); // old input = ketikan mentah, bukan hasil normalisasi
        $this->assertStringNotContainsString('autofocus', $html);
    }

    /** Review Focus G.5: email ganda beda huruf besar ditolak. */
    public function test_an_email_that_differs_only_by_letter_case_is_rejected(): void
    {
        $this->createPatient(['email' => 'siti@contoh.test']);

        $this->postRegister(['email' => 'Siti@Contoh.test'])
            ->assertRedirect(route('register'))
            ->assertSessionHasErrors(['email' => self::EMAIL_TAKEN]);

        $this->assertSame(1, User::query()->count());
    }

    /** Review Focus G.2: larik dan nilai liar menghasilkan galat field, bukan 500, dan halaman dirender ulang. */
    public function test_array_and_wild_values_give_field_errors_without_a_500_or_partial_rows(): void
    {
        $arrays = [
            'full_name' => ['Siti'], 'email' => ['siti@contoh.test'], 'phone' => ['0812'], 'birth_date' => ['1990-03-12'],
            'gender' => ['P'], 'address' => ['Jl. Merpati No. 5'], 'password' => ['Rahasia123'],
            'password_confirmation' => ['Rahasia123'], 'consent' => ['1'],
        ];

        $html = $this->from(route('register'))->followingRedirects()
            ->post(route('register.store'), $arrays)
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-errors-summary', $html);

        $this->postRegister(['gender' => 'X', 'birth_date' => '2099-01-01'])
            ->assertSessionHasErrors(['gender' => 'Jenis kelamin tidak valid.', 'birth_date' => 'Tanggal lahir harus sebelum hari ini.']);
        $this->postRegister(['birth_date' => '31-12-1990'])
            ->assertSessionHasErrors(['birth_date' => 'Format tanggal lahir tidak valid.']);

        $this->assertSame(0, User::query()->count());
        $this->assertSame(0, PatientProfile::query()->count());
        $this->assertSame(0, AuditLog::query()->count());
    }

    // --- atomisitas dan galat sistem (Review Focus G.5) --------------------------------------

    public function test_a_system_error_while_creating_the_profile_rolls_everything_back_and_keeps_the_input(): void
    {
        Notification::fake();
        PatientProfile::creating(function (): void {
            throw new RuntimeException('Gangguan uji.');
        });

        $this->postRegister()
            ->assertRedirect(route('register'))
            ->assertSessionHas('error', self::SYSTEM_ERROR)
            ->assertSessionHas('_old_input.email', 'siti@contoh.test')
            ->assertSessionHas('_old_input.full_name', 'Siti Rahma')
            ->assertSessionMissing('_old_input.password')
            ->assertSessionMissing('_old_input.password_confirmation');

        $this->assertSame(0, User::query()->count(), 'Akun harus ikut di-rollback.');
        $this->assertSame(0, PatientProfile::query()->count());
        $this->assertSame(0, AuditLog::query()->count());
        Notification::assertNothingSent();
        $this->assertGuest();
    }

    public function test_a_unique_constraint_race_shows_the_email_message_instead_of_a_500(): void
    {
        Notification::fake();
        User::creating(function (): void {
            throw new UniqueConstraintViolationException('mariadb', 'insert into `users` (`email`) values (?)', ['siti@contoh.test'], new Exception('Duplicate entry'));
        });

        $this->postRegister()
            ->assertRedirect(route('register'))
            ->assertSessionHasErrors(['email' => self::EMAIL_TAKEN])
            ->assertSessionHas('_old_input.email', 'siti@contoh.test')
            ->assertSessionMissing('_old_input.password')
            ->assertSessionMissing('error');

        $this->assertSame(0, User::query()->count());
        $this->assertSame(0, PatientProfile::query()->count());
        Notification::assertNothingSent();
        $this->assertGuest();
    }

    // --- NTF-08 di tingkat HTTP ---------------------------------------------------------------

    public function test_the_welcome_notification_is_sent_once_by_mail_after_a_successful_registration(): void
    {
        Notification::fake();

        $this->postRegister()->assertRedirect(route('patient.dashboard'));

        Notification::assertSentToTimes($this->registeredUser(), PatientWelcomeNotification::class, 1);
        Notification::assertSentTo(
            $this->registeredUser(),
            PatientWelcomeNotification::class,
            fn (PatientWelcomeNotification $notification, array $channels): bool => $channels === ['mail'],
        );
        Notification::assertCount(1);
    }

    public function test_no_notification_is_sent_when_validation_fails(): void
    {
        Notification::fake();

        $this->postRegister(['consent' => null])->assertSessionHasErrors('consent');

        Notification::assertNothingSent();
    }

    public function test_a_failing_welcome_email_does_not_cancel_the_registration(): void
    {
        Event::listen(NotificationSending::class, function (): void {
            throw new RuntimeException('Server email tidak tersedia.');
        });

        $this->postRegister()
            ->assertRedirect(route('patient.dashboard'))
            ->assertSessionHas('success', 'Registrasi berhasil. Selamat datang, Siti Rahma.');

        $user = $this->registeredUser();
        $this->assertAuthenticatedAs($user);
        $this->assertSame(1, PatientProfile::query()->where('user_id', $user->id)->count());
    }

    // --- throttle dan CSRF -------------------------------------------------------------------

    /** SEK-05: registrasi ke-6 per jam per IP ditolak (D-23: 302 + flash + Retry-After untuk POST). */
    public function test_sixth_registration_attempt_per_hour_is_throttled_and_creates_nothing(): void
    {
        Notification::fake();

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->from(route('register'))
                ->post(route('register.store'), [])
                ->assertRedirect(route('register'))
                ->assertSessionHasErrors('email');
        }

        $this->postRegister()
            ->assertStatus(302)
            ->assertRedirect(route('register'))
            ->assertHeader('Retry-After')
            ->assertSessionHas('error', fn ($message) => preg_match('/^Terlalu banyak percobaan\. Silakan coba lagi dalam \d+ detik\.$/', $message) === 1)
            ->assertSessionMissing('_old_input.password');

        $this->assertSame(0, User::query()->count());
        $this->assertSame(0, PatientProfile::query()->count());
        $this->assertGuest();
        Notification::assertNothingSent();
    }

    public function test_a_json_request_over_the_limit_gets_status_429(): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postJson(route('register.store'), [])->assertStatus(422);
        }

        $this->postJson(route('register.store'), $this->registrationPayload())
            ->assertStatus(429)
            ->assertHeader('Retry-After');

        $this->assertSame(0, User::query()->count());
    }

    /** SEK-06/ERR-03: tanpa token CSRF, pasien kembali ke form dengan pesan sesi berakhir dan tanpa baris. */
    public function test_missing_csrf_token_redirects_back_with_a_session_expired_message_and_creates_nothing(): void
    {
        $this->app['env'] = 'local'; // mengaktifkan kembali ValidateCsrfToken (dilewati saat env `testing`)

        $this->from(route('register'))
            ->post(route('register.store'), $this->registrationPayload())
            ->assertRedirect(route('register'))
            ->assertSessionHas('error', 'Sesi formulir Anda telah berakhir. Silakan periksa isian Anda lalu kirim ulang.')
            ->assertSessionMissing('_old_input.password')
            ->assertSessionMissing('_old_input.password_confirmation');

        $this->assertSame(0, User::query()->count());
        $this->assertGuest();
    }

    // --- akun yang sudah login (AKS-02) ------------------------------------------------------

    public function test_logged_in_accounts_opening_the_registration_page_go_to_their_dashboard(): void
    {
        $this->actingAs($this->createPatient())->get(route('register'))->assertRedirect(route('patient.dashboard'));
        $this->actingAs($this->createStaff('content-admin'))->get(route('register'))->assertRedirect(route('admin.dashboard'));
    }

    public function test_logged_in_patient_posting_the_registration_form_is_redirected_and_nothing_is_created(): void
    {
        $this->actingAs($this->createPatient());
        $users = User::query()->count();

        $this->postRegister(['email' => 'lain@contoh.test'])->assertRedirect(route('patient.dashboard'));

        $this->assertSame($users, User::query()->count());
    }

    // --- route -------------------------------------------------------------------------------

    public function test_registration_routes_have_the_expected_uri_method_and_middleware(): void
    {
        $expected = [
            'register' => ['GET', 'daftar', ['guest']],
            'register.store' => ['POST', 'daftar', ['guest', 'throttle:register']],
        ];

        foreach ($expected as $name => [$method, $uri, $middleware]) {
            $route = Route::getRoutes()->getByName($name);

            $this->assertNotNull($route, "Route {$name} belum terdaftar.");
            $this->assertContains($method, $route->methods(), $name);
            $this->assertSame($uri, $route->uri(), $name);
            $this->assertSame($middleware, array_values(array_diff(array_filter($route->gatherMiddleware(), 'is_string'), ['web'])), $name);
        }
    }
}
