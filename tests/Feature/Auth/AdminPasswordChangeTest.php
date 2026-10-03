<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

class AdminPasswordChangeTest extends TestCase
{
    use InteractsWithStaff;
    use RefreshDatabase;

    private const TEMPORARY_PASSWORD = 'SementaraAbc1';

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
}
