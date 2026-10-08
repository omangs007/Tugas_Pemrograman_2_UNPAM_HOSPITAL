<?php

namespace Tests\Feature\Auth;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InteractsWithPatients;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

/**
 * Login dan logout pasien (#24, F5-R6 sampai F5-R8): AKS-07, AKS-08, AKS-10, SEK-02, SEK-03, SEK-06 (419),
 * SEK-16 dan Review Focus G.1, G.2, G.4. Pola mengikuti AdminLoginTest.
 */
class PatientLoginTest extends TestCase
{
    use InteractsWithPatients;
    use InteractsWithStaff;
    use RefreshDatabase;

    private const WRONG = 'SalahSekali1';

    private function postLogin(string $email, ?string $password = null, array $extra = []): TestResponse
    {
        return $this->from(route('login'))
            ->post(route('login.store'), ['email' => $email, 'password' => $password ?? $this->patientPassword()] + $extra);
    }

    /** Pasien berprofil dengan email tetap `siti@contoh.test` dan password `Rahasia123`. */
    private function patientAccount(array $attributes = []): User
    {
        return $this->createPatient($attributes + ['email' => 'siti@contoh.test']);
    }

    /** @return array{0: int, 1: ?string, 2: array<int, string>} */
    private function outcome(string $email, ?string $password = null): array
    {
        $response = $this->postLogin($email, $password);

        return [$response->getStatusCode(), $response->headers->get('Location'), session('errors')->get('email')];
    }

    // --- tampilan form -----------------------------------------------------------------------

    public function test_login_page_renders_the_form_for_guests(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk()
            ->assertSee('<title>Masuk — '.config('app.name').'</title>', false)
            ->assertSee('Masuk ke Akun Pasien')
            ->assertSee('Masuk untuk mengelola profil dan pendaftaran kunjungan Anda.')
            ->assertSee('action="'.route('login.store').'"', false)
            ->assertSee('name="email"', false)
            ->assertSee('name="password"', false)
            ->assertSee('name="remember"', false)
            ->assertSee('Ingat saya')
            ->assertSee('<meta name="robots" content="noindex,nofollow">', false);
        $this->assertSame(1, substr_count($response->getContent(), '<h1'));
        $this->assertSame(1, substr_count($response->getContent(), 'class="auth-card'), 'Satu permukaan kartu, tanpa kelas lebar.');
        $this->assertStringNotContainsString('auth-card-wide', $response->getContent());
    }

