<?php

namespace Tests\Feature\View;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

/**
 * Penjaga regresi poles hasil uji manual fase 1 (login, ganti password, footer admin).
 */
class AuthPolishTest extends TestCase
{
    use InteractsWithStaff;
    use RefreshDatabase;

    private const FORCED_MESSAGE = 'Anda harus mengganti password sementara sebelum melanjutkan.';

    private function forcedStaff(): User
    {
        $this->seedRoles();

        return User::factory()->staff()->withRole('content-admin')->mustChangePassword()->create();
    }

    // 1. Logo besar dan nama tidak ganda -------------------------------------------------------

    public function test_login_brand_is_one_large_logo_link_without_a_duplicated_site_name(): void
    {
        $html = $this->get(route('admin.login'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '#<a class="auth-brand" href="[^"]*">\s*<img class="auth-logo" src="[^"]*logo\.svg" data-fallback="[^"]*" alt="'.preg_quote(config('app.name'), '#').'" height="64">\s*</a>#',
            $html,
        );
        $this->assertStringNotContainsString('width="48" height="48"', $html);
        $this->assertStringNotContainsString('<span class="h5 mb-0 fw-bold text-primary">', $html);
    }

    // 2. Satu kartu ----------------------------------------------------------------------------

    public function test_auth_pages_have_a_single_card_surface(): void
    {
        $pages = [
            route('admin.login') => null,
            route('admin.password.edit') => $this->forcedStaff(),
        ];

        foreach ($pages as $uri => $user) {
            if ($user !== null) {
                $this->actingAs($user);
            }

            $html = $this->get($uri)->assertOk()->getContent();

            $this->assertSame(1, substr_count($html, 'auth-card'), "Harus ada tepat satu permukaan auth-card di {$uri}.");
            $this->assertSame(0, preg_match('/class="(?:[^"]* )?card(?: [^"]*)?"/', $html), "Kartu bersarang (.card) ditemukan di {$uri}.");
            $this->assertStringNotContainsString('card-body', $html);
        }
    }

    // 3. Pesan wajib ganti hanya sekali --------------------------------------------------------

    public function test_forced_message_appears_once_with_or_without_the_flash(): void
    {
        $staff = $this->forcedStaff();

        $direct = $this->actingAs($staff)->get(route('admin.password.edit'))->assertOk()->getContent();
        $this->assertSame(1, substr_count($direct, self::FORCED_MESSAGE), 'Tanpa flash, subjudul menampilkan pesan.');

        $redirected = $this->actingAs($staff)->withSession(['warning' => self::FORCED_MESSAGE])->get(route('admin.password.edit'))->assertOk()->getContent();
        $this->assertSame(1, substr_count($redirected, self::FORCED_MESSAGE), 'Dengan flash, hanya alert yang menampilkan pesan.');
        $this->assertStringContainsString('data-flash="warning"', $redirected);
        $this->assertStringContainsString('Gunakan password yang kuat', $redirected);
    }

    public function test_regular_password_change_has_no_forced_message(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->get(route('admin.password.edit'))
            ->assertOk()
            ->assertDontSee(self::FORCED_MESSAGE);
    }

    // 4. Autofocus -----------------------------------------------------------------------------

    public function test_login_email_is_autofocused_unless_there_are_errors(): void
    {
        $html = $this->get(route('admin.login'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<input[^>]*name="email"[^>]*\sautofocus/', $html);
        $this->assertSame(1, preg_match_all('/\sautofocus(?=[\s=>])/', $html), 'Hanya satu atribut autofocus (dirender autofocus="autofocus").');

        $failed = $this->from(route('admin.login'))->followingRedirects()
            ->post(route('admin.login.store'), ['email' => 'tidak.ada@unpam.test', 'password' => 'Salah12345'])
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-errors-summary', $failed);
        $this->assertStringNotContainsString('autofocus', $failed);
    }

    // 5. Field username tersembunyi ------------------------------------------------------------

    public function test_change_password_form_has_a_hidden_username_field(): void
    {
        $staff = $this->forcedStaff();

        $html = $this->actingAs($staff)->get(route('admin.password.edit'))->assertOk()->getContent();

        $this->assertStringContainsString('<input type="text" name="username" value="'.e($staff->email).'" autocomplete="username" readonly hidden>', $html);
        $this->assertLessThan(strpos($html, 'name="current_password"'), strpos($html, 'name="username"'));
    }

    public function test_hidden_username_does_not_break_the_password_change(): void
    {
        $this->seedRoles();
        $staff = User::factory()->staff()->withRole('content-admin')->mustChangePassword()->create(['password' => 'Sementara123']);

        $this->actingAs($staff)->put(route('admin.password.update'), [
            'username' => $staff->email,
            'current_password' => 'Sementara123',
            'password' => 'BaruAman456',
            'password_confirmation' => 'BaruAman456',
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertFalse($staff->fresh()->must_change_password);
    }

    // 6. Footer admin di dasar -----------------------------------------------------------------

    public function test_admin_footer_is_pushed_to_the_bottom_of_short_pages(): void
    {
        $html = $this->actingAs($this->createStaff('content-admin'))->get(route('admin.dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('<div class="admin-column flex-grow-1 d-flex flex-column">', $html);
        $this->assertMatchesRegularExpression('/<footer class="[^"]*\bmt-auto\b[^"]*"/', $html);

        $scss = (string) file_get_contents(resource_path('scss/_admin.scss'));
        $this->assertMatchesRegularExpression('/\.admin-shell\s*\{[^}]*min-height:\s*100vh;[^}]*min-height:\s*100dvh;/s', $scss);
        $this->assertMatchesRegularExpression('/\.admin-column\s*\{[^}]*min-height:\s*100vh;[^}]*min-height:\s*100dvh;/s', $scss);
    }

    public function test_auth_logo_styles_exist_in_the_shared_stylesheet(): void
    {
        $scss = (string) file_get_contents(resource_path('scss/_shared.scss'));

        $this->assertMatchesRegularExpression('/\.auth-logo\s*\{[^}]*height:\s*4rem;[^}]*width:\s*auto;/s', $scss);
        $this->assertStringContainsString('.auth-brand', $scss);
    }
}
