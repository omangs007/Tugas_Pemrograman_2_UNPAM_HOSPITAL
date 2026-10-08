<?php

namespace Tests\Feature\Access;

use App\Services\Booking\BookingCodeGenerator;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

/**
 * KNS-01 (bagian fase 6), KNS-02 (route `patient.*` berpagar), dan KNS-03 (izin tetap 19): 9 route baru persis kerangka
 * C.1 (nama, URI, method, middleware, action, pola `where`, binding `booking_code`), `throttle:booking` hanya pada
 * `store`, 13 route di bawah `/pasien`, route admin tetap 106, dan total 150 (141 + 9).
 */
class Phase6RoutePermissionTest extends TestCase
{
    use RefreshDatabase;

    private const PATIENT_NAMESPACE = 'App\\Http\\Controllers\\Patient\\';

    private const CODE_PATTERN = 'UPH-[0-9A-HJKMNP-TV-Z]{4}-[0-9A-HJKMNP-TV-Z]{4}';

    private const SAMPLE_CODE = 'UPH-ABCD-EFGH';

    private const SAMPLE_UUID = '0b8e6f0e-3c0d-4a55-8d5e-2f6b8f6f0a11';

    /** @return array<string, array{string, string, list<string>, list<string>, string}> */
    public static function routes(): array
    {
        $portal = ['web', 'auth', 'account:patient', 'active'];
        $get = ['GET', 'HEAD'];
        $appointments = self::PATIENT_NAMESPACE.'AppointmentController@';

        $rows = [
            'patient.appointments.create' => ['pasien/pendaftaran/baru', $get, $portal, $appointments.'create'],
            'patient.appointments.review' => ['pasien/pendaftaran/tinjau', ['POST'], $portal, $appointments.'review'],
            'patient.appointments.store' => ['pasien/pendaftaran', ['POST'], [...$portal, 'throttle:booking'], $appointments.'store'],
            'patient.appointments.show' => ['pasien/pendaftaran/{appointment}', $get, $portal, $appointments.'show'],
            'patient.appointments.index' => ['pasien/pendaftaran', $get, $portal, $appointments.'index'],
            'patient.appointments.cancel' => ['pasien/pendaftaran/{appointment}/batal', ['POST'], $portal, $appointments.'cancel'],
            'patient.appointments.proof' => ['pasien/pendaftaran/{appointment}/bukti', $get, $portal, $appointments.'proof'],
            'patient.appointments.proof-pdf' => ['pasien/pendaftaran/{appointment}/bukti.pdf', $get, $portal, $appointments.'proofPdf'],
            'patient.notifications.read' => ['pasien/notifikasi/{id}/baca', ['POST'], $portal, self::PATIENT_NAMESPACE.'NotificationController@read'],
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

    public function test_the_phase_6_route_names_are_the_nine_documented_ones_and_are_not_duplicated(): void
    {
        $this->assertCount(9, self::routes());

        foreach (array_keys(self::routes()) as $name) {
            $count = collect(Route::getRoutes()->getRoutes())->filter(fn ($route): bool => $route->getName() === $name)->count();

            $this->assertSame(1, $count, "Nama route {$name} harus tepat satu.");
        }
    }

    public function test_there_are_exactly_thirteen_routes_under_pasien_and_they_are_the_portal_routes(): void
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

    public function test_the_admin_area_still_has_one_hundred_and_six_routes_after_phase_6(): void
    {
        $count = 0;

        foreach (Route::getRoutes()->getRoutes() as $route) {
            if ($route->uri() === 'admin' || str_starts_with($route->uri(), 'admin/')) {
                $count++;
            }
        }

        // Fase 6 tidak menambah route admin (admin pendaftaran, antrean, walk-in, dan bukti admin = fase 7).
        $this->assertSame(106, $count);
    }

    /** Fase 7 memperbarui tes ini saat `admin.appointments.*` dan `admin.patients.*` dibuat. */
    public function test_phase_7_admin_routes_do_not_exist_yet(): void
    {
        foreach (Route::getRoutes()->getRoutes() as $route) {
            $name = (string) $route->getName();

            $this->assertFalse(str_starts_with($name, 'admin.appointments.'), "{$name} adalah route fase 7.");
            $this->assertFalse(str_starts_with($name, 'admin.patients.'), "{$name} adalah route fase 7.");
        }
    }

    /**
     * Setara `route:list --except-vendor`: route milik aplikasi (aksi `App\…`). Tiga route vendor (`storage.local`,
     * `storage.local.upload`, `up`) berupa Closure dan tidak dihitung. 141 sebelum fase 6 + 9 = 150.
     */
    public function test_the_application_has_one_hundred_and_fifty_routes_after_phase_6(): void
    {
        $count = 0;

        foreach (Route::getRoutes()->getRoutes() as $route) {
            if (str_starts_with($route->getActionName(), 'App\\')) {
                $count++;
            }
        }

        $this->assertSame(150, $count);
    }

    public function test_uris_use_indonesian_segments(): void
    {
        $this->assertSame('/pasien/pendaftaran/baru', route('patient.appointments.create', [], false));
        $this->assertSame('/pasien/pendaftaran/tinjau', route('patient.appointments.review', [], false));
        $this->assertSame('/pasien/pendaftaran', route('patient.appointments.store', [], false));
        $this->assertSame('/pasien/pendaftaran', route('patient.appointments.index', [], false));
        $this->assertSame('/pasien/pendaftaran/'.self::SAMPLE_CODE, route('patient.appointments.show', ['appointment' => self::SAMPLE_CODE], false));
        $this->assertSame('/pasien/pendaftaran/'.self::SAMPLE_CODE.'/batal', route('patient.appointments.cancel', ['appointment' => self::SAMPLE_CODE], false));
        $this->assertSame('/pasien/pendaftaran/'.self::SAMPLE_CODE.'/bukti', route('patient.appointments.proof', ['appointment' => self::SAMPLE_CODE], false));
        $this->assertSame('/pasien/pendaftaran/'.self::SAMPLE_CODE.'/bukti.pdf', route('patient.appointments.proof-pdf', ['appointment' => self::SAMPLE_CODE], false));
        $this->assertSame('/pasien/notifikasi/'.self::SAMPLE_UUID.'/baca', route('patient.notifications.read', ['id' => self::SAMPLE_UUID], false));
    }

    public function test_the_code_routes_use_the_booking_code_pattern_and_bind_by_booking_code(): void
    {
        foreach (['show', 'cancel', 'proof', 'proof-pdf'] as $suffix) {
            $route = Route::getRoutes()->getByName('patient.appointments.'.$suffix);

            $this->assertSame(['appointment' => self::CODE_PATTERN], $route->wheres, $suffix);
            $this->assertSame(['appointment' => 'booking_code'], $route->bindingFields(), $suffix);
        }

        foreach (['create', 'review', 'store', 'index'] as $suffix) {
            $route = Route::getRoutes()->getByName('patient.appointments.'.$suffix);

            $this->assertSame([], $route->wheres, $suffix);
            $this->assertSame([], $route->bindingFields(), $suffix);
        }

        $this->assertMatchesRegularExpression('/^'.self::CODE_PATTERN.'$/', BookingCodeGenerator::generate());
    }

    public function test_the_notification_route_accepts_only_uuid_ids(): void
    {
        $route = Route::getRoutes()->getByName('patient.notifications.read');

        $this->assertSame(['id'], array_keys($route->wheres));
        $this->assertSame(1, preg_match('/^'.$route->wheres['id'].'$/', (string) Str::uuid()));
        $this->assertSame(1, preg_match('/^'.$route->wheres['id'].'$/', self::SAMPLE_UUID));

        foreach (['abc', '1', '', 'UPH-ABCD-EFGH', self::SAMPLE_UUID.'x'] as $bad) {
            $this->assertSame(0, preg_match('/^'.$route->wheres['id'].'$/', $bad), "'{$bad}' bukan UUID.");
        }

        $this->assertSame([], Route::getRoutes()->getByName('patient.notifications.read')->bindingFields());
    }

    /** Route statis (`baru`, `tinjau`) didaftarkan sebelum route berkode booking (11 §3.4). */
    public function test_static_routes_are_registered_before_the_booking_code_routes(): void
    {
        $order = [];

        foreach (Route::getRoutes()->getRoutes() as $index => $route) {
            $order[(string) $route->getName()] = $index;
        }

        foreach (['create', 'review'] as $static) {
            $this->assertLessThan($order['patient.appointments.show'], $order['patient.appointments.'.$static], $static);
        }
    }

    public function test_urls_resolve_to_the_documented_routes_and_wrong_formats_do_not_match(): void
    {
        $matches = [
            ['/pasien/pendaftaran/baru', 'GET', 'patient.appointments.create'],
            ['/pasien/pendaftaran/tinjau', 'POST', 'patient.appointments.review'],
            ['/pasien/pendaftaran', 'POST', 'patient.appointments.store'],
            ['/pasien/pendaftaran', 'GET', 'patient.appointments.index'],
            ['/pasien/pendaftaran/'.self::SAMPLE_CODE, 'GET', 'patient.appointments.show'],
            ['/pasien/pendaftaran/'.self::SAMPLE_CODE.'/batal', 'POST', 'patient.appointments.cancel'],
            ['/pasien/pendaftaran/'.self::SAMPLE_CODE.'/bukti', 'GET', 'patient.appointments.proof'],
            ['/pasien/pendaftaran/'.self::SAMPLE_CODE.'/bukti.pdf', 'GET', 'patient.appointments.proof-pdf'],
            ['/pasien/notifikasi/'.self::SAMPLE_UUID.'/baca', 'POST', 'patient.notifications.read'],
        ];

        foreach ($matches as [$uri, $method, $name]) {
            $this->assertSame($name, Route::getRoutes()->match(Request::create($uri, $method))->getName(), "{$method} {$uri}");
        }

        $misses = [
            ['/pasien/pendaftaran/UPH-ABCD-EFGI', 'GET'],
            ['/pasien/pendaftaran/uph-abcd-efgh', 'GET'],
            ['/pasien/pendaftaran/'.self::SAMPLE_CODE.'/hapus', 'POST'],
            ['/pasien/pendaftaran/'.self::SAMPLE_CODE.'/bukti.docx', 'GET'],
            ['/pasien/notifikasi/abc/baca', 'POST'],
            ['/pasien/riwayat', 'GET'],
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

    public function test_get_on_post_only_urls_and_post_on_get_only_urls_answer_405(): void
    {
        $wrongMethod = [
            ['/pasien/pendaftaran/tinjau', 'GET'],
            ['/pasien/pendaftaran', 'PUT'],
            ['/pasien/pendaftaran/'.self::SAMPLE_CODE, 'POST'],
            ['/pasien/pendaftaran/'.self::SAMPLE_CODE.'/batal', 'GET'],
            ['/pasien/pendaftaran/'.self::SAMPLE_CODE.'/bukti', 'POST'],
            ['/pasien/pendaftaran/'.self::SAMPLE_CODE.'/bukti.pdf', 'POST'],
            ['/pasien/notifikasi/'.self::SAMPLE_UUID.'/baca', 'GET'],
        ];

        foreach ($wrongMethod as [$uri, $method]) {
            try {
                Route::getRoutes()->match(Request::create($uri, $method));

                $this->fail("{$method} {$uri} tidak boleh cocok dengan route mana pun.");
            } catch (MethodNotAllowedHttpException) {
                $this->addToAssertionCount(1);
            }
        }

        // Halaman tinjau dibuka lewat GET dialihkan ke wizard (FX9; BookingReviewDirectOpenTest); route tetap POST-only.
        $this->get('/pasien/pendaftaran/tinjau')->assertRedirect(route('patient.appointments.create'));
        $this->get('/pasien/notifikasi/'.self::SAMPLE_UUID.'/baca')->assertStatus(405);
    }

    /** Hanya `store` memakai `throttle:booking` (11 §3.4); tidak ada route lain di `/pasien` yang dibatasi laju. */
    public function test_only_the_booking_submission_is_throttled_under_pasien(): void
    {
        foreach (Route::getRoutes()->getRoutes() as $route) {
            if (! str_starts_with((string) $route->getName(), 'patient.')) {
                continue;
            }

            $throttles = array_values(array_filter(
                $route->gatherMiddleware(),
                fn ($item): bool => is_string($item) && str_starts_with($item, 'throttle:'),
            ));

            $this->assertSame($route->getName() === 'patient.appointments.store' ? ['throttle:booking'] : [], $throttles, (string) $route->getName());
        }

        $this->assertNotNull(RateLimiter::limiter('booking'), 'Limiter `booking` harus terdaftar.');
    }

    /** KNS-02 (sisi pasien): 13 route `patient.*` berpagar `auth`, `account:patient`, dan `active`, tanpa izin Spatie atau `can:`. */
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
                $this->assertStringNotContainsString('can:', $item, "{$route->getName()}: otorisasi lewat Policy di controller/Form Request.");
            }

            $checked++;
        }

        $this->assertSame(13, $checked);
    }

    /** KNS-03: fase 6 tidak menambah izin. */
    public function test_phase_6_adds_no_permission_to_the_catalogue(): void
    {
        $this->assertCount(19, RolePermissionSeeder::PERMISSIONS);
    }
}