    public function test_login_email_is_autofocused_unless_there_are_errors(): void
    {
        $html = $this->get(route('login'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<input[^>]*name="email"[^>]*\sautofocus/', $html);
        $this->assertSame(1, preg_match_all('/\sautofocus(?=[\s=>])/', $html), 'Hanya satu atribut autofocus.');

        $failed = $this->from(route('login'))->followingRedirects()
            ->post(route('login.store'), ['email' => 'tidak.ada@contoh.test', 'password' => self::WRONG])
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-errors-summary', $failed);
        $this->assertStringContainsString('Email atau password salah.', $failed);
        $this->assertStringNotContainsString('autofocus', $failed);
    }

    // --- login sukses ------------------------------------------------------------------------

    public function test_patient_can_log_in_and_lands_on_the_dashboard_without_any_audit_row(): void
    {
        $this->travelTo('2026-10-08 09:30:00');
        $patient = $this->patientAccount();
        $auditBefore = AuditLog::query()->count();

        $this->postLogin('siti@contoh.test')->assertRedirect(route('patient.dashboard'));

        $this->assertAuthenticatedAs($patient);
        $this->assertSame('2026-10-08 09:30:00', $patient->fresh()->last_login_at->format('Y-m-d H:i:s'));
        $this->assertSame($auditBefore, AuditLog::query()->count(), 'Login pasien tidak diaudit (F5-R6).');
        $this->get(route('patient.dashboard'))->assertOk();
    }

    /** SEK-02: ID sesi berubah setelah login. */
    public function test_session_id_changes_after_login(): void
    {
        $this->patientAccount();

        $this->get(route('login'));
        $before = session()->getId();

        $this->postLogin('siti@contoh.test')->assertRedirect(route('patient.dashboard'));

        $this->assertNotSame($before, session()->getId());
    }

    public function test_remember_me_sets_the_recaller_cookie_only_when_requested(): void
    {
        $this->patientAccount();
        $recaller = $this->app['auth']->guard('web')->getRecallerName();

        $this->postLogin('siti@contoh.test')->assertCookieMissing($recaller);

        $this->app['auth']->guard('web')->logout();

        $this->postLogin('siti@contoh.test', null, ['remember' => '1'])
            ->assertRedirect(route('patient.dashboard'))
            ->assertCookie($recaller);
    }

    public function test_email_with_spaces_and_uppercase_is_normalised_before_authentication(): void
    {
        $patient = $this->patientAccount();

        $this->postLogin('  Siti@Contoh.TEST ')->assertRedirect(route('patient.dashboard'));

        $this->assertAuthenticatedAs($patient);
    }

    // --- kegagalan: pesan generik (SEK-16, AKS-08, AKS-10) -----------------------------------

    /** SEK-16 dan Review Focus G.1: email tak dikenal, password salah, akun staf, dan akun nonaktif tak dapat dibedakan. */
    public function test_unknown_email_wrong_password_staff_and_inactive_accounts_give_identical_generic_responses(): void
    {
        $this->patientAccount();
        $this->createPatient(['email' => 'nonaktif@contoh.test', 'is_active' => false]);
        $this->createStaff('content-admin', ['email' => 'staf@contoh.test', 'password' => $this->patientPassword()]);

        $unknown = $this->outcome('tidak-ada@contoh.test', self::WRONG);
        $wrongPassword = $this->outcome('siti@contoh.test', self::WRONG);
        $staff = $this->outcome('staf@contoh.test');
        $inactive = $this->outcome('nonaktif@contoh.test');

        $this->assertSame([302, route('login'), ['Email atau password salah.']], $unknown);
        $this->assertSame($unknown, $wrongPassword);
        $this->assertSame($unknown, $staff);
        $this->assertSame($unknown, $inactive);
        $this->assertGuest();
    }

    /** AKS-10: kredensial staf yang benar di `/masuk` gagal generik dan tidak membentuk sesi. */
    public function test_staff_credentials_on_the_patient_form_fail_generically_without_a_session(): void
    {
        $staff = $this->createStaff('content-admin', ['email' => 'staf@contoh.test', 'password' => $this->patientPassword()]);

        $this->postLogin('staf@contoh.test')
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email' => 'Email atau password salah.']);

        $this->assertGuest();
        $this->assertNull($staff->fresh()->last_login_at);
        $this->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));
    }

    /** AKS-08: pasien nonaktif ditolak dengan pesan generik, bukan pesan "tidak aktif". */
    public function test_inactive_patient_gets_the_generic_message_instead_of_an_inactive_notice(): void
    {
        $patient = $this->createPatient(['email' => 'nonaktif@contoh.test', 'is_active' => false]);

        $html = $this->from(route('login'))->followingRedirects()
            ->post(route('login.store'), ['email' => 'nonaktif@contoh.test', 'password' => $this->patientPassword()])
            ->assertOk()
            ->getContent();

        $this->assertGuest();
        $this->assertNull($patient->fresh()->last_login_at);
        $this->assertStringContainsString('Email atau password salah.', $html);
        $this->assertStringNotContainsString('Akun Anda tidak aktif', $html);
        $this->assertStringNotContainsString('dinonaktifkan', $html);
    }

