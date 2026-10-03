<?php

namespace Tests\Feature\Auth;

use App\Enums\ActorType;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

class AdminPasswordChangeTest extends TestCase
{
    use InteractsWithStaff;
    use RefreshDatabase;

    private const TEMPORARY_PASSWORD = 'SementaraAbc1';

    private const NEW_PASSWORD = 'PasswordBaru123';

    protected function setUp(): void
    {
        parent::setUp();

        // Route admin lain yang dipagari G-ADMIN penuh (fase 2+ menambah yang sungguhan).
        Route::middleware(['web', 'auth', 'account:staff', 'active', 'password.changed'])
            ->get('/admin/_uji-lain', fn () => 'halaman lain')
            ->name('uji.admin-lain');
        Route::getRoutes()->refreshNameLookups();
    }

    /** @param  array<string, mixed>  $attributes */
    private function forcedStaff(array $attributes = []): User
    {
        return $this->createStaff('content-admin', $attributes + [
            'email' => 'baru@unpam.test',
            'password' => self::TEMPORARY_PASSWORD,
            'must_change_password' => true,
        ]);
    }

    // --- AKS-09: pengalihan ------------------------------------------------------------------

    public function test_staff_who_must_change_password_is_redirected_from_every_admin_page(): void
    {
        $staff = $this->forcedStaff();

        $this->actingAs($staff)->get('/admin')
            ->assertRedirect(route('admin.password.edit'))
            ->assertSessionHas('warning', 'Anda harus mengganti password sementara sebelum melanjutkan.');

        $this->actingAs($staff)->get('/admin/_uji-lain')
            ->assertRedirect(route('admin.password.edit'));
    }

    public function test_json_requests_get_403_instead_of_a_redirect_while_the_password_is_temporary(): void
    {
        $this->actingAs($this->forcedStaff())->getJson('/admin/_uji-lain')
            ->assertForbidden()
            ->assertExactJson(['message' => 'Ganti password sementara terlebih dahulu.']);
    }

    public function test_staff_with_a_permanent_password_is_not_redirected(): void
    {
        $this->actingAs($this->createStaff('content-admin'))->get('/admin/_uji-lain')->assertOk()->assertSee('halaman lain');
    }

    public function test_logout_is_never_redirected_while_the_password_is_temporary(): void
    {
        $this->actingAs($this->forcedStaff())->post(route('admin.logout'))
            ->assertRedirect(route('admin.login'));

        $this->assertGuest();
    }

    public function test_login_of_forced_staff_leads_to_the_change_password_page(): void
    {
        $this->forcedStaff();

        $this->post(route('admin.login.store'), ['email' => 'baru@unpam.test', 'password' => self::TEMPORARY_PASSWORD])
            ->assertRedirect(route('admin.dashboard'));

        $this->get(route('admin.dashboard'))->assertRedirect(route('admin.password.edit'));
    }

    // --- halaman ganti password --------------------------------------------------------------

    /** @param  array<string, string>  $data */
    private function putPassword(array $data): TestResponse
    {
        return $this->from(route('admin.password.edit'))->put(route('admin.password.update'), $data);
    }

    /** @return array<string, string> */
    private function validPayload(): array
    {
        return [
            'current_password' => self::TEMPORARY_PASSWORD,
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ];
    }

    public function test_edit_page_is_reachable_while_forced_and_explains_why(): void
    {
        $response = $this->actingAs($this->forcedStaff())->get(route('admin.password.edit'));

        $response->assertOk()
            ->assertSee('Ganti Password')
            ->assertSee('Anda harus mengganti password sementara sebelum melanjutkan.')
            ->assertSee('action="'.route('admin.password.update').'"', false)
            ->assertSee('name="_method" value="PUT"', false)
            ->assertSee('name="current_password"', false)
            ->assertSee('name="password_confirmation"', false)
            ->assertSee('<meta name="robots" content="noindex,nofollow">', false)
            ->assertDontSee('Kembali ke dasbor');
        $this->assertSame(1, substr_count($response->getContent(), '<h1'));
    }

