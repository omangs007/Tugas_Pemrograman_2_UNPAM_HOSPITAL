<?php

namespace Tests\Unit\Support;

use App\Enums\AppointmentStatus;
use App\Exceptions\BookingException;
use App\Exceptions\InvalidTransitionException;
use App\Models\Appointment;
use App\Support\BookingRedirect;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as RoutingRoute;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InteractsWithMasterData;
use Tests\Support\InteractsWithSchedules;
use Tests\TestCase;

/**
 * F7A-R20: pemetaan `BookingException` pada route admin ke tujuan pengalihan (cabang transisi status; cabang walk-in
 * dan antrean ditambah T9 dan T10). Route admin lain tetap kembali ke halaman sebelumnya.
 */
class BookingRedirectAdminTest extends TestCase
{
    use InteractsWithMasterData;
    use InteractsWithSchedules;
    use RefreshDatabase;

    private const CODE = 'UPH-ABCD-2345';

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

    private function stale(): InvalidTransitionException
    {
        return InvalidTransitionException::make('STALE_STATUS');
    }

    private function detailUrl(string $code = self::CODE): string
    {
        return route('admin.appointments.show', ['appointment' => $code]);
    }

    public function test_a_transition_failure_returns_to_the_detail_by_default(): void
    {
        $request = $this->requestOn('admin.appointments.transition', [], ['appointment' => self::CODE]);

        $this->assertSame($this->detailUrl(), BookingRedirect::after($this->stale(), $request));
    }

    public function test_the_route_parameter_may_be_the_bound_model(): void
    {
        $session = $this->createSession();
        $appointment = $this->bookAppointment($session, AppointmentStatus::Confirmed, ['booking_code' => self::CODE]);
        $request = $this->requestOn('admin.appointments.transition', [], ['appointment' => Appointment::query()->findOrFail($appointment->id)]);

        $this->assertSame($this->detailUrl(), BookingRedirect::after($this->stale(), $request));
    }

    public function test_a_valid_kembali_to_the_queue_or_the_list_wins(): void
    {
        foreach (['/admin/antrean?tanggal=2026-10-06&sesi=3', '/admin/pendaftaran?status=pending', '/admin/pendaftaran/UPH-WXYZ-6789'] as $back) {
            $request = $this->requestOn('admin.appointments.transition', ['kembali' => $back], ['appointment' => self::CODE]);

            $this->assertSame($back, BookingRedirect::after($this->stale(), $request), $back);
        }
    }

    /** @return array<string, array{mixed}> */
    public static function badKembali(): array
    {
        return [
            'domain lain' => ['https://evil.test/admin/antrean'],
            'protokol relatif' => ['//evil.test'],
            'portal pasien' => ['/pasien/pendaftaran'],
            'area admin lain' => ['/admin/pasien'],
            'segmen titik-titik' => ['/admin/antrean/../pasien'],
            'larik' => [['/admin/antrean']],
            'bilangan' => [123],
        ];
    }

    #[DataProvider('badKembali')]
    public function test_an_unsafe_kembali_is_ignored(mixed $back): void
    {
        $request = $this->requestOn('admin.appointments.transition', ['kembali' => $back], ['appointment' => self::CODE]);

        $this->assertSame($this->detailUrl(), BookingRedirect::after($this->stale(), $request));
    }

    public function test_without_a_valid_booking_code_the_fallback_is_the_list(): void
    {
        $request = $this->requestOn('admin.appointments.transition', [], ['appointment' => 'bukan-kode']);

        $this->assertSame(route('admin.appointments.index'), BookingRedirect::after($this->stale(), $request));
    }

    public function test_every_transition_error_code_goes_to_the_same_place(): void
    {
        $request = $this->requestOn('admin.appointments.transition', [], ['appointment' => self::CODE]);

        foreach (InvalidTransitionException::CODES as $code) {
            $this->assertSame($this->detailUrl(), BookingRedirect::after(InvalidTransitionException::make($code), $request), $code);
        }

        $this->assertSame($this->detailUrl(), BookingRedirect::after(BookingException::make(BookingException::SYSTEM_BUSY), $request));
    }

    public function test_other_admin_routes_still_go_back_to_the_previous_page(): void
    {
        $request = $this->requestOn('admin.doctors.status', [], ['doctor' => '3']);
        $request->headers->set('referer', 'http://localhost/admin/dokter/3');
        $this->app->instance('request', $request);

        $this->assertSame(
            'http://localhost/admin/dokter/3',
            BookingRedirect::after(BookingException::make(BookingException::QUOTA_FULL), $request),
        );
    }
}
