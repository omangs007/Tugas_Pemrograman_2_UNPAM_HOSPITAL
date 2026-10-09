<?php

namespace Tests\Feature\Access;

use App\Models\User;
use App\Services\Booking\BookingCodeGenerator;
use App\Support\NavigationMenu;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

/**
 * KNS-01 (bagian fase 7a), KNS-02 (route `admin.*` berpagar), dan KNS-03 (izin tetap 19): 16 route baru persis kerangka C.1
 * (nama, URI, method, middleware berurutan, action), pola kode booking dan binding `booking_code` pada tiga route
 * `{appointment}`, serta angka AKHIR fase 7a: 166 route aplikasi (150 + 16), 122 di `/admin` (106 + 16), dan 13 di `/pasien`.
 * Angka global tidak lagi dikunci di `Phase4b`/`Phase5`/`Phase6RoutePermissionTest` sejak T5 (F7A-R4). Fase 7b dan 8 memperbarui
 * daftar "route yang belum ada" di bawah.
 */
class Phase7aRoutePermissionTest extends TestCase
{
    use InteractsWithStaff;
    use RefreshDatabase;

    private const ADMIN_NAMESPACE = 'App\\Http\\Controllers\\Admin\\';

    private const CODE_PATTERN = 'UPH-[0-9A-HJKMNP-TV-Z]{4}-[0-9A-HJKMNP-TV-Z]{4}';

    private const SAMPLE_CODE = 'UPH-ABCD-EFGH';

    /** @return array<string, array{string, string, list<string>, list<string>, string}> */
    public static function routes(): array
    {
        $guard = ['web', 'auth', 'account:staff', 'active', 'password.changed'];
        $get = ['GET', 'HEAD'];
        $patients = self::ADMIN_NAMESPACE.'PatientController@';
        $appointments = self::ADMIN_NAMESPACE.'AppointmentController@';
        $queue = self::ADMIN_NAMESPACE.'QueueController@';

        $rows = [
            'admin.patients.index' => ['admin/pasien', $get, 'patients.view', $patients.'index'],
            'admin.patients.create' => ['admin/pasien/buat', $get, 'patients.manage', $patients.'create'],
            'admin.patients.store' => ['admin/pasien', ['POST'], 'patients.manage', $patients.'store'],
            'admin.patients.show' => ['admin/pasien/{patient}', $get, 'patients.view', $patients.'show'],
            'admin.patients.edit' => ['admin/pasien/{patient}/ubah', $get, 'patients.manage', $patients.'edit'],
            'admin.patients.update' => ['admin/pasien/{patient}', ['PUT', 'PATCH'], 'patients.manage', $patients.'update'],
            'admin.patients.status' => ['admin/pasien/{patient}/status', ['PATCH'], 'patients.manage', $patients.'status'],
            'admin.patients.link-account' => ['admin/pasien/{patient}/hubungkan-akun', ['POST'], 'patients.manage', $patients.'linkAccount'],
            'admin.appointments.index' => ['admin/pendaftaran', $get, 'appointments.view', $appointments.'index'],
            'admin.appointments.create' => ['admin/pendaftaran/buat', $get, 'appointments.manage', $appointments.'create'],
            'admin.appointments.store' => ['admin/pendaftaran', ['POST'], 'appointments.manage', $appointments.'store'],
            'admin.appointments.show' => ['admin/pendaftaran/{appointment}', $get, 'appointments.view', $appointments.'show'],
            'admin.appointments.transition' => ['admin/pendaftaran/{appointment}/status', ['POST'], 'appointments.manage', $appointments.'transition'],
            'admin.appointments.proof' => ['admin/pendaftaran/{appointment}/bukti', $get, 'appointments.view', $appointments.'proof'],
            'admin.queue.index' => ['admin/antrean', $get, 'appointments.view', $queue.'index'],
            'admin.queue.mark-no-show' => ['admin/antrean/{session}/tidak-hadir', ['POST'], 'appointments.manage', $queue.'markNoShow'],
        ];

        $cases = [];

        foreach ($rows as $name => [$uri, $methods, $permission, $action]) {
            $cases[$name] = [$name, $uri, $methods, [...$guard, 'permission:'.$permission], $action];
        }

        return $cases;
    }

