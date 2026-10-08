<?php

namespace Tests\Feature\Access;

use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

/**
 * KNS-01 (bagian fase 5), KNS-02 (route `patient.*` berpagar), dan KNS-03 (izin tetap 19): 13 route baru persis
 * kerangka C.1 (nama, URI, method, middleware, action) dan route admin tetap 106. Angka global (total route, jumlah di
 * bawah `/pasien`, ketiadaan route `patient.appointments.*`) tidak lagi dikunci di sini sejak fase 6 (F6-R4);
 * `Phase6RoutePermissionTest` mengunci angka akhir.
 */
class Phase5RoutePermissionTest extends TestCase
{
    use RefreshDatabase;

    private const AUTH_NAMESPACE = 'App\\Http\\Controllers\\Auth\\';

    private const PATIENT_NAMESPACE = 'App\\Http\\Controllers\\Patient\\';

    /** @return array<string, array{string, string, list<string>, list<string>, string}> */
    public static function routes(): array
    {
        $guest = ['web', 'guest'];
        $portal = ['web', 'auth', 'account:patient', 'active'];
        $get = ['GET', 'HEAD'];

        $rows = [
            'login' => ['masuk', $get, $guest, self::AUTH_NAMESPACE.'LoginController@create'],
            'login.store' => ['masuk', ['POST'], ['web', 'guest', 'throttle:login'], self::AUTH_NAMESPACE.'LoginController@store'],
            'logout' => ['keluar', ['POST'], ['web', 'auth'], self::AUTH_NAMESPACE.'LoginController@destroy'],
            'register' => ['daftar', $get, $guest, self::AUTH_NAMESPACE.'RegisterController@create'],
            'register.store' => ['daftar', ['POST'], ['web', 'guest', 'throttle:register'], self::AUTH_NAMESPACE.'RegisterController@store'],
            'password.request' => ['lupa-password', $get, $guest, self::AUTH_NAMESPACE.'PasswordResetLinkController@create'],
            'password.email' => ['lupa-password', ['POST'], ['web', 'guest', 'throttle:password-reset'], self::AUTH_NAMESPACE.'PasswordResetLinkController@store'],
            'password.reset' => ['reset-password/{token}', $get, $guest, self::AUTH_NAMESPACE.'NewPasswordController@create'],
            'password.update' => ['reset-password', ['POST'], ['web', 'guest', 'throttle:password-reset'], self::AUTH_NAMESPACE.'NewPasswordController@store'],
            'patient.dashboard' => ['pasien', $get, $portal, self::PATIENT_NAMESPACE.'DashboardController@index'],
            'patient.profile.edit' => ['pasien/profil', $get, $portal, self::PATIENT_NAMESPACE.'ProfileController@edit'],
            'patient.profile.update' => ['pasien/profil', ['PUT'], $portal, self::PATIENT_NAMESPACE.'ProfileController@update'],
            'patient.password.update' => ['pasien/password', ['PUT'], $portal, self::PATIENT_NAMESPACE.'PasswordController@update'],
        ];

        $cases = [];

        foreach ($rows as $name => [$uri, $methods, $middleware, $action]) {
            $cases[$name] = [$name, $uri, $methods, $middleware, $action];
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

    public function test_the_phase_5_route_names_are_the_thirteen_documented_ones_and_are_not_duplicated(): void
    {
        $this->assertCount(13, self::routes());

        foreach (array_keys(self::routes()) as $name) {
            $count = collect(Route::getRoutes()->getRoutes())->filter(fn ($route): bool => $route->getName() === $name)->count();

            $this->assertSame(1, $count, "Nama route {$name} harus tepat satu.");
        }
    }

    /** F6-R4: keempat route portal fase 5 tetap berada di bawah `/pasien` (jumlah total dikunci Phase6RoutePermissionTest). */
    public function test_the_four_phase_5_portal_routes_are_still_under_pasien(): void
    {
        $found = [];

        foreach (Route::getRoutes()->getRoutes() as $route) {
            if ($route->uri() === 'pasien' || str_starts_with($route->uri(), 'pasien/')) {
                $found[] = (string) $route->getName();
            }
        }

        foreach (['patient.dashboard', 'patient.password.update', 'patient.profile.edit', 'patient.profile.update'] as $name) {
            $this->assertContains($name, $found, $name);
        }
    }

    public function test_there_is_no_wildcard_route_under_pasien(): void
    {
        foreach (['/pasien/riwayat', '/pasien/apa-saja', '/pasien/pendaftaran/tidak-ada', '/pasien/notifikasi'] as $uri) {
            foreach (['GET', 'POST'] as $method) {
                try {
                    Route::getRoutes()->match(Request::create($uri, $method));

                    $this->fail("{$method} {$uri} tidak boleh cocok dengan route mana pun.");
                } catch (NotFoundHttpException) {
                    $this->addToAssertionCount(1);
                }
            }
        }
    }

    public function test_the_admin_area_still_has_one_hundred_and_six_routes_after_phase_5(): void
    {
        $count = 0;

        foreach (Route::getRoutes()->getRoutes() as $route) {
            if ($route->uri() === 'admin' || str_starts_with($route->uri(), 'admin/')) {
                $count++;
            }
        }

        // Fase 5 tidak menambah route admin (admin pasien = fase 7, pengaturan = fase 8).
        $this->assertSame(106, $count);
    }

    public function test_uris_use_indonesian_segments(): void
    {
        $this->assertSame('/masuk', route('login', [], false));
        $this->assertSame('/masuk', route('login.store', [], false));
        $this->assertSame('/keluar', route('logout', [], false));
        $this->assertSame('/daftar', route('register', [], false));
        $this->assertSame('/daftar', route('register.store', [], false));
        $this->assertSame('/lupa-password', route('password.request', [], false));
        $this->assertSame('/lupa-password', route('password.email', [], false));
        $this->assertSame('/reset-password/abc123', route('password.reset', 'abc123', false));
        $this->assertSame('/reset-password', route('password.update', [], false));
        $this->assertSame('/pasien', route('patient.dashboard', [], false));
        $this->assertSame('/pasien/profil', route('patient.profile.edit', [], false));
        $this->assertSame('/pasien/profil', route('patient.profile.update', [], false));
        $this->assertSame('/pasien/password', route('patient.password.update', [], false));
    }

    /** Nama `password.reset` dan `password.request` wajib persis: `ResetPassword` memanggil `route('password.reset', …)`. */
    public function test_the_password_reset_link_matches_what_laravel_generates(): void
    {
        $route = Route::getRoutes()->getByName('password.reset');

        $this->assertSame(['token'], $route->parameterNames());
        $this->assertSame(
            url('/reset-password/abc123?email=siti%40contoh.test'),
            route('password.reset', ['token' => 'abc123', 'email' => 'siti@contoh.test']),
        );
    }

    public function test_only_the_four_documented_post_routes_are_throttled(): void
    {
        $expected = [
            'login.store' => 'throttle:login',
            'register.store' => 'throttle:register',
            'password.email' => 'throttle:password-reset',
            'password.update' => 'throttle:password-reset',
        ];

        foreach (array_keys(self::routes()) as $name) {
            $throttles = array_values(array_filter(
                Route::getRoutes()->getByName($name)->gatherMiddleware(),
                fn ($item): bool => is_string($item) && str_starts_with($item, 'throttle:'),
            ));

            $this->assertSame(isset($expected[$name]) ? [$expected[$name]] : [], $throttles, $name);
        }
    }

    /** Route tamu memakai `guest` saja; `logout` hanya `auth` (F5-R3, F5-R8). */
    public function test_guest_routes_never_carry_auth_account_or_permission_middleware(): void
    {
        foreach (['login', 'login.store', 'register', 'register.store', 'password.request', 'password.email', 'password.reset', 'password.update'] as $name) {
            $middleware = array_filter(Route::getRoutes()->getByName($name)->gatherMiddleware(), 'is_string');

            $this->assertContains('guest', $middleware, $name);

            foreach ($middleware as $item) {
                $this->assertStringNotContainsString('permission:', $item, "{$name}: {$item}");
                $this->assertStringNotContainsString('account:', $item, "{$name}: {$item}");
                $this->assertNotSame('auth', $item, "{$name}: {$item}");
            }
        }

        $logout = array_filter(Route::getRoutes()->getByName('logout')->gatherMiddleware(), 'is_string');

        $this->assertContains('auth', $logout);
        $this->assertNotContains('guest', $logout);
        $this->assertNotContains('account:patient', $logout, 'Staf yang memakai /keluar ikut keluar (F5-R8).');
    }

    /** KNS-02 (sisi pasien): setiap route `patient.*` berpagar `auth`, `account:patient`, dan `active`, tanpa izin Spatie. */
    public function test_every_patient_route_is_guarded_by_auth_account_and_active_without_permissions(): void
    {
        $checked = 0;

        foreach (Route::getRoutes()->getRoutes() as $route) {
            if (! str_starts_with((string) $route->getName(), 'patient.')) {
                continue;
            }

            $middleware = array_filter($route->gatherMiddleware(), 'is_string');

            foreach (['auth', 'account:patient', 'active'] as $required) {
                $this->assertContains($required, $middleware, "{$route->getName()}: tanpa middleware {$required}");
            }

            foreach ($middleware as $item) {
                $this->assertStringNotContainsString('permission:', $item, "{$route->getName()}: pasien tidak memegang izin.");
            }

            $checked++;
        }

        $this->assertGreaterThanOrEqual(4, $checked, 'Sedikitnya keempat route portal fase 5 harus ikut diperiksa.');
    }

    /** F5-R3: grup pasien dimuat sebelum grup admin di `bootstrap/app.php` dan komentar penanda fase 5 sudah diganti. */
    public function test_the_patient_group_is_loaded_before_the_admin_group_in_bootstrap(): void
    {
        $source = (string) file_get_contents(base_path('bootstrap/app.php'));

        $this->assertStringContainsString("->prefix('pasien')", $source);
        $this->assertStringContainsString("->name('patient.')", $source);
        $this->assertStringContainsString("base_path('routes/patient.php')", $source);
        $this->assertLessThan(strpos($source, "base_path('routes/admin.php')"), strpos($source, "base_path('routes/patient.php')"));
        $this->assertStringNotContainsString('ditambahkan pada fase 5', $source);
        $this->assertStringNotContainsString('Cadangan F1-R2', $source);
    }

    /** KNS-03: fase 5 tidak menambah izin dan pasien tidak punya peran. */
    public function test_phase_5_adds_no_permission_to_the_catalogue(): void
    {
        $this->assertCount(19, RolePermissionSeeder::PERMISSIONS);
    }

    public function test_get_on_post_only_or_put_only_phase_5_urls_answers_405(): void
    {
        $this->get('/keluar')->assertStatus(405);
        $this->get('/pasien/password')->assertStatus(405);
        $this->get('/reset-password')->assertStatus(405);
    }
}
