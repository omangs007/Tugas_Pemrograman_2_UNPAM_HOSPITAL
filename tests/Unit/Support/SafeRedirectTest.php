<?php

namespace Tests\Unit\Support;

use App\Support\SafeRedirect;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SafeRedirectTest extends TestCase
{
    /** @return array<string, array{?string, ?string}> */
    public static function internalPaths(): array
    {
        return [
            'path sederhana' => ['/admin/dokter', '/admin/dokter'],
            'dengan query' => ['/admin/x?y=1', '/admin/x?y=1'],
            'akar situs' => ['/', '/'],
            'dengan fragmen' => ['/admin/dokter?page=2#tabel', '/admin/dokter?page=2#tabel'],
            'dengan persen-encoding' => ['/admin/dokter?q=dr%20andi', '/admin/dokter?q=dr%20andi'],
            'dengan titik dua di query' => ['/admin?next=/a:b', '/admin?next=/a:b'],
            'tepat 2000 karakter' => ['/'.str_repeat('a', 1999), '/'.str_repeat('a', 1999)],
            'null' => [null, null],
            'kosong' => ['', null],
            'url absolut https' => ['https://situs-lain.test', null],
            'url absolut http' => ['http://situs-lain.test/admin', null],
            'protokol relatif' => ['//evil.test', null],
            'tiga garis miring' => ['///evil.test', null],
            'backslash setelah garis miring' => ['/\\evil.test', null],
            'backslash di tengah' => ['/admin\\x', null],
            'skema javascript' => ['javascript:alert(1)', null],
            'skema data' => ['data:text/html,x', null],
            'tanpa garis miring awal' => ['admin/dokter', null],
            'spasi di depan' => [' /admin', null],
            'tab menyamarkan protokol relatif' => ["/\t/evil.test", null],
            'newline (header injection)' => ["/admin\nSet-Cookie: a=b", null],
            'carriage return' => ["/admin\rx", null],
            'null byte' => ["/admin\0x", null],
            'karakter DEL' => ["/admin\x7Fx", null],
            'lebih dari 2000 karakter' => ['/'.str_repeat('a', 2000), null],
        ];
    }

    #[DataProvider('internalPaths')]
    public function test_internal_path_only_accepts_safe_local_paths(?string $input, ?string $expected): void
    {
        $this->assertSame($expected, SafeRedirect::internalPath($input));
    }

    private function registerRoute(string $name, string $uri): void
    {
        Route::get($uri, fn () => 'ok')->name($name);
        $this->app['router']->getRoutes()->refreshNameLookups();
    }

    private function requestOnRoute(string $routeName, array $input = []): Request
    {
        $route = (new RoutingRoute(['POST'], '/uji', fn () => 'ok'))->name($routeName);
        $request = Request::create('/uji', 'POST', $input);
        $request->setRouteResolver(fn () => $route);
        $this->app->instance('request', $request);

        return $request;
    }

    public function test_back_returns_the_previous_url_when_there_is_one(): void
    {
        $this->registerRoute('home', '/_uji/beranda');

        $request = Request::create('/admin/dokter/1', 'POST');
        $request->headers->set('referer', 'http://localhost/admin/dokter?page=2');
        $this->app->instance('request', $request);

        $this->assertSame('http://localhost/admin/dokter?page=2', SafeRedirect::back($request));
    }

    public function test_back_falls_back_to_home_without_a_previous_url(): void
    {
        $this->registerRoute('home', '/_uji/beranda');

        $request = Request::create('/admin/dokter/1', 'POST');
        $this->app->instance('request', $request);

        $this->assertSame(route('home'), SafeRedirect::back($request));
    }

    /** @return array<string, array{string}> */
    public static function wizardRoutes(): array
    {
        return [
            'tinjau' => ['patient.appointments.review'],
            'simpan' => ['patient.appointments.store'],
        ];
    }

    #[DataProvider('wizardRoutes')]
    public function test_back_from_post_only_wizard_routes_returns_to_the_wizard_step(string $routeName): void
    {
        $this->registerRoute('patient.appointments.create', '/_uji/pasien/pendaftaran/baru');

        $request = $this->requestOnRoute($routeName, ['practice_session_id' => '7']);

        $this->assertSame(route('patient.appointments.create', ['sesi' => '7']), SafeRedirect::back($request));
    }

    public function test_back_from_wizard_without_session_id_omits_the_parameter(): void
    {
        $this->registerRoute('patient.appointments.create', '/_uji/pasien/pendaftaran/baru');

        $request = $this->requestOnRoute('patient.appointments.store');

        $this->assertSame(route('patient.appointments.create'), SafeRedirect::back($request));
    }

    public function test_back_does_not_need_the_home_route_for_wizard_routes(): void
    {
        $this->registerRoute('patient.appointments.create', '/_uji/pasien/pendaftaran/baru');

        $request = $this->requestOnRoute('patient.appointments.review', ['practice_session_id' => '3']);

        $this->assertStringContainsString('sesi=3', SafeRedirect::back($request));
    }
}