    /**
     * @param  list<string>  $methods
     * @param  list<string>  $middleware
     */
    #[DataProvider('routes')]
    public function test_route_exists_with_the_documented_uri_methods_middleware_and_action(string $name, string $uri, array $methods, array $middleware, string $action): void
    {
        $route = Route::getRoutes()->getByName($name);

        $this->assertNotNull($route, "Route {$name} belum terdaftar.");
        $this->assertSame($uri, $route->uri(), $name);
        $this->assertSame($methods, $route->methods(), $name);
        $this->assertSame($middleware, array_values(array_filter($route->gatherMiddleware(), 'is_string')), "Middleware {$name} harus persis dan berurutan.");
        $this->assertSame($action, $route->getActionName(), $name);
    }

    /**
     * @param  list<string>  $methods
     * @param  list<string>  $middleware
     */
    #[DataProvider('routes')]
    public function test_the_action_points_to_an_existing_controller_method(string $name, string $uri, array $methods, array $middleware, string $action): void
    {
        [$class, $method] = explode('@', $action);

        $this->assertTrue(class_exists($class), "{$class} belum ada.");
        $this->assertTrue(method_exists($class, $method), "{$class}::{$method} belum ada.");
    }

    public function test_the_phase_7a_route_names_are_the_sixteen_documented_ones_and_nothing_else_under_the_three_prefixes(): void
    {
        $expected = array_keys(self::routes());

        $this->assertCount(16, $expected);

        $found = [];

        foreach (Route::getRoutes()->getRoutes() as $route) {
            $name = (string) $route->getName();

            if (preg_match('/^admin\.(patients|appointments|queue)\./', $name) === 1) {
                $found[] = $name;
            }
        }

        sort($expected);
        sort($found);

        $this->assertSame($expected, $found, 'Nama route 7a harus tepat 16 dan tidak ganda.');
    }

    public function test_the_admin_area_has_one_hundred_and_twenty_two_routes_after_phase_7a(): void
    {
        $count = 0;

        foreach (Route::getRoutes()->getRoutes() as $route) {
            if ($route->uri() === 'admin' || str_starts_with($route->uri(), 'admin/')) {
                $count++;
            }
        }

        // 31 fase 1-2 + 19 fase 3 + 40 fase 4a + 16 fase 4b + 16 fase 7a = 122 (106 + 16). Fase 7b dan 8 memperbarui angka ini.
        $this->assertSame(122, $count);
    }

    /**
     * Setara `route:list --except-vendor`: route milik aplikasi (aksi `App\…`). Tiga route vendor (`storage.local`,
     * `storage.local.upload`, `up`) berupa Closure dan tidak dihitung. 150 sebelum fase 7a + 16 = 166.
     */
    public function test_the_application_has_one_hundred_and_sixty_six_routes_after_phase_7a(): void
    {
        $count = 0;

        foreach (Route::getRoutes()->getRoutes() as $route) {
            if (str_starts_with($route->getActionName(), 'App\\')) {
                $count++;
            }
        }

        $this->assertSame(166, $count);
    }

    public function test_there_are_still_exactly_thirteen_routes_under_pasien(): void
    {
        $found = [];

        foreach (Route::getRoutes()->getRoutes() as $route) {
            if ($route->uri() === 'pasien' || str_starts_with($route->uri(), 'pasien/')) {
                $found[] = (string) $route->getName();
            }
        }

        $expected = [
            'patient.dashboard', 'patient.profile.edit', 'patient.profile.update', 'patient.password.update',
            'patient.appointments.create', 'patient.appointments.review', 'patient.appointments.store', 'patient.appointments.show',
            'patient.appointments.index', 'patient.appointments.cancel', 'patient.appointments.proof', 'patient.appointments.proof-pdf',
            'patient.notifications.read',
        ];

        sort($found);
        sort($expected);

        $this->assertCount(13, $found);
        $this->assertSame($expected, $found);
    }

