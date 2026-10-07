<?php

namespace Tests\Feature\Support;

use App\Models\User;
use App\Support\NavigationMenu;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

/**
 * F4B-R28: tab modul halaman #36 (`modules.facilities`). Route admin 4b baru didaftarkan Task 4 dan 5, jadi tes
 * mendaftarkan route palsu hanya bila belum ada; tes tetap benar sebelum dan sesudah route asli ada.
 */
class FacilitiesModuleTabsTest extends TestCase
{
    use InteractsWithStaff;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['admin.room-categories.index' => '/_uji/kamar', 'admin.facilities.index' => '/_uji/fasilitas'] as $name => $uri) {
            if (! Route::has($name)) {
                Route::middleware('web')->get($uri, fn () => 'ok')->name($name);
            }
        }

        Route::getRoutes()->refreshNameLookups();
    }

    public function test_the_configuration_follows_the_blueprint(): void
    {
        $this->assertSame([
            ['label' => 'Kamar dan Kapasitas', 'route' => 'admin.room-categories.index', 'permission' => 'facilities.manage|rooms.update-availability', 'active' => ['admin.room-categories.*']],
            ['label' => 'Fasilitas', 'route' => 'admin.facilities.index', 'permission' => 'facilities.manage', 'active' => ['admin.facilities.*']],
        ], config('navigation.modules.facilities'));
    }

    public function test_every_permission_in_the_tabs_exists_in_the_catalogue(): void
    {
        foreach (config('navigation.modules.facilities') as $tab) {
            foreach (explode('|', $tab['permission']) as $permission) {
                $this->assertContains($permission, RolePermissionSeeder::PERMISSIONS, "Izin {$permission} tidak ada di katalog.");
            }
        }
    }

    public function test_the_tabs_cover_the_same_routes_as_the_sidebar_group(): void
    {
        $group = collect(config('navigation.admin'))->firstWhere('group', 'Fasilitas dan Kamar');

        $this->assertNotNull($group);
        $this->assertSame(array_column($group['items'], 'route'), array_column(config('navigation.modules.facilities'), 'route'));
        $this->assertSame(array_column($group['items'], 'permission'), array_column(config('navigation.modules.facilities'), 'permission'));
        $this->assertSame(array_column($group['items'], 'label'), array_column(config('navigation.modules.facilities'), 'label'));
    }

    public function test_content_admin_and_super_admin_see_both_tabs(): void
    {
        foreach (['content-admin', 'super-admin'] as $role) {
            $labels = array_column(NavigationMenu::items('modules.facilities', $this->createStaff($role)), 'label');

            $this->assertSame(['Kamar dan Kapasitas', 'Fasilitas'], $labels, $role);
        }
    }

    public function test_the_registration_officer_sees_only_the_rooms_tab(): void
    {
        $items = NavigationMenu::items('modules.facilities', $this->createStaff('registration-officer'));

        $this->assertSame(['Kamar dan Kapasitas'], array_column($items, 'label'));
        $this->assertSame(route('admin.room-categories.index'), $items[0]['url']);
    }

    public function test_guests_and_patients_see_no_tabs(): void
    {
        $this->assertSame([], NavigationMenu::items('modules.facilities', null));
        $this->assertSame([], NavigationMenu::items('modules.facilities', User::factory()->create()));
    }

    public function test_a_tab_to_a_missing_route_is_hidden(): void
    {
        $tabs = config('navigation.modules.facilities');
        $tabs[1]['route'] = 'rute.belum.ada';
        config(['navigation.modules.facilities' => $tabs]);

        $this->assertSame(['Kamar dan Kapasitas'], array_column(NavigationMenu::items('modules.facilities', $this->createStaff('content-admin')), 'label'));

        $tabs[0]['route'] = 'rute.belum.ada.juga';
        config(['navigation.modules.facilities' => $tabs]);

        $this->assertSame([], NavigationMenu::items('modules.facilities', $this->createStaff('super-admin')));
    }

    public function test_the_content_tabs_are_unchanged_by_the_new_group(): void
    {
        $this->assertCount(7, config('navigation.modules.content'));
        $this->assertSame(['clinics', 'schedules', 'content', 'facilities'], array_keys(config('navigation.modules')));
    }
}
