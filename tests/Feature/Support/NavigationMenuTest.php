<?php

namespace Tests\Feature\Support;

use App\Support\NavigationMenu;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

class NavigationMenuTest extends TestCase
{
    use InteractsWithStaff;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('web')->get('/_uji/satu', fn () => 'satu')->name('uji.satu');
        Route::middleware('web')->get('/_uji/dua', fn () => 'dua')->name('uji.dua');
        Route::middleware('web')->get('/_uji/aktif', fn () => response()->json(NavigationMenu::items('uji')))->name('uji.aktif');
        Route::getRoutes()->refreshNameLookups();
    }

    public function test_items_without_a_registered_route_are_dropped(): void
    {
        config(['navigation.uji' => [
            ['label' => 'Ada', 'route' => 'uji.satu'],
            ['label' => 'Tidak Ada', 'route' => 'rute.belum.ada'],
        ]]);

        $items = NavigationMenu::items('uji');

        $this->assertCount(1, $items);
        $this->assertSame('Ada', $items[0]['label']);
        $this->assertSame(route('uji.satu'), $items[0]['url']);
        $this->assertFalse($items[0]['active']);
    }

    public function test_unknown_area_returns_an_empty_list(): void
    {
        $this->assertSame([], NavigationMenu::items('area-yang-tidak-ada'));
    }

    public function test_empty_groups_and_dropdowns_are_removed_but_partial_ones_are_kept(): void
    {
        config(['navigation.uji' => [
            ['group' => 'Grup Kosong', 'items' => [
                ['label' => 'Belum Ada', 'route' => 'rute.belum.ada'],
            ]],
            ['group' => 'Grup Terisi', 'items' => [
                ['label' => 'Ada', 'route' => 'uji.satu'],
                ['label' => 'Belum Ada', 'route' => 'rute.belum.ada'],
            ]],
            ['label' => 'Induk Kosong', 'children' => [
                ['label' => 'Belum Ada', 'route' => 'rute.belum.ada'],
            ]],
            ['label' => 'Induk Terisi', 'children' => [
                ['label' => 'Anak Ada', 'route' => 'uji.dua'],
                ['label' => 'Anak Belum Ada', 'route' => 'rute.belum.ada'],
            ]],
        ]]);

        $items = NavigationMenu::items('uji');

        $this->assertCount(2, $items);
        $this->assertSame('Grup Terisi', $items[0]['group']);
        $this->assertCount(1, $items[0]['items']);
        $this->assertSame('Induk Terisi', $items[1]['label']);
        $this->assertCount(1, $items[1]['children']);
        $this->assertSame('Anak Ada', $items[1]['children'][0]['label']);
    }

    public function test_permission_alternatives_keep_item_when_user_has_any_of_them(): void
    {
        config(['navigation.uji' => [
            ['label' => 'Kelola Dokter atau Staf', 'route' => 'uji.satu', 'permission' => 'staff.manage|doctors.manage'],
            ['label' => 'Lihat Pendaftaran atau Staf', 'route' => 'uji.dua', 'permission' => 'staff.manage|appointments.view'],
        ]]);
        $konten = $this->createStaff('content-admin');
        $petugas = $this->createStaff('registration-officer');
        $super = $this->createStaff('super-admin');

        $this->assertSame(['Kelola Dokter atau Staf'], array_column(NavigationMenu::items('uji', $konten), 'label'));
        $this->assertSame(['Lihat Pendaftaran atau Staf'], array_column(NavigationMenu::items('uji', $petugas), 'label'));
        $this->assertCount(2, NavigationMenu::items('uji', $super));
    }

    public function test_items_with_permission_are_hidden_from_guests_but_plain_items_stay(): void
    {
        config(['navigation.uji' => [
            ['label' => 'Tanpa Izin', 'route' => 'uji.satu'],
            ['label' => 'Butuh Izin', 'route' => 'uji.dua', 'permission' => 'dashboard.view'],
        ]]);

        $this->assertSame(['Tanpa Izin'], array_column(NavigationMenu::items('uji', null), 'label'));
    }

    public function test_active_flag_follows_the_current_route_and_custom_patterns(): void
    {
        config(['navigation.uji' => [
            ['label' => 'Aktif', 'route' => 'uji.aktif'],
            ['label' => 'Pola', 'route' => 'uji.satu', 'active' => ['uji.aktif', 'uji.lain.*']],
            ['label' => 'Tidak', 'route' => 'uji.dua'],
            ['label' => 'Induk', 'children' => [
                ['label' => 'Anak', 'route' => 'uji.aktif'],
                ['label' => 'Anak Dua', 'route' => 'uji.dua'],
            ]],
        ]]);

        $items = $this->getJson('/_uji/aktif')->assertOk()->json();

        $this->assertTrue($items[0]['active']);
        $this->assertTrue($items[1]['active']);
        $this->assertFalse($items[2]['active']);
        $this->assertTrue($items[3]['active']);
        $this->assertTrue($items[3]['children'][0]['active']);
        $this->assertFalse($items[3]['children'][1]['active']);
    }

    public function test_real_admin_config_only_uses_permissions_from_the_catalogue_and_bootstrap_icons(): void
    {
        $entries = config('navigation.admin');
        $this->assertIsArray($entries);

        $links = [];
        foreach ($entries as $entry) {
            foreach ($entry['items'] ?? [$entry] as $link) {
                $links[] = $link;
            }
        }

        $this->assertNotEmpty($links);
        foreach ($links as $link) {
            $this->assertArrayHasKey('route', $link);
            $this->assertStringStartsWith('admin.', $link['route']);
            $this->assertStringStartsWith('bi-', $link['icon']);
            foreach (explode('|', $link['permission']) as $permission) {
                $this->assertContains($permission, RolePermissionSeeder::PERMISSIONS, "Izin {$permission} tidak ada di katalog.");
            }
        }
    }

    public function test_real_public_and_footer_config_keep_the_canonical_route_names(): void
    {
        $names = [];
        $public = config('navigation.public');
        $footer = config('navigation.footer');
        array_walk_recursive($public, function ($value, $key) use (&$names): void {
            if ($key === 'route') {
                $names[] = $value;
            }
        });
        array_walk_recursive($footer, function ($value, $key) use (&$names): void {
            if ($key === 'route') {
                $names[] = $value;
            }
        });

        $this->assertEqualsCanonicalizing([
            'about', 'vision-mission', 'clinics.index', 'doctors.index', 'schedules.index', 'facilities.index',
            'rooms.index', 'insurance', 'registration-guide', 'articles.index', 'galleries.index', 'faq', 'contact',
        ], array_values(array_unique($names)));
    }
}