    /** KNS-02: tiap route 7a memuat tepat satu `permission:*` dan tidak memakai `role:`, `role_or_permission:`, atau `can:`. */
    public function test_every_phase_7a_route_has_exactly_one_permission_and_no_role_or_gate_middleware(): void
    {
        foreach (array_keys(self::routes()) as $name) {
            $middleware = array_values(array_filter(Route::getRoutes()->getByName($name)->gatherMiddleware(), 'is_string'));

            $this->assertCount(1, array_filter($middleware, fn (string $item): bool => str_starts_with($item, 'permission:')), "{$name}: harus tepat satu permission:*.");

            foreach ($middleware as $item) {
                $this->assertFalse(
                    str_starts_with($item, 'role:') || str_starts_with($item, 'role_or_permission:') || str_starts_with($item, 'can:'),
                    "{$name}: otorisasi lewat izin Spatie dan Policy, bukan {$item}.",
                );
            }
        }
    }

    public function test_the_appointment_routes_use_the_booking_code_pattern_and_bind_by_booking_code(): void
    {
        foreach (['show', 'transition', 'proof'] as $suffix) {
            $route = Route::getRoutes()->getByName('admin.appointments.'.$suffix);

            $this->assertSame(self::CODE_PATTERN, $route->wheres['appointment'] ?? null, $suffix);
            $this->assertSame(['appointment' => 'booking_code'], $route->bindingFields(), $suffix);
        }

        foreach (['index', 'create', 'store'] as $suffix) {
            $route = Route::getRoutes()->getByName('admin.appointments.'.$suffix);

            $this->assertSame([], $route->bindingFields(), $suffix);
            $this->assertArrayNotHasKey('appointment', $route->wheres, $suffix);
        }

        // `appointment` tidak lagi dipaksa numerik oleh grup admin (F7A-R3); `patient` dan `session` tetap numerik.
        foreach (['admin.patients.show', 'admin.patients.update', 'admin.queue.mark-no-show', 'admin.doctors.show'] as $name) {
            $this->assertArrayNotHasKey('appointment', Route::getRoutes()->getByName($name)->wheres, $name);
        }

        foreach (['admin.patients.show', 'admin.patients.edit', 'admin.patients.update', 'admin.patients.status', 'admin.patients.link-account'] as $name) {
            $route = Route::getRoutes()->getByName($name);

            $this->assertSame('[0-9]+', $route->wheres['patient'] ?? null, $name);
            $this->assertSame([], $route->bindingFields(), $name);
        }

        $mark = Route::getRoutes()->getByName('admin.queue.mark-no-show');

        $this->assertSame('[0-9]+', $mark->wheres['session'] ?? null);
        $this->assertSame([], $mark->bindingFields());

        $this->assertMatchesRegularExpression('/^'.self::CODE_PATTERN.'$/', BookingCodeGenerator::generate());
    }

    /** `buat` didaftarkan sebelum parameter: `/admin/pasien/buat` dan `/admin/pendaftaran/buat` tidak tertangkap `{patient}`/`{appointment}`. */
    public function test_static_routes_are_registered_before_the_wildcard_routes(): void
    {
        $order = [];

        foreach (Route::getRoutes()->getRoutes() as $index => $route) {
            $order[(string) $route->getName()] = $index;
        }

        $this->assertLessThan($order['admin.patients.show'], $order['admin.patients.create']);
        $this->assertLessThan($order['admin.appointments.show'], $order['admin.appointments.create']);
    }

