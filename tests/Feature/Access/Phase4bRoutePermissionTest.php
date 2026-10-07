<?php

namespace Tests\Feature\Access;

use App\Support\NavigationMenu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

/**
 * KNS-01 (bagian fase 4b), KNS-02 (route admin berizin), KNS-03 (`facilities.manage`, `rooms.update-availability`,
 * `messages.manage`), dan bagian route dari CRD-13: 16 nama route admin 4b, izin, nama parameter, URI Indonesia,
 * enam route publik terbuka, serta route yang sengaja tidak ada.
 */
class Phase4bRoutePermissionTest extends TestCase
{
    use InteractsWithStaff;
    use RefreshDatabase;

    private const FACILITIES = 'permission:facilities.manage';

    private const ROOMS_VIEW = 'permission:facilities.manage|rooms.update-availability';

    private const AVAILABILITY = 'permission:rooms.update-availability';

    private const MESSAGES = 'permission:messages.manage';

    /** @return array<string, array{string, string}> */
    public static function permissions(): array
    {
        $rows = [];

        foreach (['index', 'create', 'store', 'edit', 'update', 'destroy'] as $action) {
            $rows["admin.facilities.{$action}"] = self::FACILITIES;
        }

        foreach (['create', 'store', 'edit', 'update', 'destroy'] as $action) {
            $rows["admin.room-categories.{$action}"] = self::FACILITIES;
        }

        $rows['admin.room-categories.index'] = self::ROOMS_VIEW;
        $rows['admin.room-categories.availability'] = self::AVAILABILITY;

        foreach (['index', 'show', 'status'] as $action) {
            $rows["admin.messages.{$action}"] = self::MESSAGES;
        }

        $cases = [];

        foreach ($rows as $name => $permission) {
            $cases[$name] = [$name, $permission];
        }

        return $cases;
    }

    #[DataProvider('permissions')]
    public function test_route_exists_with_the_documented_permission_middleware(string $name, string $permission): void
    {
        $route = Route::getRoutes()->getByName($name);

        $this->assertNotNull($route, "Route {$name} belum terdaftar.");

        $middleware = array_filter($route->gatherMiddleware(), 'is_string');
        $this->assertContains($permission, $middleware, "Route {$name} harus memakai {$permission}.");
        $this->assertContains('auth', $middleware, "Route {$name} harus berada di grup admin yang memakai auth.");
        $this->assertCount(1, array_filter($middleware, fn (string $item): bool => str_starts_with($item, 'permission:')), "Route {$name} hanya boleh memakai satu middleware izin.");
    }

    public function test_the_phase_4b_admin_route_names_are_exactly_the_sixteen_documented_ones(): void
    {
        $names = [];

        foreach (Route::getRoutes()->getRoutes() as $route) {
            $name = (string) $route->getName();

            if (preg_match('/^admin\.(facilities|room-categories|messages)\./', $name) === 1) {
                $names[] = $name;
            }
        }

        sort($names);

        $this->assertSame(16, count($names));
        $this->assertSame(array_values(array_unique($names)), $names, 'Nama route tidak boleh ganda.');
        $this->assertEqualsCanonicalizing(array_keys(self::permissions()), $names);
    }

    public function test_the_admin_area_has_one_hundred_and_six_routes_after_phase_4b(): void
    {
        $count = 0;

        foreach (Route::getRoutes()->getRoutes() as $route) {
            if ($route->uri() === 'admin' || str_starts_with($route->uri(), 'admin/')) {
                $count++;
            }
        }

        // 31 fase 1-2 + 19 fase 3 + 40 fase 4a + 16 fase 4b (F4B-R13); fase 5 dan seterusnya wajib memperbarui angka ini.
        $this->assertSame(106, $count);
    }

    public function test_route_parameters_are_named_as_documented(): void
    {
        $expected = [
            'admin.facilities.edit' => ['facility'],
            'admin.facilities.update' => ['facility'],
            'admin.facilities.destroy' => ['facility'],
            'admin.room-categories.edit' => ['roomCategory'],
            'admin.room-categories.update' => ['roomCategory'],
            'admin.room-categories.destroy' => ['roomCategory'],
            'admin.room-categories.availability' => ['roomCategory'],
            'admin.messages.show' => ['message'],
            'admin.messages.status' => ['message'],
        ];

        foreach ($expected as $name => $parameters) {
            $this->assertSame($parameters, Route::getRoutes()->getByName($name)->parameterNames(), $name);
        }
    }

