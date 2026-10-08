<?php

namespace Tests\Unit\Support;

use App\Exceptions\BookingException;
use App\Exceptions\InvalidTransitionException;
use App\Models\Appointment;
use App\Support\BookingRedirect;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InteractsWithMasterData;
use Tests\Support\InteractsWithSchedules;
use Tests\TestCase;

/**
 * F6-R10: tujuan pengalihan per kode `BookingException` (tidak pernah back()). Route `show` (T8) dan `index` (T9)
 * didaftarkan sebagai route palsu hanya bila route asli belum ada, sehingga tes tetap valid sesudah T8/T9.
 */
class BookingRedirectTest extends TestCase
{
    use InteractsWithMasterData;
    use InteractsWithSchedules;
    use RefreshDatabase;

    private const CODE = 'UPH-7K3M-9QWA';

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-05 07:00:00');

        if (! Route::has('patient.appointments.show')) {
            Route::middleware('web')->get('/_uji/pasien/pendaftaran/{appointment}', fn () => 'x')->name('patient.appointments.show');
        }

        if (! Route::has('patient.appointments.index')) {
            Route::middleware('web')->get('/_uji/pasien/pendaftaran', fn () => 'x')->name('patient.appointments.index');
        }

        Route::getRoutes()->refreshNameLookups();
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $routeParameters
     */
    private function requestOn(string $routeName, array $input = [], array $routeParameters = []): Request
    {
        $uri = '/uji'.implode('', array_map(fn (string $name): string => '/{'.$name.'}', array_keys($routeParameters)));
        $path = '/uji'.str_repeat('/x', count($routeParameters));
        $route = (new RoutingRoute(['POST'], $uri, fn () => 'ok'))->name($routeName);
        $request = Request::create($path, 'POST', $input);
        $route->bind($request);

        foreach ($routeParameters as $name => $value) {
            $route->setParameter($name, $value);
        }

        $request->setRouteResolver(fn () => $route);
        $this->app->instance('request', $request);

        return $request;
    }

    /** @return array<string, array{string}> */
    public static function wizardRoutes(): array
    {
        return [
            'tinjau' => ['patient.appointments.review'],
            'simpan' => ['patient.appointments.store'],
        ];
    }

    /** @return array<string, array{string, string}> */
    public static function stepThreeCodes(): array
    {
        $cases = [];

        foreach ([BookingException::SESSION_UNAVAILABLE, BookingException::OUTSIDE_WINDOW, BookingException::CUTOFF_PASSED, BookingException::QUOTA_FULL, BookingException::OVERLAPPING_BOOKING] as $code) {
            foreach (self::wizardRoutes() as $label => [$routeName]) {
                $cases["{$code} / {$label}"] = [$code, $routeName];
            }
        }

        return $cases;
    }

    #[DataProvider('stepThreeCodes')]
    public function test_session_level_failures_return_to_step_three_of_the_same_doctor(string $code, string $routeName): void
    {
        $doctor = $this->createDoctor();
        $session = $this->createSession(['doctor_id' => $doctor->id, 'clinic_id' => $doctor->clinic_id]);
        $clinic = $doctor->clinic()->firstOrFail();
        $request = $this->requestOn($routeName, ['practice_session_id' => (string) $session->id]);

        $this->assertSame(
            route('patient.appointments.create', ['poli' => $clinic->slug, 'dokter' => $doctor->slug]),
            BookingRedirect::after(BookingException::make($code), $request),
        );
    }

    #[DataProvider('wizardRoutes')]
    public function test_payment_and_busy_failures_return_to_step_four(string $routeName): void
    {
        $session = $this->createSession();
        $request = $this->requestOn($routeName, ['practice_session_id' => (string) $session->id]);

        foreach ([BookingException::INVALID_PAYMENT, BookingException::SYSTEM_BUSY] as $code) {
            $this->assertSame(
                route('patient.appointments.create', ['sesi' => (string) $session->id]),
                BookingRedirect::after(BookingException::make($code), $request),
                $code,
            );
        }
    }

    public function test_step_four_without_a_usable_session_id_falls_back_to_the_start_of_the_wizard(): void
    {
        foreach ([[], ['practice_session_id' => ['1']], ['practice_session_id' => 'abc']] as $input) {
            $request = $this->requestOn('patient.appointments.store', $input);

            $this->assertSame(
                route('patient.appointments.create'),
                BookingRedirect::after(BookingException::make(BookingException::SYSTEM_BUSY), $request),
            );
        }
    }