    /** AKS-08 dan Review Focus G.4: akun dinonaktifkan saat sesi berjalan, diputus dengan flash dari middleware `active`. */
    public function test_account_deactivated_during_a_running_session_is_sent_to_login_with_the_middleware_flash(): void
    {
        $this->patientAccount();
        $this->postLogin('siti@contoh.test')->assertRedirect(route('patient.dashboard'));
        $this->get(route('patient.dashboard'))->assertOk();

        // Ubah instance yang dipegang guard (guard menyimpan user antar-permintaan di tes), bukan instance lain.
        $this->app['auth']->guard('web')->user()->forceFill(['is_active' => false])->save();

        $this->get(route('patient.dashboard'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'Akun Anda dinonaktifkan. Silakan hubungi administrator rumah sakit.');
        $this->assertGuest();
    }

    public function test_required_fields_and_email_format_are_validated_with_indonesian_messages(): void
    {
        $this->from(route('login'))
            ->post(route('login.store'), [])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email' => 'Email wajib diisi.', 'password' => 'Password wajib diisi.']);

        $this->postLogin('bukan-email')->assertSessionHasErrors(['email' => 'Format email tidak valid.']);
        $this->postLogin(str_repeat('a', 250).'@contoh.test')->assertSessionHasErrors(['email' => 'Email maksimal 255 karakter.']);
        $this->postLogin('siti@contoh.test', str_repeat('a', 256))->assertSessionHasErrors(['password' => 'Password maksimal 255 karakter.']);
        $this->assertGuest();
    }

    /** F5-R31: email yang terlalu panjang sekaligus tidak valid hanya memberi satu galat (`bail`). */
    public function test_an_overlong_and_malformed_email_gives_a_single_max_error(): void
    {
        $this->postLogin(str_repeat('a', 256))
            ->assertSessionHasErrors(['email' => 'Email maksimal 255 karakter.']);

        $this->assertSame(['Email maksimal 255 karakter.'], session('errors')->get('email'));
        $this->assertGuest();
    }

    /** Review Focus G.2: larik dan nilai liar menghasilkan galat field, bukan 500; halaman dirender ulang dengan wajar. */
    public function test_array_and_wild_values_give_field_errors_without_a_500(): void
    {
        $this->patientAccount();

        $this->from(route('login'))
            ->post(route('login.store'), ['email' => ['siti@contoh.test'], 'password' => ['Rahasia123'], 'remember' => 'abc'])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors([
                'email' => 'Format email tidak valid.',
                'password' => 'Password tidak valid.',
                'remember' => 'Pilihan ingat saya tidak valid.',
            ]);

        $html = $this->from(route('login'))->followingRedirects()
            ->post(route('login.store'), ['email' => ['x'], 'password' => ['y'], 'remember' => 'abc'])
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Pilihan ingat saya tidak valid.', $html);
        $this->assertGuest();
    }

    /** Temuan fase 5: nilai lama larik pada checkbox `remember` memicu "Array to string conversion" saat form dirender ulang. */
    public function test_array_remember_value_does_not_break_the_form_when_it_is_rendered_again(): void
    {
        $this->patientAccount();

        $html = $this->from(route('login'))->followingRedirects()
            ->post(route('login.store'), ['email' => 'siti@contoh.test', 'password' => $this->patientPassword(), 'remember' => ['1']])
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Pilihan ingat saya tidak valid.', $html);
        $this->assertGuest();
    }

    public function test_remember_checkbox_keeps_its_checked_state_after_a_failed_attempt(): void
    {
        $html = $this->from(route('login'))->followingRedirects()
            ->post(route('login.store'), ['email' => 'tidak.ada@contoh.test', 'password' => self::WRONG, 'remember' => '1'])
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression('/<input[^>]*name="remember"[^>]*\schecked/', $html);
    }

    public function test_password_is_not_flashed_back_into_the_form(): void
    {
        $this->postLogin('siti@contoh.test', self::WRONG);

        $this->assertNull(session('_old_input.password'));
        $this->assertSame('siti@contoh.test', session('_old_input.email'));
    }

    // --- CSRF (SEK-06, ERR-03) ---------------------------------------------------------------

    public function test_missing_csrf_token_on_login_redirects_back_with_a_session_expired_message(): void
    {
        $this->app['env'] = 'local'; // mengaktifkan kembali ValidateCsrfToken (dilewati saat env `testing`)

        $this->from(route('login'))
            ->post(route('login.store'), ['email' => 'siti@contoh.test', 'password' => 'RahasiaUji1'])
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'Sesi formulir Anda telah berakhir. Silakan periksa isian Anda lalu kirim ulang.')
            ->assertSessionMissing('_old_input.password');

        $this->assertGuest();
    }