    public function test_messages_have_no_destroy_create_edit_or_update_routes_and_facilities_and_rooms_have_no_show(): void
    {
        foreach (['admin.messages.destroy', 'admin.messages.create', 'admin.messages.store', 'admin.messages.edit', 'admin.messages.update', 'admin.facilities.show', 'admin.room-categories.show'] as $name) {
            $this->assertNull(Route::getRoutes()->getByName($name), "{$name} tidak boleh ada (00 C, 00 I.6).");
        }

        $this->delete('/admin/pesan/1')->assertStatus(405);
        $this->put('/admin/pesan/1')->assertStatus(405);
        $this->post('/admin/pesan')->assertStatus(405);
        $this->get('/admin/fasilitas/1')->assertStatus(405);
        $this->get('/admin/kamar/1')->assertStatus(405);
    }

    public function test_methods_of_the_non_resource_routes(): void
    {
        $this->assertSame(['PATCH'], Route::getRoutes()->getByName('admin.room-categories.availability')->methods());
        $this->assertSame(['PATCH'], Route::getRoutes()->getByName('admin.messages.status')->methods());
        $this->assertSame(['GET', 'HEAD'], Route::getRoutes()->getByName('admin.room-categories.index')->methods());
        $this->assertSame(['GET', 'HEAD'], Route::getRoutes()->getByName('admin.messages.index')->methods());
        $this->assertSame(['GET', 'HEAD'], Route::getRoutes()->getByName('admin.messages.show')->methods());
    }

    public function test_uris_use_indonesian_segments(): void
    {
        $this->assertSame('/admin/fasilitas', route('admin.facilities.index', [], false));
        $this->assertSame('/admin/fasilitas/buat', route('admin.facilities.create', [], false));
        $this->assertSame('/admin/fasilitas/4/ubah', route('admin.facilities.edit', 4, false));
        $this->assertSame('/admin/fasilitas/4', route('admin.facilities.update', 4, false));
        $this->assertSame('/admin/kamar', route('admin.room-categories.index', [], false));
        $this->assertSame('/admin/kamar/buat', route('admin.room-categories.create', [], false));
        $this->assertSame('/admin/kamar/5/ubah', route('admin.room-categories.edit', 5, false));
        $this->assertSame('/admin/kamar/5/ketersediaan', route('admin.room-categories.availability', 5, false));
        $this->assertSame('/admin/pesan', route('admin.messages.index', [], false));
        $this->assertSame('/admin/pesan/7', route('admin.messages.show', 7, false));
        $this->assertSame('/admin/pesan/7/status', route('admin.messages.status', 7, false));
    }

    public function test_static_segments_are_matched_before_parameterised_routes(): void
    {
        $routes = Route::getRoutes();

        $this->assertSame('admin.facilities.create', $routes->match(Request::create('/admin/fasilitas/buat', 'GET'))->getName());
        $this->assertSame('admin.room-categories.create', $routes->match(Request::create('/admin/kamar/buat', 'GET'))->getName());
        $this->assertSame('admin.room-categories.availability', $routes->match(Request::create('/admin/kamar/7/ketersediaan', 'PATCH'))->getName());
        $this->assertSame('admin.room-categories.destroy', $routes->match(Request::create('/admin/kamar/7', 'DELETE'))->getName());
        $this->assertSame('admin.facilities.update', $routes->match(Request::create('/admin/fasilitas/7', 'PUT'))->getName());
        $this->assertSame('admin.messages.status', $routes->match(Request::create('/admin/pesan/7/status', 'PATCH'))->getName());
    }

    public function test_admin_ids_must_be_numeric(): void
    {
        $admin = $this->createStaff('super-admin');

        $this->actingAs($admin)->get('/admin/fasilitas/abc/ubah')->assertNotFound();
        $this->actingAs($admin)->get('/admin/kamar/abc/ubah')->assertNotFound();
        $this->actingAs($admin)->get('/admin/pesan/abc')->assertNotFound();
        $this->actingAs($admin)->patch('/admin/kamar/abc/ketersediaan')->assertNotFound();
        $this->actingAs($admin)->patch('/admin/pesan/abc/status')->assertNotFound();
    }

