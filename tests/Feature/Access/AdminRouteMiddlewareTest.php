<?php

namespace Tests\Feature\Access;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * KNS-02: setiap route `admin.*` (selain login/logout/ganti password) wajib dipagari `auth`, `account:staff`,
 * `active`, dan `permission:*`. Tes ini otomatis mencakup route yang ditambahkan di fase berikutnya.
 */
class AdminRouteMiddlewareTest extends TestCase
{
    /** Route yang sengaja berada di luar G-ADMIN penuh. */
    private const EXEMPT = ['admin.login', 'admin.login.store', 'admin.logout', 'admin.password.edit', 'admin.password.update'];

    private const REQUIRED = ['auth', 'account:staff', 'active'];

    /**
     * @return list<string>
     */
    private function violations(): array
    {
        $violations = [];

        foreach (Route::getRoutes()->getRoutes() as $route) {
            $name = (string) $route->getName();

            if (! str_starts_with($name, 'admin.') || in_array($name, self::EXEMPT, true)) {
                continue;
            }

            $middleware = array_filter($route->gatherMiddleware(), 'is_string');

            foreach (self::REQUIRED as $required) {
                if (! in_array($required, $middleware, true)) {
                    $violations[] = "{$name}: tanpa middleware {$required}";
                }
            }

            if (collect($middleware)->doesntContain(fn (string $item) => str_starts_with($item, 'permission:'))) {
                $violations[] = "{$name}: tanpa middleware permission:*";
            }
        }

        return $violations;
    }

    public function test_every_admin_route_has_the_account_active_and_permission_middleware(): void
    {
        $this->assertSame([], $this->violations());
    }

    public function test_the_guard_detects_an_unprotected_admin_route(): void
    {
        Route::middleware('web')->get('/admin/_uji-bocor', fn () => 'bocor')->name('admin.uji.bocor');
        Route::middleware(['web', 'auth', 'account:staff', 'active'])->get('/admin/_uji-tanpa-izin', fn () => 'x')->name('admin.uji.tanpa-izin');

        $violations = $this->violations();

        $this->assertContains('admin.uji.bocor: tanpa middleware auth', $violations);
        $this->assertContains('admin.uji.bocor: tanpa middleware permission:*', $violations);
        $this->assertContains('admin.uji.tanpa-izin: tanpa middleware permission:*', $violations);
        $this->assertNotContains('admin.uji.tanpa-izin: tanpa middleware auth', $violations);
    }

    public function test_login_and_logout_routes_are_guarded_as_documented(): void
    {
        $routes = Route::getRoutes();

        $login = $routes->getByName('admin.login');
        $store = $routes->getByName('admin.login.store');
        $logout = $routes->getByName('admin.logout');

        $this->assertContains('guest', $login->gatherMiddleware());
        $this->assertContains('guest', $store->gatherMiddleware());
        $this->assertContains('throttle:login', $store->gatherMiddleware());
        $this->assertContains('auth', $logout->gatherMiddleware());
        $this->assertContains('account:staff', $logout->gatherMiddleware());
    }

    public function test_every_admin_route_lives_under_the_admin_url_prefix(): void
    {
        foreach (Route::getRoutes()->getRoutes() as $route) {
            if (str_starts_with((string) $route->getName(), 'admin.')) {
                $this->assertTrue(
                    $route->uri() === 'admin' || str_starts_with($route->uri(), 'admin/'),
                    "Route {$route->getName()} berada di luar /admin ({$route->uri()})."
                );
            }
        }
    }
}
