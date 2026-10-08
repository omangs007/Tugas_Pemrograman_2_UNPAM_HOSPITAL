<?php

namespace Tests\Feature\Auth;

use App\Enums\ActorType;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InteractsWithPatients;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

/**
 * Reset password pasien (#25, #26): V-04, V-05, F5-R15 sampai F5-R17, BR-148. Hanya akun pasien aktif yang menerima
 * tautan; respons permintaan selalu identik; tautan tidak pernah tampil di halaman; token sekali pakai, berlaku 60 menit.
 */
class PatientPasswordResetTest extends TestCase
{
    use InteractsWithPatients;
    use InteractsWithStaff;
    use RefreshDatabase;

    private const EMAIL = 'pasien@contoh.test';

    private const NEW_PASSWORD = 'PasswordBaru123';

    private const TOKEN_ERROR = 'Tautan pengaturan ulang password tidak valid atau sudah kedaluwarsa. Silakan ajukan permintaan baru.';

    private const THROTTLE_PATTERN = '/^Terlalu banyak percobaan\. Silakan coba lagi dalam \d+ detik\.$/';

    // --- pembantu ----------------------------------------------------------------------------

    /** @param  array<string, mixed>  $attributes */
    private function patient(array $attributes = []): User
    {
        return $this->createPatient($attributes + ['email' => self::EMAIL]);
    }

    private function postForgot(string $email): TestResponse
    {
        return $this->from(route('password.request'))
            ->post(route('password.email'), ['email' => $email]);
    }

    /** @param  array<string, mixed>  $overrides */
    private function postReset(string $token, array $overrides = []): TestResponse
    {
        return $this->from($this->resetUrl($token))
            ->post(route('password.update'), $overrides + [
                'token' => $token,
                'email' => self::EMAIL,
                'password' => self::NEW_PASSWORD,
                'password_confirmation' => self::NEW_PASSWORD,
            ]);
    }

    private function resetUrl(string $token): string
    {
        return route('password.reset', ['token' => $token, 'email' => self::EMAIL]);
    }

    /** Meminta tautan dengan notifikasi palsu lalu mengembalikan token dari notifikasi (menghitung 1 permintaan limiter). */
    private function requestToken(User $user): string
    {
        Notification::fake();
        $this->postForgot($user->email)->assertRedirect(route('password.request'));

        return $this->capturedToken($user);
    }

    private function capturedToken(User $user): string
    {
        $token = null;

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use (&$token): bool {
            $token = $notification->token;

            return true;
        });

        $this->assertIsString($token);

