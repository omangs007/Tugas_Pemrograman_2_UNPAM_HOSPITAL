<?php

namespace Tests\Feature\Access;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * KNS-01 (bagian fase 2) dan pelengkap KNS-02: nama route, izin, URI, dan route yang sengaja tidak ada.
 */
class Phase2RoutePermissionTest extends TestCase
{
    private const MANAGE_DOCTORS = 'permission:doctors.manage';

    private const MANAGE_CLINICS = 'permission:clinics.manage';

    /** @return array<string, array{string, string}> */
    public static function permissions(): array
    {
        $rows = [
            'admin.doctors.index' => 'permission:doctors.view',
            'admin.doctors.show' => 'permission:doctors.view',
            'admin.doctors.create' => self::MANAGE_DOCTORS,
            'admin.doctors.store' => self::MANAGE_DOCTORS,
            'admin.doctors.edit' => self::MANAGE_DOCTORS,
            'admin.doctors.update' => self::MANAGE_DOCTORS,
            'admin.doctors.status' => self::MANAGE_DOCTORS,
            'admin.doctors.archive' => self::MANAGE_DOCTORS,
            'admin.doctors.photo' => self::MANAGE_DOCTORS,
            'admin.media.editor-upload' => 'permission:content.manage|clinics.manage|facilities.manage',
            'admin.media.reorder' => 'permission:doctors.manage|clinics.manage|facilities.manage|content.manage',
            'admin.media.update' => 'permission:doctors.manage|clinics.manage|facilities.manage|content.manage|settings.manage',
            'admin.media.destroy' => 'permission:doctors.manage|clinics.manage|facilities.manage|content.manage|settings.manage',
        ];

        foreach (['specialties', 'clinics'] as $resource) {
            foreach (['index', 'create', 'store', 'edit', 'update', 'destroy'] as $action) {
                $rows["admin.{$resource}.{$action}"] = self::MANAGE_CLINICS;
            }
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
        $this->assertContains($permission, array_filter($route->gatherMiddleware(), 'is_string'), "Route {$name} harus memakai {$permission}.");
    }

    public function test_routes_that_must_not_exist_are_absent(): void
    {
        foreach (['admin.doctors.destroy', 'admin.specialties.show', 'admin.clinics.show'] as $name) {
            $this->assertNull(Route::getRoutes()->getByName($name), "{$name} tidak boleh ada (00 I.6, BR-03).");
        }

        foreach (Route::getRoutes()->getRoutes() as $route) {
            if (str_starts_with($route->uri(), 'admin/dokter')) {
                $this->assertNotContains('DELETE', $route->methods(), "Dokter tidak boleh punya route DELETE ({$route->uri()}).");
            }
        }
    }

    public function test_the_doctor_resource_has_an_admin_show_screen(): void
    {
        $this->assertNotNull(Route::getRoutes()->getByName('admin.doctors.show'));
    }

    public function test_uris_use_indonesian_segments(): void
    {
        $this->assertSame('/admin/dokter/buat', route('admin.doctors.create', [], false));
        $this->assertSame('/admin/dokter/5/ubah', route('admin.doctors.edit', 5, false));
        $this->assertSame('/admin/dokter/5/status', route('admin.doctors.status', 5, false));
        $this->assertSame('/admin/dokter/5/arsip', route('admin.doctors.archive', 5, false));
        $this->assertSame('/admin/dokter/5/foto', route('admin.doctors.photo', 5, false));
        $this->assertSame('/admin/spesialisasi', route('admin.specialties.index', [], false));
        $this->assertSame('/admin/poliklinik/3/ubah', route('admin.clinics.edit', 3, false));
        $this->assertSame('/admin/media/editor', route('admin.media.editor-upload', [], false));
        $this->assertSame('/admin/media/urutkan', route('admin.media.reorder', [], false));
        $this->assertSame('/layanan', route('clinics.index', [], false));
        $this->assertSame('/layanan/poliklinik-anak', route('clinics.show', 'poliklinik-anak', false));
    }

    public function test_static_segments_are_matched_before_parameterised_routes(): void
    {
        $routes = Route::getRoutes();

        $this->assertSame('admin.doctors.create', $routes->match(Request::create('/admin/dokter/buat', 'GET'))->getName());
        $this->assertSame('admin.media.reorder', $routes->match(Request::create('/admin/media/urutkan', 'POST'))->getName());
        $this->assertSame('admin.media.editor-upload', $routes->match(Request::create('/admin/media/editor', 'POST'))->getName());
    }
}