    public function test_a_valid_csrf_token_passes_the_csrf_check(): void
    {
        $this->app['env'] = 'local';

        $this->withSession(['_token' => 'token-uji'])
            ->from(route('login'))
            ->post(route('login.store'), ['_token' => 'token-uji', 'email' => '', 'password' => ''])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email', 'password'])
            ->assertSessionMissing('error');
    }

    // --- throttle (SEK-03) -------------------------------------------------------------------

    public function test_sixth_failed_attempt_is_throttled_with_a_flash_message_and_retry_after_header(): void
    {
        $this->patientAccount();

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postLogin('siti@contoh.test', self::WRONG)
                ->assertRedirect(route('login'))
                ->assertSessionHasErrors('email');
        }

        $this->postLogin('siti@contoh.test', self::WRONG)
            ->assertStatus(302)
            ->assertRedirect(route('login'))
            ->assertHeader('Retry-After')
            ->assertSessionHas('error', fn ($message) => preg_match('/^Terlalu banyak percobaan\. Silakan coba lagi dalam \d+ detik\.$/', $message) === 1)
            ->assertSessionMissing('_old_input.password');

        // Email lain tidak terkena batas email yang sama.
        $this->postLogin('lain@contoh.test', self::WRONG)->assertSessionHasErrors('email');
    }

    public function test_throttle_key_is_shared_across_email_case_and_spacing(): void
    {
        $this->patientAccount();

        foreach (['  Siti@Contoh.TEST ', 'siti@contoh.test', 'SITI@contoh.test ', 'siti@CONTOH.test', 'Siti@contoh.test'] as $variant) {
            $this->postLogin($variant, self::WRONG)->assertSessionHasErrors('email');
        }

        $this->postLogin('siti@contoh.test', self::WRONG)
            ->assertHeader('Retry-After')
            ->assertSessionHas('error');
    }

    public function test_successful_login_clears_the_login_throttle_counter(): void
    {
        $this->patientAccount();

        for ($attempt = 1; $attempt <= 4; $attempt++) {
            $this->postLogin('siti@contoh.test', self::WRONG)->assertSessionHasErrors('email');
        }

        // Permintaan ke-5 (sukses) ikut dihitung middleware; setelah itu penghitung harus kembali nol.
        $this->postLogin('  Siti@Contoh.TEST ')->assertRedirect(route('patient.dashboard'));
        $this->app['auth']->guard('web')->logout();

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postLogin('siti@contoh.test', self::WRONG)
                ->assertSessionHasErrors('email')
                ->assertHeaderMissing('Retry-After');
        }

        $this->postLogin('siti@contoh.test', self::WRONG)->assertHeader('Retry-After');
    }

    // --- intended URL (AKS-07, Review Focus G.4) ---------------------------------------------

    /**
     * AKS-07 (versi fase 5). Route `patient.profile.edit` baru ada di Task 6 dan `/pasien/pendaftaran/baru` di fase 6,
     * jadi URL terlindungi yang membawa query diwakili dashboard `/pasien?contoh=utuh`. Tes AKS-07 asli (sesi bookable,
     * D-151) ada di `BookingWizardTest::test_aks_07_a_guest_with_a_bookable_session_returns_to_the_wizard_with_the_query_intact`.
     */
    public function test_login_returns_to_the_intended_protected_url_with_its_query_intact(): void
    {
        $this->patientAccount();

        $this->get('/pasien?contoh=utuh')->assertRedirect(route('login'));
        $this->postLogin('siti@contoh.test')
            ->assertRedirect(url('/pasien?contoh=utuh'))
            ->assertSessionMissing('url.intended');

        $this->get('/pasien?contoh=utuh')->assertOk();
    }

    public function test_intended_admin_url_is_ignored_and_the_key_is_consumed(): void
    {
        $this->patientAccount();

        $this->get('/admin/dokter')->assertRedirect(route('admin.login'));
        $this->postLogin('siti@contoh.test')
            ->assertRedirect(route('patient.dashboard'))
            ->assertSessionMissing('url.intended');
    }

    #[DataProvider('unsafeIntendedValues')]
    public function test_unsafe_or_non_string_intended_values_fall_back_to_the_dashboard(mixed $intended): void
    {
        $this->patientAccount();

        $this->withSession(['url.intended' => $intended]);

        $this->postLogin('siti@contoh.test')->assertRedirect(route('patient.dashboard'));
    }

    /** @return array<string, array{0: mixed}> */
    public static function unsafeIntendedValues(): array
    {
        return [
            'admin tepat' => ['http://localhost/admin'],
            'admin dengan subpath dan query' => ['http://localhost/admin/dokter?q=a'],
            'bukan string (larik)' => [['http://localhost/pasien']],
            'angka' => [123],
            'kosong' => [''],
            'tanpa path' => ['http://localhost'],
        ];
    }

    // --- logout (F5-R8, SEK-02) --------------------------------------------------------------

    public function test_logout_invalidates_the_session_issues_a_new_csrf_token_and_is_not_audited(): void
    {
        $this->patientAccount();
        $this->postLogin('siti@contoh.test');
        $idBefore = session()->getId();
        $tokenBefore = session()->token();
        $auditBefore = AuditLog::query()->count();

        $this->post(route('logout'))
            ->assertRedirect(route('home'))
            ->assertSessionHas('success', 'Anda telah keluar.');

        $this->assertGuest();
        $this->assertNotSame($idBefore, session()->getId());
        $this->assertNotSame($tokenBefore, session()->token());
        $this->assertSame($auditBefore, AuditLog::query()->count());
        $this->get(route('patient.dashboard'))->assertRedirect(route('login'));
    }

    public function test_guest_logout_goes_to_the_patient_login(): void
    {
        $this->post(route('logout'))->assertRedirect(route('login'));
    }

    public function test_staff_can_also_use_the_shared_logout_route(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->post(route('logout'))
            ->assertRedirect(route('home'));

        $this->assertGuest();
    }

    // --- pengalihan akun yang sudah login ----------------------------------------------------

    public function test_logged_in_patient_opening_the_login_page_goes_to_the_dashboard(): void
    {
        $this->actingAs($this->patientAccount())->get(route('login'))->assertRedirect(route('patient.dashboard'));
    }

    public function test_logged_in_staff_opening_the_login_page_or_the_patient_area_goes_to_the_admin_dashboard(): void
    {
        $staff = $this->createStaff('content-admin');

        $this->actingAs($staff)->get(route('login'))->assertRedirect(route('admin.dashboard'));
        $this->actingAs($staff)->get('/pasien')->assertRedirect(route('admin.dashboard'));
        $this->actingAs($staff)->getJson('/pasien')->assertForbidden();
    }

    public function test_guest_on_the_patient_area_is_sent_to_the_patient_login(): void
    {
        $this->get('/pasien')->assertRedirect(route('login'));
    }

    // --- route -------------------------------------------------------------------------------

    /** F5-R3: URI, method, dan middleware empat route Task 3. */
    public function test_task_3_routes_have_the_expected_uri_method_and_middleware(): void
    {
        $expected = [
            'login' => ['GET', 'masuk', ['guest']],
            'login.store' => ['POST', 'masuk', ['guest', 'throttle:login']],
            'logout' => ['POST', 'keluar', ['auth']],
            'patient.dashboard' => ['GET', 'pasien', ['auth', 'account:patient', 'active']],
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