    public function test_edit_page_for_a_regular_change_offers_a_link_back_to_the_dashboard(): void
    {
        $this->actingAs($this->createStaff('content-admin'))->get(route('admin.password.edit'))
            ->assertOk()
            ->assertSee('Kembali ke dasbor')
            ->assertDontSee('Anda harus mengganti password sementara');
    }

    public function test_forced_staff_can_change_the_password_and_then_reach_the_dashboard(): void
    {
        $staff = $this->forcedStaff();

        $this->actingAs($staff);
        $this->putPassword($this->validPayload())
            ->assertRedirect(route('admin.dashboard'))
            ->assertSessionHas('success', 'Password berhasil diganti.');

        $fresh = $staff->fresh();
        $this->assertFalse($fresh->must_change_password);
        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $fresh->password));
        $this->assertFalse(Hash::check(self::TEMPORARY_PASSWORD, $fresh->password));

        $this->get('/admin')->assertOk()->assertSee('Selamat datang');
        $this->get('/admin/_uji-lain')->assertOk();
    }

    public function test_new_password_is_stored_as_a_bcrypt_hash_and_never_appears_in_the_audit_log(): void
    {
        $staff = $this->forcedStaff();

        $this->actingAs($staff);
        $this->putPassword($this->validPayload())->assertRedirect(route('admin.dashboard'));

        $stored = DB::table('users')->where('id', $staff->id)->value('password');
        $this->assertStringStartsWith('$2y$', $stored);
        $this->assertNotSame(self::NEW_PASSWORD, $stored);

        foreach ([self::NEW_PASSWORD, self::TEMPORARY_PASSWORD] as $secret) {
            $this->assertSame(0, DB::table('audit_logs')
                ->where('old_values', 'like', "%{$secret}%")
                ->orWhere('new_values', 'like', "%{$secret}%")
                ->orWhere('description', 'like', "%{$secret}%")
                ->count(), 'Password asli tidak boleh ada di audit_logs.');
        }
    }

    public function test_password_change_is_audited_with_the_previous_flag(): void
    {
        $forced = $this->forcedStaff();
        $this->actingAs($forced);
        $this->putPassword($this->validPayload())->assertRedirect(route('admin.dashboard'));

        $log = AuditLog::query()->where('action', 'auth.password_changed')->sole();
        $this->assertSame(ActorType::Staff, $log->actor_type);
        $this->assertEquals($forced->id, $log->user_id);
        $this->assertEquals($forced->id, $log->auditable_id);
        $this->assertSame(['must_change_password' => true], $log->old_values);
        $this->assertSame(['must_change_password' => false], $log->new_values);

        $regular = $this->createStaff('registration-officer', ['password' => self::TEMPORARY_PASSWORD]);
        $this->actingAs($regular);
        $this->putPassword($this->validPayload())->assertRedirect(route('admin.dashboard'));

        $second = AuditLog::query()->where('action', 'auth.password_changed')->latest('id')->first();
        $this->assertSame(['must_change_password' => false], $second->old_values);
    }

    public function test_session_id_is_regenerated_after_the_password_change(): void
    {
        $this->actingAs($this->forcedStaff());
        $this->get(route('admin.password.edit'));
        $before = session()->getId();

        $this->putPassword($this->validPayload())->assertRedirect(route('admin.dashboard'));

        $this->assertNotSame($before, session()->getId());
    }

    /**
     * @return array<string, array{array<string, string>, string, string}>
     */
    public static function invalidPayloads(): array
    {
        $ok = ['current_password' => self::TEMPORARY_PASSWORD, 'password' => self::NEW_PASSWORD, 'password_confirmation' => self::NEW_PASSWORD];

        return [
            'password saat ini salah' => [['current_password' => 'SalahSekali1'] + $ok, 'current_password', 'Password saat ini tidak sesuai.'],
            'password saat ini kosong' => [['current_password' => ''] + $ok, 'current_password', 'Password saat ini wajib diisi.'],
            'sama dengan password lama' => [['password' => self::TEMPORARY_PASSWORD, 'password_confirmation' => self::TEMPORARY_PASSWORD] + $ok, 'password', 'Password baru harus berbeda dari password saat ini.'],
            'tanpa angka' => [['password' => 'Abcdefghij', 'password_confirmation' => 'Abcdefghij'] + $ok, 'password', 'Password harus mengandung angka.'],
            'tanpa huruf' => [['password' => '1234567890', 'password_confirmation' => '1234567890'] + $ok, 'password', 'Password harus mengandung huruf.'],
            'terlalu pendek' => [['password' => 'Ab1', 'password_confirmation' => 'Ab1'] + $ok, 'password', 'Password minimal 8 karakter.'],
            'terlalu panjang' => [['password' => str_repeat('Aa1', 21).'AB', 'password_confirmation' => str_repeat('Aa1', 21).'AB'] + $ok, 'password', 'Password maksimal 64 karakter.'],
            'konfirmasi tidak cocok' => [['password_confirmation' => 'Lain12345'] + $ok, 'password', 'Konfirmasi password tidak cocok.'],
            'password baru kosong' => [['password' => ''] + $ok, 'password', 'Password baru wajib diisi.'],
            'konfirmasi kosong' => [['password_confirmation' => ''] + $ok, 'password_confirmation', 'Konfirmasi password wajib diisi.'],
        ];
    }

    /** @param  array<string, string>  $payload */
    #[DataProvider('invalidPayloads')]
    public function test_invalid_input_is_rejected_with_the_documented_messages_and_changes_nothing(array $payload, string $field, string $message): void
    {
        $staff = $this->forcedStaff();
        $originalHash = $staff->password;

        $this->actingAs($staff);
        $this->putPassword($payload)
            ->assertRedirect(route('admin.password.edit'))
            ->assertSessionHasErrors([$field => $message]);

        $fresh = $staff->fresh();
        $this->assertSame($originalHash, $fresh->password);
        $this->assertTrue($fresh->must_change_password);
        $this->assertSame(0, AuditLog::query()->where('action', 'auth.password_changed')->count());
    }

    public function test_password_fields_are_never_flashed_back_into_the_form(): void
    {
        $this->actingAs($this->forcedStaff());
        $this->putPassword(['current_password' => 'SalahSekali1', 'password' => 'Ab1', 'password_confirmation' => 'Ab1']);

        $this->assertNull(session('_old_input.current_password'));
        $this->assertNull(session('_old_input.password'));
        $this->assertNull(session('_old_input.password_confirmation'));
    }

    public function test_change_password_routes_reject_guests_patients_and_deactivated_staff(): void
    {
        $this->get(route('admin.password.edit'))->assertRedirect(route('admin.login'));
        $this->put(route('admin.password.update'), $this->validPayload())->assertRedirect(route('admin.login'));

        $this->actingAs(User::factory()->patient()->create())
            ->get(route('admin.password.edit'))
            ->assertForbidden();

        $staff = $this->forcedStaff();
        $this->actingAs($staff)->get(route('admin.password.edit'))->assertOk();
        $staff->forceFill(['is_active' => false])->save();

        $this->get(route('admin.password.edit'))
            ->assertRedirect(route('admin.login'))
            ->assertSessionHas('error', 'Akun Anda dinonaktifkan. Silakan hubungi administrator rumah sakit.');
    }

    public function test_user_menu_links_to_the_change_password_page(): void
    {
        $this->actingAs($this->createStaff('content-admin'))->get('/admin')
            ->assertOk()
            ->assertSee('Ganti Password')
            ->assertSee('href="'.route('admin.password.edit').'"', false);
    }
}