    #[DataProvider('wizardRoutes')]
    public function test_a_session_that_no_longer_exists_returns_to_the_start_of_the_wizard(string $routeName): void
    {
        foreach (['999999', ['1'], 'abc', ''] as $value) {
            $request = $this->requestOn($routeName, ['practice_session_id' => $value]);

            $this->assertSame(
                route('patient.appointments.create'),
                BookingRedirect::after(BookingException::make(BookingException::QUOTA_FULL), $request),
            );
        }
    }

    #[DataProvider('wizardRoutes')]
    public function test_duplicate_booking_goes_to_the_existing_appointment(string $routeName): void
    {
        $request = $this->requestOn($routeName, ['practice_session_id' => '1']);
        $exception = BookingException::make(BookingException::DUPLICATE_BOOKING, [], ['appointment_code' => self::CODE]);

        $this->assertSame(
            route('patient.appointments.show', ['appointment' => self::CODE]),
            BookingRedirect::after($exception, $request),
        );
    }

    #[DataProvider('wizardRoutes')]
    public function test_duplicate_booking_without_a_usable_code_behaves_like_a_full_session(string $routeName): void
    {
        $doctor = $this->createDoctor();
        $session = $this->createSession(['doctor_id' => $doctor->id, 'clinic_id' => $doctor->clinic_id]);
        $clinic = $doctor->clinic()->firstOrFail();
        $request = $this->requestOn($routeName, ['practice_session_id' => (string) $session->id]);
        $expected = route('patient.appointments.create', ['poli' => $clinic->slug, 'dokter' => $doctor->slug]);

        foreach ([[], ['appointment_code' => '<script>'], ['appointment_code' => ['UPH-7K3M-9QWA']], ['appointment_code' => 'uph-7k3m-9qwa'], ['appointment_code' => 'UPH-7K3M-9QWI']] as $context) {
            $exception = BookingException::make(BookingException::DUPLICATE_BOOKING, [], $context);

            $this->assertSame($expected, BookingRedirect::after($exception, $request), json_encode($context));
        }
    }

    #[DataProvider('wizardRoutes')]
    public function test_active_limit_goes_to_the_history_list(string $routeName): void
    {
        $request = $this->requestOn($routeName, ['practice_session_id' => '1']);

        $this->assertSame(
            route('patient.appointments.index'),
            BookingRedirect::after(BookingException::make(BookingException::ACTIVE_LIMIT_REACHED), $request),
        );
    }

    public function test_a_failed_cancellation_returns_to_the_appointment_detail(): void
    {
        $exception = InvalidTransitionException::make('CANCEL_CUTOFF_PASSED');

        $request = $this->requestOn('patient.appointments.cancel', [], ['appointment' => self::CODE]);

        $this->assertSame(
            route('patient.appointments.show', ['appointment' => self::CODE]),
            BookingRedirect::after($exception, $request),
        );

        $appointment = new Appointment;
        $appointment->forceFill(['booking_code' => self::CODE]);
        $request = $this->requestOn('patient.appointments.cancel', [], ['appointment' => $appointment]);

        $this->assertSame(
            route('patient.appointments.show', ['appointment' => self::CODE]),
            BookingRedirect::after($exception, $request),
        );
    }

    public function test_a_cancellation_without_a_valid_code_falls_back_to_the_previous_page(): void
    {
        $request = $this->requestOn('patient.appointments.cancel', [], ['appointment' => 'bukan-kode']);
        $request->headers->set('referer', 'http://localhost/pasien');
        $this->app->instance('request', $request);

        $this->assertSame(
            'http://localhost/pasien',
            BookingRedirect::after(InvalidTransitionException::make('ALREADY_FINAL'), $request),
        );
    }

    public function test_any_other_route_goes_back_to_the_previous_page(): void
    {
        $request = $this->requestOn('patient.profile.update');
        $request->headers->set('referer', 'http://localhost/pasien/profil');
        $this->app->instance('request', $request);

        $this->assertSame(
            'http://localhost/pasien/profil',
            BookingRedirect::after(BookingException::make(BookingException::QUOTA_FULL), $request),
        );
    }
}