        return $token;
    }

    // --- halaman ----------------------------------------------------------------------------

    public function test_forgot_password_page_renders_the_form_for_guests(): void
    {
        $response = $this->get(route('password.request'));

        $response->assertOk()
            ->assertSee('Lupa Password')
            ->assertSee('Masukkan email akun Anda. Kami akan mengirim tautan untuk mengatur ulang password jika email terdaftar.')
            ->assertSee('action="'.route('password.email').'"', false)
            ->assertSee('name="email"', false)
            ->assertSee('autocomplete="username"', false)
            ->assertSee('Kirim Tautan')
            ->assertSee('<a href="'.route('login').'">Kembali ke halaman masuk</a>', false)
            ->assertSee('<meta name="robots" content="noindex,nofollow">', false)
            ->assertDontSee('name="password"', false);
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertSame(1, substr_count($response->getContent(), '<h1'));
    }

    public function test_login_page_links_to_the_forgot_password_page(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('<a href="'.route('password.request').'">Lupa password?</a>', false);
    }

    public function test_reset_form_prefills_a_readonly_email_and_hides_the_token(): void
    {
        $response = $this->get(route('password.reset', ['token' => 'abc123', 'email' => ' Pasien@Contoh.TEST ']));

        $response->assertOk()
            ->assertSee('<title>Reset Password', false)
            ->assertSee('<h1 class="h3 mb-1">Atur Ulang Password</h1>', false)
            ->assertSee('Buat password baru untuk akun Anda.')
            ->assertSee('action="'.route('password.update').'"', false)
            ->assertSee('<input type="hidden" name="token" value="abc123">', false)
            ->assertSee('value="pasien@contoh.test"', false)
            ->assertSee('Password baru')
            ->assertSee('Konfirmasi password baru')
            ->assertSee('Simpan Password Baru')
            ->assertSee('<a href="'.route('password.request').'">Minta tautan baru</a>', false);
        $this->assertMatchesRegularExpression('/<input[^>]*name="email"[^>]*\sreadonly[="\s>]/', $response->getContent());
        $this->assertMatchesRegularExpression('/<input[^>]*name="email"[^>]*autocomplete="username"/', $response->getContent());
        $this->assertSame(2, substr_count($response->getContent(), 'autocomplete="new-password"'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_reset_form_without_a_usable_email_leaves_the_field_editable(): void
    {
        foreach (['/reset-password/abc123', '/reset-password/abc123?email[]=x', '/reset-password/abc123?email='] as $url) {
            $response = $this->get($url);

            $response->assertOk();
            $this->assertDoesNotMatchRegularExpression('/<input[^>]*name="email"[^>]*\sreadonly/', $response->getContent());
        }
    }

    public function test_the_token_in_the_reset_form_is_escaped(): void
    {
        $this->get(route('password.reset', ['token' => 'a"b']))
            ->assertOk()
            ->assertSee('<input type="hidden" name="token" value="a&quot;b">', false);
    }

    public function test_logged_in_accounts_are_redirected_away_from_the_reset_pages(): void
    {
        $patient = $this->patient();
        $staff = $this->createStaff('content-admin');

        foreach ([route('password.request'), route('password.reset', ['token' => 'x'])] as $url) {
            $this->actingAs($patient)->get($url)->assertRedirect(route('patient.dashboard'));
            $this->actingAs($staff)->get($url)->assertRedirect(route('admin.dashboard'));
        }

        Notification::fake();
        $this->actingAs($patient)->post(route('password.email'), ['email' => self::EMAIL])
            ->assertRedirect(route('patient.dashboard'));
        Notification::assertNothingSent();
    }

    public function test_routes_have_the_documented_uris_methods_and_middleware(): void
    {
        $expected = [
            'password.request' => ['GET', 'lupa-password', false],
            'password.email' => ['POST', 'lupa-password', true],
            'password.reset' => ['GET', 'reset-password/{token}', false],
            'password.update' => ['POST', 'reset-password', true],
        ];

        foreach ($expected as $name => [$method, $uri, $throttled]) {
            $route = Route::getRoutes()->getByName($name);

            $this->assertNotNull($route, "Route {$name} belum ada.");
            $this->assertContains($method, $route->methods());
            $this->assertSame($uri, $route->uri());
            $this->assertContains('guest', $route->gatherMiddleware(), "{$name} harus memakai guest.");
            $this->assertSame($throttled, in_array('throttle:password-reset', $route->gatherMiddleware(), true), "{$name}: throttle password-reset.");
        }
    }

    public function test_broker_configuration_matches_the_documented_values(): void
    {
        $this->assertSame(60, config('auth.passwords.users.expire'));
        $this->assertSame(60, config('auth.passwords.users.throttle'));
        $this->assertSame('password_reset_tokens', config('auth.passwords.users.table'));
    }

    // --- V-04: permintaan tautan ------------------------------------------------------------

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function invalidForgotPayloads(): array
    {
        return [
            'email kosong' => [['email' => ''], 'Email wajib diisi.'],
            'email tidak valid' => [['email' => 'bukan-email'], 'Format email tidak valid.'],
            'email terlalu panjang' => [['email' => str_repeat('a', 250).'@x.test'], 'Email maksimal 255 karakter.'],
            'email larik' => [['email' => ['x@contoh.test']], 'Format email tidak valid.'],
        ];
    }

    /** @param  array<string, mixed>  $payload */
    #[DataProvider('invalidForgotPayloads')]
    public function test_forgot_request_validates_the_email_with_the_documented_messages(array $payload, string $message): void
    {
        Notification::fake();
        $this->patient();

        $this->from(route('password.request'))
            ->post(route('password.email'), $payload)
            ->assertRedirect(route('password.request'))
            ->assertSessionHasErrors(['email' => $message]);

        Notification::assertNothingSent();
        $this->assertSame(0, DB::table('password_reset_tokens')->count());
    }

    // --- NTF-05 / SEK-16: respons generik ---------------------------------------------------

    public function test_registered_unknown_staff_and_inactive_emails_get_identical_responses(): void
    {
        Notification::fake();
        $patient = $this->patient();
        $inactive = $this->createPatient(['email' => 'nonaktif@contoh.test', 'is_active' => false]);
        $staff = $this->createStaff('registration-officer', ['email' => 'petugas@contoh.test']);

        $snapshots = [];

        foreach ([self::EMAIL, 'tidak-ada@contoh.test', 'petugas@contoh.test', 'nonaktif@contoh.test'] as $email) {
            $response = $this->postForgot($email);

            $response->assertRedirect(route('password.request'))
                ->assertSessionHas('success', 'Jika email terdaftar, tautan untuk mengatur ulang password telah dikirim. Periksa kotak masuk Anda.')
                ->assertSessionHasNoErrors();
            $snapshots[$email] = [$response->getStatusCode(), $response->headers->get('Location')];
        }

        $this->assertCount(1, array_unique(array_map('serialize', $snapshots)), 'Status dan Location harus identik untuk keempat email.');

        // Hanya pasien aktif yang menerima tautan dan memiliki baris token.
        Notification::assertSentTo($patient, ResetPassword::class);
        Notification::assertNotSentTo($staff, ResetPassword::class);
        Notification::assertNotSentTo($inactive, ResetPassword::class);
        Notification::assertSentTimes(ResetPassword::class, 1);
        $this->assertSame([self::EMAIL], DB::table('password_reset_tokens')->pluck('email')->all());
    }

    public function test_a_second_request_within_sixty_seconds_looks_identical_and_sends_nothing_new(): void
    {
        Notification::fake();
        $patient = $this->patient();

        $first = $this->postForgot(self::EMAIL);
        $firstToken = DB::table('password_reset_tokens')->value('token');

        // Broker mengembalikan RESET_THROTTLED: tidak ada notifikasi baru, token lama tidak diganti.
        $second = $this->postForgot(self::EMAIL);

        $second->assertRedirect(route('password.request'))
            ->assertSessionHas('success', __('passwords.sent'))
            ->assertSessionHasNoErrors();
        $this->assertSame($first->getStatusCode(), $second->getStatusCode());
        $this->assertSame($first->headers->get('Location'), $second->headers->get('Location'));
        Notification::assertSentTimes(ResetPassword::class, 1);
        $this->assertSame($firstToken, DB::table('password_reset_tokens')->value('token'));

        // Sesudah 60 detik permintaan baru diproses lagi (email diketik dengan huruf besar dan spasi).
        $this->travel(61)->seconds();
        $this->postForgot('  PASIEN@Contoh.test ')
            ->assertSessionHas('success', __('passwords.sent'));
        Notification::assertSentTimes(ResetPassword::class, 2);
        Notification::assertSentTo($patient, ResetPassword::class);
    }

    public function test_staff_and_inactive_patients_cannot_complete_a_reset_even_with_a_valid_token(): void
    {
        $staff = $this->createStaff('content-admin', ['email' => 'petugas@contoh.test']);
        $inactive = $this->createPatient(['email' => 'nonaktif@contoh.test', 'is_active' => false]);

        foreach ([$staff, $inactive] as $account) {
            $originalHash = $account->password;
            $token = Password::broker()->createToken($account);

            $this->postReset($token, ['email' => $account->email])
                ->assertSessionHasErrors(['email' => self::TOKEN_ERROR]);

            $this->assertSame($originalHash, $account->fresh()->password, 'Password akun yang tidak berhak tidak boleh berubah.');
        }

        $this->assertSame(0, AuditLog::query()->where('action', 'auth.password_reset')->count());
    }

    public function test_the_reset_link_never_appears_in_a_page_or_in_the_flash(): void
    {
        $patient = $this->patient();
        $token = $this->requestToken($patient);

        $redirect = $this->postForgot(self::EMAIL);
        $page = $this->get(route('password.request'));

        foreach ([$redirect->getContent(), $page->getContent(), (string) json_encode(session()->all())] as $haystack) {
            $this->assertStringNotContainsString($token, $haystack);
            $this->assertStringNotContainsString('/reset-password/', $haystack);
        }

        // Token tersimpan ter-hash.
        $this->assertNotSame($token, DB::table('password_reset_tokens')->value('token'));
    }

    // --- SEK-04: throttle ------------------------------------------------------------------

    public function test_the_fourth_request_within_ten_minutes_is_throttled_with_a_flash_and_retry_after(): void
    {
        Notification::fake();
        $this->patient();

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $this->postForgot(self::EMAIL)
                ->assertRedirect(route('password.request'))
                ->assertSessionHas('success', __('passwords.sent'))
                ->assertHeaderMissing('Retry-After');
        }

        $this->postForgot(self::EMAIL)
            ->assertRedirect(route('password.request'))
            ->assertHeader('Retry-After')
            ->assertSessionHas('error', fn ($message) => preg_match(self::THROTTLE_PATTERN, $message) === 1)
            ->assertSessionMissing('success');

        // Kunci tidak membedakan huruf besar/kecil dan spasi; email lain tidak terkena.
        $this->postForgot('  PASIEN@Contoh.TEST ')->assertHeader('Retry-After');
        $this->postForgot('lain@contoh.test')
            ->assertHeaderMissing('Retry-After')
            ->assertSessionHas('success', __('passwords.sent'));
    }

    public function test_password_update_shares_the_throttle_bucket_with_the_reset_request(): void
    {
        Notification::fake();
        $this->patient();

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $this->postForgot(self::EMAIL)->assertHeaderMissing('Retry-After');
        }

        $this->postReset('token-apa-saja')
            ->assertRedirect($this->resetUrl('token-apa-saja'))
            ->assertHeader('Retry-After')
            ->assertSessionHas('error', fn ($message) => preg_match(self::THROTTLE_PATTERN, $message) === 1);
    }

    public function test_a_missing_csrf_token_on_the_reset_request_redirects_back_with_a_session_expired_message(): void
    {
        $this->app['env'] = 'local'; // mengaktifkan kembali ValidateCsrfToken (dilewati saat env `testing`)

        $this->from(route('password.request'))
            ->post(route('password.email'), ['email' => self::EMAIL])
            ->assertRedirect(route('password.request'))
            ->assertSessionHas('error', 'Sesi formulir Anda telah berakhir. Silakan periksa isian Anda lalu kirim ulang.');
    }

    // --- NTF-04: alur reset penuh -----------------------------------------------------------

    public function test_a_patient_can_reset_the_password_with_the_emailed_token_and_the_token_is_single_use(): void
    {
        $patient = $this->patient();
        $token = $this->requestToken($patient);

        $this->get($this->resetUrl($token))->assertOk();

        $this->postReset($token)
            ->assertRedirect(route('login'))
            ->assertSessionHas('success', 'Password berhasil diubah. Silakan masuk dengan password baru.');
        $this->assertGuest();

        $fresh = $patient->fresh();
        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $fresh->password));
        $this->assertFalse(Hash::check($this->patientPassword(), $fresh->password));
        $this->assertStringStartsWith('$2y$', DB::table('users')->where('id', $patient->id)->value('password'));
        $this->assertSame(0, DB::table('password_reset_tokens')->count(), 'Token dihapus setelah dipakai.');

        // Pemakaian ulang token yang sama gagal dengan pesan token (galat pada field email).
        $this->postReset($token, ['password' => 'LainLagi12345', 'password_confirmation' => 'LainLagi12345'])
            ->assertRedirect($this->resetUrl($token))
            ->assertSessionHasErrors(['email' => self::TOKEN_ERROR]);
        $this->assertFalse(Hash::check('LainLagi12345', $patient->fresh()->password));

        // Password baru berlaku untuk login, password lama tidak.
        $this->post(route('login.store'), ['email' => self::EMAIL, 'password' => self::NEW_PASSWORD])
            ->assertRedirect(route('patient.dashboard'));
        $this->assertAuthenticatedAs($patient);
    }

    public function test_the_old_password_no_longer_logs_in_after_a_reset(): void
    {
        $patient = $this->patient();
        $token = $this->requestToken($patient);
        $this->postReset($token)->assertRedirect(route('login'));

        $this->from(route('login'))
            ->post(route('login.store'), ['email' => self::EMAIL, 'password' => $this->patientPassword()])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email' => 'Email atau password salah.']);
        $this->assertGuest();
    }

    public function test_the_token_expires_after_sixty_minutes(): void
    {
        $patient = $this->patient();
        $token = $this->requestToken($patient);
        $originalHash = $patient->password;

        $this->travel(61)->minutes();

        $this->postReset($token)
            ->assertRedirect($this->resetUrl($token))
            ->assertSessionHasErrors(['email' => self::TOKEN_ERROR]);
        $this->assertSame($originalHash, $patient->fresh()->password);
        $this->assertSame(0, AuditLog::query()->where('action', 'auth.password_reset')->count());
    }

    public function test_the_token_is_still_valid_at_minute_fifty_nine(): void
    {
        $patient = $this->patient();
        $token = $this->requestToken($patient);

        $this->travel(59)->minutes();

        $this->postReset($token)->assertRedirect(route('login'));
        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $patient->fresh()->password));
    }

    public function test_a_wrong_token_or_a_foreign_email_gives_the_same_error_and_changes_nothing(): void
    {
        $patient = $this->patient();
        $other = $this->createPatient(['email' => 'lain@contoh.test']);
        $token = $this->requestToken($patient);
        $originalHash = $patient->password;

        $this->postReset('token-salah')
            ->assertSessionHasErrors(['email' => self::TOKEN_ERROR]);

        // Token milik pasien lain dipakai dengan email pasien lain: tidak cocok.
        $this->postReset($token, ['email' => 'lain@contoh.test'])
            ->assertSessionHasErrors(['email' => self::TOKEN_ERROR]);

        $this->assertSame($originalHash, $patient->fresh()->password);
        $this->assertSame($other->password, $other->fresh()->password);
        $this->assertSame(1, DB::table('password_reset_tokens')->count(), 'Token yang sah tetap berlaku.');
    }

    public function test_the_email_is_normalised_before_the_reset_is_checked(): void
    {
        $patient = $this->patient();
        $token = $this->requestToken($patient);

        $this->postReset($token, ['email' => '  PASIEN@Contoh.TEST '])->assertRedirect(route('login'));
        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $patient->fresh()->password));
    }

    // --- V-05: validasi form reset ----------------------------------------------------------

    /**
     * @return array<string, array{array<string, mixed>, string, string}>
     */
    public static function invalidResetPayloads(): array
    {
        $ok = ['token' => 'tok', 'email' => self::EMAIL, 'password' => self::NEW_PASSWORD, 'password_confirmation' => self::NEW_PASSWORD];
        $tokenMessage = 'Tautan pengaturan ulang password tidak valid. Silakan ajukan permintaan baru.';

        return [
            'token kosong' => [['token' => ''] + $ok, 'token', $tokenMessage],
            'token larik' => [['token' => ['x']] + $ok, 'token', $tokenMessage],
            'email kosong' => [['email' => ''] + $ok, 'email', 'Email wajib diisi.'],
            'email tidak valid' => [['email' => 'bukan-email'] + $ok, 'email', 'Format email tidak valid.'],
            'email larik' => [['email' => ['x']] + $ok, 'email', 'Format email tidak valid.'],
            'email terlalu panjang' => [['email' => str_repeat('a', 250).'@x.test'] + $ok, 'email', 'Email maksimal 255 karakter.'],
            'password kosong' => [['password' => ''] + $ok, 'password', 'Password wajib diisi.'],
            'password terlalu pendek' => [['password' => 'Ab1', 'password_confirmation' => 'Ab1'] + $ok, 'password', 'Password minimal 8 karakter.'],
            'password tanpa angka' => [['password' => 'Abcdefghij', 'password_confirmation' => 'Abcdefghij'] + $ok, 'password', 'Password harus mengandung angka.'],
            'password tanpa huruf' => [['password' => '1234567890', 'password_confirmation' => '1234567890'] + $ok, 'password', 'Password harus mengandung huruf.'],
            'password terlalu panjang' => [['password' => str_repeat('Aa1', 21).'AB', 'password_confirmation' => str_repeat('Aa1', 21).'AB'] + $ok, 'password', 'Password maksimal 64 karakter.'],
            'konfirmasi tidak cocok' => [['password_confirmation' => 'Lain12345'] + $ok, 'password', 'Konfirmasi password tidak cocok.'],
            'konfirmasi kosong' => [['password_confirmation' => ''] + $ok, 'password_confirmation', 'Konfirmasi password wajib diisi.'],
            'password larik' => [['password' => ['x'], 'password_confirmation' => ['x']] + $ok, 'password', 'Password tidak valid.'],
        ];
    }

    /** @param  array<string, mixed>  $payload */
    #[DataProvider('invalidResetPayloads')]
    public function test_reset_form_rejects_invalid_input_with_the_documented_messages_and_never_a_server_error(array $payload, string $field, string $message): void
    {
        $patient = $this->patient();
        $originalHash = $patient->password;

        $this->from($this->resetUrl('tok'))
            ->post(route('password.update'), $payload)
            ->assertRedirect($this->resetUrl('tok'))
            ->assertSessionHasErrors([$field => $message]);

        $this->assertSame($originalHash, $patient->fresh()->password);
        $this->assertSame(0, AuditLog::query()->where('action', 'auth.password_reset')->count());
    }

    public function test_the_email_is_kept_in_the_form_after_a_failed_reset_but_passwords_are_not(): void
    {
        $patient = $this->patient();
        $token = $this->requestToken($patient);

        $this->postReset($token, ['password' => 'Ab1', 'password_confirmation' => 'Ab1'])
            ->assertSessionHasErrors('password');

        $this->assertNull(session('_old_input.password'));
        $this->assertNull(session('_old_input.password_confirmation'));

        $this->get($this->resetUrl($token))
            ->assertOk()
            ->assertSee('Password minimal 8 karakter.')
            ->assertSee('value="pasien@contoh.test"', false);
    }

    // --- efek reset: sesi, remember_token, audit (SEK-11, G.4) ------------------------------

    public function test_a_reset_deletes_every_session_of_the_account_and_rotates_the_remember_token(): void
    {
        $patient = $this->patient();
        $other = $this->createPatient(['email' => 'lain@contoh.test']);
        $rememberBefore = $patient->remember_token;

        $row = fn (string $id, ?int $userId): array => [
            'id' => $id,
            'user_id' => $userId,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'uji',
            'payload' => 'x',
            'last_activity' => time(),
        ];
        DB::table('sessions')->insert([
            $row('sesi-pasien-1', $patient->id),
            $row('sesi-pasien-2', $patient->id),
            $row('sesi-akun-lain', $other->id),
            $row('sesi-tamu', null),
        ]);

        $token = $this->requestToken($patient);
        $this->postReset($token)->assertRedirect(route('login'));

        $this->assertSame(0, DB::table('sessions')->where('user_id', $patient->id)->count());
        $this->assertEqualsCanonicalizing(['sesi-akun-lain', 'sesi-tamu'], DB::table('sessions')->pluck('id')->all());

        $rememberAfter = $patient->fresh()->remember_token;
        $this->assertNotSame($rememberBefore, $rememberAfter);
        $this->assertSame(60, strlen((string) $rememberAfter));
    }

    public function test_the_reset_is_audited_without_the_token_or_any_password(): void
    {
        $patient = $this->patient();
        $token = $this->requestToken($patient);

        $this->postReset($token)->assertRedirect(route('login'));

        $log = AuditLog::query()->where('action', 'auth.password_reset')->sole();
        $this->assertSame(ActorType::Patient, $log->actor_type);
        $this->assertEquals($patient->id, $log->user_id);
        $this->assertEquals($patient->id, $log->auditable_id);
        $this->assertSame(User::class, $log->auditable_type);
        $this->assertNull($log->old_values);
        $this->assertNull($log->new_values);
        $this->assertSame(
            'Password akun pasien '.$patient->patientProfile->patient_number.' diatur ulang lewat tautan email.',
            $log->description,
        );

        foreach ([$token, self::NEW_PASSWORD, $this->patientPassword(), self::EMAIL] as $secret) {
            $this->assertSame(0, DB::table('audit_logs')
                ->where('old_values', 'like', "%{$secret}%")
                ->orWhere('new_values', 'like', "%{$secret}%")
                ->orWhere('description', 'like', "%{$secret}%")
                ->count(), 'Token, password, dan email tidak boleh ada di audit_logs.');
        }
    }

    // --- email reset berbahasa Indonesia (F5-R17) ------------------------------------------

    public function test_the_reset_mail_is_sent_in_indonesian_with_a_link_to_the_reset_form(): void
    {
        $this->patient(['name' => 'Siti Rahma']);

        $this->postForgot(self::EMAIL)->assertSessionHas('success', __('passwords.sent'));

        $messages = app('mail.manager')->mailer()->getSymfonyTransport()->messages();
        $this->assertCount(1, $messages);

        $email = $messages->first()->getOriginalMessage();
        $this->assertSame('Atur Ulang Password', $email->getSubject());
        $this->assertSame(self::EMAIL, $email->getTo()[0]->getAddress());

        $html = (string) $email->getHtmlBody();

        foreach ([
            'Halo!',
            'Anda menerima email ini karena ada permintaan untuk mengatur ulang password akun Anda.',
            'Atur Ulang Password',
            'Tautan ini berlaku selama 60 menit.',
            'Jika Anda tidak meminta pengaturan ulang password, abaikan email ini.',
            'Salam,',
            '/reset-password/',
            'email=pasien%40contoh.test',
        ] as $expected) {
            $this->assertStringContainsString($expected, $html);
        }

        foreach (['Hello!', 'Regards,', 'Reset Password', 'You are receiving this email', 'Whoops!'] as $english) {
            $this->assertStringNotContainsString($english, $html, "Teks Inggris '{$english}' tidak boleh muncul di email.");
        }
    }

    public function test_the_notification_url_points_at_the_reset_form_with_token_and_email(): void
    {
        $patient = $this->patient();
        $url = (new ResetPassword('tok123'))->toMail($patient)->actionUrl;

        $this->assertSame(url('/reset-password/tok123?email=pasien%40contoh.test'), $url);
    }
}
