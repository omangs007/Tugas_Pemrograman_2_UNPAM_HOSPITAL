<?php

namespace Tests\Feature\Auth;

use App\Enums\ActorType;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

class AdminLoginTest extends TestCase
{
    use InteractsWithStaff;
    use RefreshDatabase;

    private const PASSWORD = 'Rahasia123';

    private function postLogin(string $email, string $password = self::PASSWORD): TestResponse
    {
        return $this->from(route('admin.login'))
            ->post(route('admin.login.store'), ['email' => $email, 'password' => $password]);
    }

    /** @param  array<string, mixed>  $attributes */
    private function staffAccount(string $role = 'content-admin', array $attributes = []): User
    {
        return $this->createStaff($role, $attributes + ['email' => 'konten@unpam.test', 'password' => self::PASSWORD]);
    }

    private function lastFailure(): AuditLog
    {
        return AuditLog::query()->where('action', 'auth.staff_login_failed')->latest('id')->firstOrFail();
    }

    public function test_login_page_renders_the_form_for_guests(): void
    {
        $response = $this->get(route('admin.login'));

        $response->assertOk()
            ->assertSee('Masuk Panel Admin')
            ->assertSee('action="'.route('admin.login.store').'"', false)
            ->assertSee('name="email"', false)
            ->assertSee('name="password"', false)
            ->assertSee('<meta name="robots" content="noindex,nofollow">', false)
            ->assertDontSee('Ingat saya');
        $this->assertSame(1, substr_count($response->getContent(), '<h1'));
    }

    public function test_staff_can_log_in_and_it_is_audited_with_last_login_timestamp(): void
    {
        $staff = $this->staffAccount();

        $this->postLogin('konten@unpam.test')->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($staff);
        $this->assertNotNull($staff->fresh()->last_login_at);

        $log = AuditLog::query()->where('action', 'auth.staff_login')->sole();
        $this->assertSame(ActorType::Staff, $log->actor_type);
        $this->assertEquals($staff->id, $log->user_id);
        $this->assertEquals($staff->id, $log->auditable_id);
        $this->assertSame($staff->getMorphClass(), $log->auditable_type);
    }

    public function test_login_redirects_to_the_intended_admin_url_after_a_guest_redirect(): void
    {
        Route::middleware(['web', 'auth', 'account:staff', 'active'])->get('/admin/_uji-intended', fn () => 'ok')->name('uji.intended');
        Route::getRoutes()->refreshNameLookups();
        $this->staffAccount();

        $this->get('/admin/_uji-intended')->assertRedirect(route('admin.login'));
        $this->postLogin('konten@unpam.test')->assertRedirect(url('/admin/_uji-intended'));
    }

    public function test_session_id_changes_after_login(): void
    {
        $this->staffAccount();

        $this->get(route('admin.login'));
        $before = session()->getId();

        $this->postLogin('konten@unpam.test')->assertRedirect(route('admin.dashboard'));

        $this->assertNotSame($before, session()->getId());
    }

    public function test_email_with_spaces_and_uppercase_is_normalised_before_authentication(): void
    {
        $staff = $this->staffAccount('content-admin', ['email' => 'admin@x.test']);

        $this->postLogin('  Admin@X.TEST ')->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($staff);
    }

    public function test_unknown_email_and_wrong_password_give_identical_generic_responses(): void
    {
        $this->staffAccount();

        $unknown = $this->postLogin('tidak-ada@unpam.test', 'SalahSekali1');
        $unknownErrors = session('errors')->get('email');
        $wrong = $this->postLogin('konten@unpam.test', 'SalahSekali1');
        $wrongErrors = session('errors')->get('email');

        $this->assertSame($unknown->getStatusCode(), $wrong->getStatusCode());
        $this->assertSame($unknown->headers->get('Location'), $wrong->headers->get('Location'));
        $this->assertSame(['Email atau password salah.'], $unknownErrors);
        $this->assertSame($unknownErrors, $wrongErrors);
        $this->assertGuest();
    }

    public function test_failed_login_is_audited_with_a_reason_and_never_stores_the_password(): void
    {
        $staff = $this->staffAccount();

        $this->postLogin('konten@unpam.test', 'SalahSekali1');
        $bad = $this->lastFailure();
        $this->postLogin('tidak-ada@unpam.test', 'SalahSekali1');
        $unknown = $this->lastFailure();

        $this->assertSame(ActorType::Staff, $bad->actor_type);
        $this->assertEquals($staff->id, $bad->user_id);
        $this->assertEquals($staff->id, $bad->auditable_id);
        $this->assertEquals(['email' => 'konten@unpam.test', 'reason' => 'bad_credentials'], $bad->new_values);

        $this->assertSame(ActorType::System, $unknown->actor_type);
        $this->assertNull($unknown->user_id);
        $this->assertNull($unknown->auditable_id);
        $this->assertSame((new User)->getMorphClass(), $unknown->auditable_type);
        $this->assertEquals(['email' => 'tidak-ada@unpam.test', 'reason' => 'bad_credentials'], $unknown->new_values);

        $this->assertSame(0, DB::table('audit_logs')
            ->where('new_values', 'like', '%SalahSekali1%')
            ->orWhere('old_values', 'like', '%SalahSekali1%')
            ->orWhere('description', 'like', '%SalahSekali1%')
            ->count());
    }

    public function test_patient_account_cannot_log_in_through_the_admin_form(): void
    {
        User::factory()->patient()->create(['email' => 'pasien@unpam.test', 'password' => self::PASSWORD]);

        $this->postLogin('pasien@unpam.test')
            ->assertRedirect(route('admin.login'))
            ->assertSessionHasErrors(['email' => 'Email atau password salah.']);

        $this->assertGuest();
        $failure = $this->lastFailure();
        $this->assertSame('not_staff', $failure->new_values['reason']);
        $this->assertSame(ActorType::System, $failure->actor_type);
    }

    public function test_inactive_staff_cannot_log_in_and_gets_the_generic_message(): void
    {
        $staff = $this->staffAccount('content-admin', ['is_active' => false]);

        $this->postLogin('konten@unpam.test')
            ->assertRedirect(route('admin.login'))
            ->assertSessionHasErrors(['email' => 'Email atau password salah.']);

        $this->assertGuest();
        $this->get(route('admin.login'))->assertDontSee('Akun Anda tidak aktif');
        $failure = $this->lastFailure();
        $this->assertSame('inactive', $failure->new_values['reason']);
        $this->assertEquals($staff->id, $failure->user_id);
        $this->assertSame(ActorType::Staff, $failure->actor_type);
    }

    public function test_required_fields_and_email_format_are_validated_with_indonesian_messages(): void
    {
        $this->from(route('admin.login'))
            ->post(route('admin.login.store'), [])
            ->assertRedirect(route('admin.login'))
            ->assertSessionHasErrors(['email' => 'Email wajib diisi.', 'password' => 'Password wajib diisi.']);

        $this->postLogin('bukan-email')
            ->assertSessionHasErrors(['email' => 'Format email tidak valid.']);
        $this->assertGuest();
    }

    public function test_password_is_not_flashed_back_into_the_form(): void
    {
        $this->postLogin('konten@unpam.test', 'SalahSekali1');

        $this->assertNull(session('_old_input.password'));
    }
}