    /** @return array<string, array{string, string, list<string>}> */
    public static function publicRoutes(): array
    {
        return [
            'facilities.index' => ['facilities.index', 'fasilitas', ['GET', 'HEAD']],
            'facilities.show' => ['facilities.show', 'fasilitas/{facility}', ['GET', 'HEAD']],
            'rooms.index' => ['rooms.index', 'kamar-rawat-inap', ['GET', 'HEAD']],
            'rooms.show' => ['rooms.show', 'kamar-rawat-inap/{roomCategory}', ['GET', 'HEAD']],
            'contact' => ['contact', 'kontak', ['GET', 'HEAD']],
            'contact.store' => ['contact.store', 'kontak', ['POST']],
        ];
    }

    /**
     * @param  list<string>  $methods
     */
    #[DataProvider('publicRoutes')]
    public function test_public_routes_are_open_without_auth_or_permission_middleware(string $name, string $uri, array $methods): void
    {
        $route = Route::getRoutes()->getByName($name);

        $this->assertNotNull($route, "Route {$name} belum terdaftar.");
        $this->assertSame($methods, $route->methods());
        $this->assertSame($uri, $route->uri());

        foreach (array_filter($route->gatherMiddleware(), 'is_string') as $middleware) {
            $this->assertStringNotContainsString('permission:', $middleware, "{$name}: {$middleware}");
            $this->assertStringNotContainsString('auth', $middleware, "{$name}: {$middleware}");
            $this->assertStringNotContainsString('account:', $middleware, "{$name}: {$middleware}");
        }
    }

    public function test_facility_and_room_detail_routes_bind_by_slug(): void
    {
        $this->assertSame('slug', Route::getRoutes()->getByName('facilities.show')->bindingFieldFor('facility'));
        $this->assertSame('slug', Route::getRoutes()->getByName('rooms.show')->bindingFieldFor('roomCategory'));
    }

    public function test_only_the_contact_form_submission_is_throttled(): void
    {
        $store = array_filter(Route::getRoutes()->getByName('contact.store')->gatherMiddleware(), 'is_string');
        $this->assertContains('throttle:contact', $store);

        foreach (['contact', 'facilities.index', 'facilities.show', 'rooms.index', 'rooms.show'] as $name) {
            $middleware = array_filter(Route::getRoutes()->getByName($name)->gatherMiddleware(), 'is_string');
            $this->assertNotContains('throttle:contact', $middleware, $name);
        }
    }

    public function test_public_uris_use_indonesian_segments(): void
    {
        $this->assertSame('/fasilitas', route('facilities.index', [], false));
        $this->assertSame('/fasilitas/musala', route('facilities.show', 'musala', false));
        $this->assertSame('/kamar-rawat-inap', route('rooms.index', [], false));
        $this->assertSame('/kamar-rawat-inap/vip', route('rooms.show', 'vip', false));
        $this->assertSame('/kontak', route('contact', [], false));
        $this->assertSame('/kontak', route('contact.store', [], false));
    }

    public function test_the_facilities_module_tabs_follow_the_documented_configuration(): void
    {
        $tabs = config('navigation.modules.facilities');

        $this->assertSame(['admin.room-categories.index', 'admin.facilities.index'], array_column($tabs, 'route'));
        $this->assertSame(['Kamar dan Kapasitas', 'Fasilitas'], array_column($tabs, 'label'));
        $this->assertSame(['facilities.manage|rooms.update-availability', 'facilities.manage'], array_column($tabs, 'permission'));
        $this->assertSame([['admin.room-categories.*'], ['admin.facilities.*']], array_column($tabs, 'active'));
    }

    public function test_the_facilities_tabs_follow_the_user_permissions(): void
    {
        $this->assertSame(['Kamar dan Kapasitas', 'Fasilitas'], array_column(NavigationMenu::items('modules.facilities', $this->createStaff('content-admin')), 'label'));
        $this->assertSame(['Kamar dan Kapasitas', 'Fasilitas'], array_column(NavigationMenu::items('modules.facilities', $this->createStaff('super-admin')), 'label'));
        $this->assertSame(['Kamar dan Kapasitas'], array_column(NavigationMenu::items('modules.facilities', $this->createStaff('registration-officer')), 'label'));
        $this->assertSame([], NavigationMenu::items('modules.facilities', null));
    }

    public function test_the_message_inbox_is_not_a_module_tab(): void
    {
        foreach (config('navigation.modules') as $group => $tabs) {
            $this->assertNotContains('admin.messages.index', array_column($tabs, 'route'), $group);
        }
    }
}