    public function test_urls_resolve_to_the_documented_routes_and_wrong_formats_do_not_match(): void
    {
        $code = self::SAMPLE_CODE;

        $matches = [
            ['/admin/pasien', 'GET', 'admin.patients.index'],
            ['/admin/pasien/buat', 'GET', 'admin.patients.create'],
            ['/admin/pasien', 'POST', 'admin.patients.store'],
            ['/admin/pasien/12', 'GET', 'admin.patients.show'],
            ['/admin/pasien/12/ubah', 'GET', 'admin.patients.edit'],
            ['/admin/pasien/12', 'PUT', 'admin.patients.update'],
            ['/admin/pasien/12', 'PATCH', 'admin.patients.update'],
            ['/admin/pasien/12/status', 'PATCH', 'admin.patients.status'],
            ['/admin/pasien/12/hubungkan-akun', 'POST', 'admin.patients.link-account'],
            ['/admin/pendaftaran', 'GET', 'admin.appointments.index'],
            ['/admin/pendaftaran/buat', 'GET', 'admin.appointments.create'],
            ['/admin/pendaftaran', 'POST', 'admin.appointments.store'],
            ["/admin/pendaftaran/{$code}", 'GET', 'admin.appointments.show'],
            ["/admin/pendaftaran/{$code}/status", 'POST', 'admin.appointments.transition'],
            ["/admin/pendaftaran/{$code}/bukti", 'GET', 'admin.appointments.proof'],
            ['/admin/antrean', 'GET', 'admin.queue.index'],
            ['/admin/antrean/7/tidak-hadir', 'POST', 'admin.queue.mark-no-show'],
        ];

        foreach ($matches as [$uri, $method, $name]) {
            $this->assertSame($name, Route::getRoutes()->match(Request::create($uri, $method))->getName(), "{$method} {$uri}");
        }

        $misses = [
            ['/admin/pasien/abc', 'GET'],
            ['/admin/pasien/abc/ubah', 'GET'],
            ['/admin/pasien/12/hapus', 'POST'],
            ['/admin/pendaftaran/UPH-ABCD-EFGI', 'GET'],
            ['/admin/pendaftaran/uph-abcd-efgh', 'GET'],
            ['/admin/pendaftaran/12', 'GET'],
            ["/admin/pendaftaran/{$code}/hapus", 'POST'],
            ["/admin/pendaftaran/{$code}/bukti.pdf", 'GET'],
            ['/admin/antrean/abc/tidak-hadir', 'POST'],
        ];

        foreach ($misses as [$uri, $method]) {
            try {
                Route::getRoutes()->match(Request::create($uri, $method));

                $this->fail("{$method} {$uri} tidak boleh cocok dengan route mana pun.");
            } catch (NotFoundHttpException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_wrong_methods_answer_405_and_there_is_no_destroy(): void
    {
        $code = self::SAMPLE_CODE;

        $wrongMethod = [
            ['/admin/pasien/12', 'POST'],
            ['/admin/pasien/12', 'DELETE'],
            ['/admin/pasien/buat', 'PUT'],
            ['/admin/pasien/12/status', 'POST'],
            ['/admin/pasien/12/hubungkan-akun', 'GET'],
            ['/admin/pendaftaran/buat', 'POST'],
            ["/admin/pendaftaran/{$code}", 'POST'],
            ["/admin/pendaftaran/{$code}/status", 'GET'],
            ["/admin/pendaftaran/{$code}/bukti", 'POST'],
            ['/admin/antrean', 'POST'],
            ['/admin/antrean/7/tidak-hadir', 'GET'],
        ];

        foreach ($wrongMethod as [$uri, $method]) {
            try {
                Route::getRoutes()->match(Request::create($uri, $method));

                $this->fail("{$method} {$uri} tidak boleh cocok dengan route mana pun.");
            } catch (MethodNotAllowedHttpException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->get('/admin/antrean/7/tidak-hadir')->assertStatus(405);
    }

    public function test_uris_use_indonesian_segments(): void
    {
        $code = self::SAMPLE_CODE;

        $this->assertSame('/admin/pasien', route('admin.patients.index', [], false));
        $this->assertSame('/admin/pasien/buat', route('admin.patients.create', [], false));
        $this->assertSame('/admin/pasien', route('admin.patients.store', [], false));
        $this->assertSame('/admin/pasien/12', route('admin.patients.show', ['patient' => 12], false));
        $this->assertSame('/admin/pasien/12/ubah', route('admin.patients.edit', ['patient' => 12], false));
        $this->assertSame('/admin/pasien/12', route('admin.patients.update', ['patient' => 12], false));
        $this->assertSame('/admin/pasien/12/status', route('admin.patients.status', ['patient' => 12], false));
        $this->assertSame('/admin/pasien/12/hubungkan-akun', route('admin.patients.link-account', ['patient' => 12], false));
        $this->assertSame('/admin/pendaftaran', route('admin.appointments.index', [], false));
        $this->assertSame('/admin/pendaftaran/buat', route('admin.appointments.create', [], false));
        $this->assertSame('/admin/pendaftaran', route('admin.appointments.store', [], false));
        $this->assertSame("/admin/pendaftaran/{$code}", route('admin.appointments.show', ['appointment' => $code], false));
        $this->assertSame("/admin/pendaftaran/{$code}/status", route('admin.appointments.transition', ['appointment' => $code], false));
        $this->assertSame("/admin/pendaftaran/{$code}/bukti", route('admin.appointments.proof', ['appointment' => $code], false));
        $this->assertSame('/admin/antrean', route('admin.queue.index', [], false));
        $this->assertSame('/admin/antrean/7/tidak-hadir', route('admin.queue.mark-no-show', ['session' => 7], false));
    }

    /**
     * Tidak ada route di luar cakupan 7a: tanpa `destroy` pasien/pendaftaran (BP 15 KNS-01), tanpa `edit`/`update`
     * pendaftaran (status hanya lewat `transition`), tanpa PDF admin, tanpa ekspor CSV (fase 8), dan tanpa layar
     * Sesi Terdampak (7b). 7b dan fase 8 memperbarui daftar ini.
     */
    public function test_no_route_of_phase_7b_or_8_or_a_destructive_route_exists_yet(): void
    {
        $absent = [
            'admin.patients.destroy', 'admin.appointments.destroy', 'admin.appointments.edit', 'admin.appointments.update',
            'admin.appointments.proof-pdf', 'admin.queue.show', 'admin.queue.store',
            'admin.appointments.export', 'admin.sessions.review', 'admin.sessions.resolve',
        ];

        foreach ($absent as $name) {
            $this->assertFalse(Route::has($name), "{$name} belum boleh ada di fase 7a.");
        }
    }

    /** KNS-03: fase 7a tidak menambah izin. */
    public function test_phase_7a_adds_no_permission_to_the_catalogue(): void
    {
        $this->assertCount(19, RolePermissionSeeder::PERMISSIONS);
    }

    /** Menu "Pelayanan Pasien" (05 §3.5): Petugas dan Super Admin melihat tiga item 7a (dan Pesan Kontak); Admin Konten dan tamu tidak. */
    public function test_the_patient_care_sidebar_group_follows_the_permissions(): void
    {
        $full = ['Pendaftaran', 'Antrean Hari Ini', 'Pasien', 'Pesan Kontak'];

        $this->assertSame($full, $this->careLabels($this->createStaff('registration-officer')));
        $this->assertSame($full, $this->careLabels($this->createStaff('super-admin')));
        $this->assertNull($this->careLabels($this->createStaff('content-admin')));
        $this->assertNull($this->careLabels(null));
    }

    /** @return list<string>|null */
    private function careLabels(?User $user): ?array
    {
        $group = collect(NavigationMenu::items('admin', $user))->firstWhere('group', 'Pelayanan Pasien');

        return $group === null ? null : array_column($group['items'], 'label');
    }
}
