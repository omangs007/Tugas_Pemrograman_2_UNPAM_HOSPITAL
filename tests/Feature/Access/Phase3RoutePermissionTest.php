<?php

namespace Tests\Feature\Access;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * KNS-01 (bagian fase 3), KNS-02 (route admin berizin), dan bagian route dari CRD-13: nama route, izin, URI,
 * urutan segmen statis sebelum berparameter, dan route yang sengaja tidak ada.
 */
class Phase3RoutePermissionTest extends TestCase
{
    private const VIEW = 'permission:schedules.view';

    private const MANAGE = 'permission:schedules.manage';

    /** @return array<string, array{string, string}> */
    public static function permissions(): array
    {
        $rows = [];

        foreach (['schedules', 'exceptions'] as $resource) {
            $rows["admin.{$resource}.index"] = self::VIEW;

            foreach (['create', 'store', 'edit', 'update', 'destroy'] as $action) {
                $rows["admin.{$resource}.{$action}"] = self::MANAGE;
            }
        }

        $rows['admin.sessions.index'] = self::VIEW;

        foreach (['create', 'store', 'edit', 'update', 'status', 'destroy'] as $action) {
            $rows["admin.sessions.{$action}"] = self::MANAGE;
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

    public function test_the_phase_3_admin_route_names_are_exactly_the_nineteen_documented_ones(): void
    {
        $names = [];

        foreach (Route::getRoutes()->getRoutes() as $route) {
            $name = (string) $route->getName();

            if (preg_match('/^admin\.(schedules|exceptions|sessions)\./', $name) === 1) {
                $names[] = $name;
            }
        }

        sort($names);

        $this->assertSame(19, count($names));
        $this->assertSame(array_values(array_unique($names)), $names, 'Nama route tidak boleh ganda.');
        $this->assertEqualsCanonicalizing(
            array_keys(self::permissions()),
            $names,
            'Hanya route fase 3 yang terdaftar; Sesi Terdampak (`admin.sessions.review`, `.resolve`) menyusul di fase 7 dan mengubah daftar ini.',
        );
    }

    public function test_public_schedule_routes_are_open_get_routes_without_auth_or_permission_middleware(): void
    {
        foreach (['doctors.index' => '/dokter', 'doctors.show' => '/dokter/{doctor}', 'schedules.index' => '/jadwal-dokter'] as $name => $uri) {
            $route = Route::getRoutes()->getByName($name);

            $this->assertNotNull($route, "Route {$name} belum terdaftar.");
            $this->assertSame(['GET', 'HEAD'], $route->methods());
            $this->assertSame(ltrim($uri, '/'), $route->uri());

            foreach (array_filter($route->gatherMiddleware(), 'is_string') as $middleware) {
                $this->assertStringNotContainsString('permission:', $middleware, "{$name}: {$middleware}");
                $this->assertStringNotContainsString('auth', $middleware, "{$name}: {$middleware}");
                $this->assertStringNotContainsString('account:', $middleware, "{$name}: {$middleware}");
            }
        }
    }

    public function test_the_doctor_detail_route_binds_by_slug(): void
    {
        $route = Route::getRoutes()->getByName('doctors.show');

        $this->assertSame('slug', $route->bindingFieldFor('doctor'));
    }

    public function test_routes_that_must_not_exist_are_absent(): void
    {
        foreach (['admin.schedules.show', 'admin.sessions.show', 'admin.exceptions.show'] as $name) {
            $this->assertNull(Route::getRoutes()->getByName($name), "{$name} tidak boleh ada (00 I.6).");
        }
    }

    public function test_crd_13_destroy_routes_do_not_exist_for_entities_that_are_never_deleted(): void
    {
        $forbidden = ['doctors', 'patients', 'appointments', 'pages', 'messages', 'staff', 'roles', 'audit-logs'];

        foreach (Route::getRoutes()->getRoutes() as $route) {
            $name = (string) $route->getName();

            foreach ($forbidden as $entity) {
                $this->assertNotSame("admin.{$entity}.destroy", $name, "{$name} tidak boleh ada (00 C, BR-03).");
            }

            if (str_starts_with($route->uri(), 'admin/dokter')) {
                $this->assertNotContains('DELETE', $route->methods(), "Dokter tidak boleh punya route DELETE ({$route->uri()}).");
            }
        }

        $this->assertNotNull(Route::getRoutes()->getByName('admin.sessions.destroy'), 'admin.sessions.destroy ada (00 I.4, F3-R20).');
    }

    public function test_uris_use_indonesian_segments(): void
    {
        $this->assertSame('/admin/jadwal', route('admin.schedules.index', [], false));
        $this->assertSame('/admin/jadwal/buat', route('admin.schedules.create', [], false));
        $this->assertSame('/admin/jadwal/7/ubah', route('admin.schedules.edit', 7, false));
        $this->assertSame('/admin/jadwal/sesi', route('admin.sessions.index', [], false));
        $this->assertSame('/admin/jadwal/sesi/buat', route('admin.sessions.create', [], false));
        $this->assertSame('/admin/jadwal/sesi/7/ubah', route('admin.sessions.edit', 7, false));
        $this->assertSame('/admin/jadwal/sesi/7/status', route('admin.sessions.status', 7, false));
        $this->assertSame('/admin/jadwal/pengecualian', route('admin.exceptions.index', [], false));
        $this->assertSame('/admin/jadwal/pengecualian/buat', route('admin.exceptions.create', [], false));
        $this->assertSame('/admin/jadwal/pengecualian/7/ubah', route('admin.exceptions.edit', 7, false));
        $this->assertSame('/dokter', route('doctors.index', [], false));
        $this->assertSame('/dokter/raka-pradipta-wibowo', route('doctors.show', 'raka-pradipta-wibowo', false));
        $this->assertSame('/jadwal-dokter', route('schedules.index', [], false));
    }

    public function test_static_segments_are_matched_before_parameterised_routes(): void
    {
        $routes = Route::getRoutes();

        $this->assertSame('admin.schedules.create', $routes->match(Request::create('/admin/jadwal/buat', 'GET'))->getName());
        $this->assertSame('admin.sessions.index', $routes->match(Request::create('/admin/jadwal/sesi', 'GET'))->getName());
        $this->assertSame('admin.sessions.create', $routes->match(Request::create('/admin/jadwal/sesi/buat', 'GET'))->getName());
        $this->assertSame('admin.sessions.status', $routes->match(Request::create('/admin/jadwal/sesi/7/status', 'PATCH'))->getName());
        $this->assertSame('admin.sessions.destroy', $routes->match(Request::create('/admin/jadwal/sesi/7', 'DELETE'))->getName());
        $this->assertSame('admin.exceptions.index', $routes->match(Request::create('/admin/jadwal/pengecualian', 'GET'))->getName());
        $this->assertSame('admin.exceptions.create', $routes->match(Request::create('/admin/jadwal/pengecualian/buat', 'GET'))->getName());
        $this->assertSame('admin.exceptions.destroy', $routes->match(Request::create('/admin/jadwal/pengecualian/7', 'DELETE'))->getName());
        $this->assertSame('admin.schedules.destroy', $routes->match(Request::create('/admin/jadwal/7', 'DELETE'))->getName());
        $this->assertSame('doctors.index', $routes->match(Request::create('/dokter', 'GET'))->getName());
        $this->assertSame('doctors.show', $routes->match(Request::create('/dokter/dr-contoh', 'GET'))->getName());
    }

    public function test_the_schedule_module_tabs_follow_the_documented_configuration(): void
    {
        $tabs = config('navigation.modules.schedules');

        $this->assertSame(
            ['admin.schedules.index', 'admin.sessions.index', 'admin.exceptions.index', 'admin.sessions.review'],
            array_column($tabs, 'route'),
        );
        $this->assertSame(
            ['schedules.view', 'schedules.view', 'schedules.view', 'sessions.review'],
            array_column($tabs, 'permission'),
        );
    }
}
