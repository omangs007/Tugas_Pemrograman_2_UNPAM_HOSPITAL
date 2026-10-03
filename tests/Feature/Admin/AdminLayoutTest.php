<?php

namespace Tests\Feature\Admin;

use App\Support\NavigationMenu;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

class AdminLayoutTest extends TestCase
{
    use InteractsWithStaff;
    use RefreshDatabase;

    /**
     * @return array<string, array{string, string}>
     */
    public static function roles(): array
    {
        return [
            'super admin' => ['super-admin', 'Super Admin'],
            'admin konten' => ['content-admin', 'Admin Konten'],
            'petugas pendaftaran' => ['registration-officer', 'Petugas Pendaftaran'],
        ];
    }

    public function test_strict_mode_is_active_in_the_test_suite(): void
    {
        $this->assertTrue(Model::preventsLazyLoading());
    }

    #[DataProvider('roles')]
    public function test_dashboard_renders_for_each_role_with_strict_mode_using_acting_as(string $role, string $label): void
    {
        $staff = $this->createStaff($role, ['name' => 'Andi Uji']);

        $response = $this->actingAs($staff)->get('/admin');

        $response->assertOk()
            ->assertSee('Selamat datang, Andi Uji.')
            ->assertSee($label)
            ->assertSee('aria-label="Menu admin"', false)
            ->assertSee('UNPAM HOSPITAL Admin — prototipe');
        $this->assertSame(1, substr_count($response->getContent(), '<h1'));
    }

    #[DataProvider('roles')]
    public function test_dashboard_renders_for_each_role_after_a_real_login_with_strict_mode(string $role, string $label): void
    {
        $this->createStaff($role, ['email' => 'uji@unpam.test', 'password' => 'Rahasia123', 'name' => 'Budi Uji']);

        $this->post(route('admin.login.store'), ['email' => 'uji@unpam.test', 'password' => 'Rahasia123'])
            ->assertRedirect(route('admin.dashboard'));

        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Selamat datang, Budi Uji.')
            ->assertSee($label);
    }

    public function test_layout_has_sidebar_topbar_user_menu_noindex_and_skip_link(): void
    {
        $response = $this->actingAs($this->createStaff('super-admin', ['name' => 'Andi Uji']))->get('/admin')->assertOk();

        $response->assertSee('<html lang="id">', false)
            ->assertSee('<meta name="robots" content="noindex,nofollow">', false)
            ->assertSee('class="skip-link" href="#konten-utama"', false)
            ->assertSee('<main id="konten-utama" tabindex="-1"', false)
            ->assertSee('offcanvas-lg', false)
            ->assertSee('aria-label="Buka menu admin"', false)
            ->assertSee('Lihat Situs')
            ->assertSee('target="_blank" rel="noopener"', false)
            ->assertSee('action="'.route('admin.logout').'"', false)
            ->assertSee('Keluar')
            ->assertSee('aria-current="page"', false);
    }

    public function test_user_name_is_escaped_in_the_greeting(): void
    {
        $staff = $this->createStaff('content-admin', ['name' => '<script>alert(1)</script>']);

        $this->actingAs($staff)->get('/admin')
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    public function test_sidebar_hides_items_without_permission_and_groups_left_empty(): void
    {
        Route::middleware('web')->get('/_uji/boleh', fn () => 'ok')->name('uji.boleh');
        Route::middleware('web')->get('/_uji/dilarang', fn () => 'ok')->name('uji.dilarang');
        Route::getRoutes()->refreshNameLookups();
        config(['navigation.admin' => [
            ['label' => 'Dashboard', 'route' => 'admin.dashboard', 'icon' => 'bi-speedometer2', 'permission' => 'dashboard.view'],
            ['group' => 'Grup Terlihat', 'items' => [
                ['label' => 'Item Boleh', 'route' => 'uji.boleh', 'icon' => 'bi-gear', 'permission' => 'doctors.manage'],
                ['label' => 'Item Dilarang', 'route' => 'uji.dilarang', 'icon' => 'bi-gear', 'permission' => 'staff.manage'],
            ]],
            ['group' => 'Grup Tersembunyi', 'items' => [
                ['label' => 'Hanya Dilarang', 'route' => 'uji.dilarang', 'icon' => 'bi-gear', 'permission' => 'audit.view'],
            ]],
        ]]);

        $this->actingAs($this->createStaff('content-admin'))->get('/admin')
            ->assertOk()
            ->assertSee('Grup Terlihat')
            ->assertSee('Item Boleh')
            ->assertDontSee('Item Dilarang')
            ->assertDontSee('Grup Tersembunyi')
            ->assertDontSee('Hanya Dilarang');

        $this->actingAs($this->createStaff('super-admin'))->get('/admin')
            ->assertSee('Item Dilarang')
            ->assertSee('Grup Tersembunyi');
    }

    public function test_real_admin_menu_always_starts_with_the_dashboard_for_every_role(): void
    {
        foreach (['super-admin', 'content-admin', 'registration-officer'] as $role) {
            $items = NavigationMenu::items('admin', $this->createStaff($role));

            $this->assertSame('admin.dashboard', $items[0]['route'] ?? null, "Peran {$role} harus melihat Dashboard.");
        }
    }
}
