<?php

namespace Tests\Unit\Support;

use App\Exceptions\BookingException;
use App\Exceptions\InvalidTransitionException;
use App\Models\PracticeSession;
use App\Support\BookingRedirect;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as RoutingRoute;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InteractsWithMasterData;
use Tests\Support\InteractsWithSchedules;
use Tests\TestCase;

/**
 * F7A-R20: pemetaan `BookingException` pada `admin.queue.mark-no-show` (Tandai tidak hadir massal) ke antrean sesi
 * itu dengan `tanggal` (= `session_date`) dan `sesi`. Route admin lain tetap kembali ke halaman sebelumnya (diuji
 * `BookingRedirectAdminTest`).
 */
class BookingRedirectQueueTest extends TestCase
{
    use InteractsWithMasterData;
    use InteractsWithSchedules;
    use RefreshDatabase;

    private PracticeSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-06 07:00:00');

        $doctor = $this->createDoctor(['name' => 'Sinta Maharani']);
        $this->session = $this->createSession([
            'doctor_id' => $doctor->id,
            'clinic_id' => $doctor->clinic_id,
            'session_date' => '2026-10-05',
            'doctor_schedule_id' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $routeParameters
     */
    private function requestOn(array $input = [], array $routeParameters = []): Request
    {
        $uri = '/uji'.implode('', array_map(fn (string $name): string => '/{'.$name.'}', array_keys($routeParameters)));
        $path = '/uji'.str_repeat('/x', count($routeParameters));
        $route = (new RoutingRoute(['POST'], $uri, fn () => 'ok'))->name('admin.queue.mark-no-show');
        $request = Request::create($path, 'POST', $input);
        $route->bind($request);

        foreach ($routeParameters as $name => $value) {
            $route->setParameter($name, $value);
        }

        $request->setRouteResolver(fn () => $route);
        $this->app->instance('request', $request);

        return $request;
    }

    private function queueUrl(): string
    {
        return route('admin.queue.index', ['tanggal' => '2026-10-05', 'sesi' => $this->session->id]);
    }

    private function notEnded(): InvalidTransitionException
    {
        return InvalidTransitionException::make(InvalidTransitionException::SESSION_NOT_ENDED, ['time' => '12.00']);
    }

    public function test_a_bound_session_returns_to_its_own_queue(): void
    {
        $request = $this->requestOn([], ['session' => $this->session]);

        $this->assertSame($this->queueUrl(), BookingRedirect::after($this->notEnded(), $request));
    }

    public function test_a_numeric_route_parameter_is_resolved_to_the_session(): void
    {
        $request = $this->requestOn([], ['session' => (string) $this->session->id]);

        $this->assertSame($this->queueUrl(), BookingRedirect::after($this->notEnded(), $request));
    }

    public function test_the_error_context_is_the_fallback_when_the_route_has_no_session(): void
    {
        $request = $this->requestOn();
        $exception = InvalidTransitionException::make(
            InvalidTransitionException::SESSION_NOT_ENDED,
            ['time' => '12.00'],
            ['practice_session_id' => $this->session->id],
        );

        $this->assertSame($this->queueUrl(), BookingRedirect::after($exception, $request));
    }

    public function test_the_under_review_variant_returns_to_the_same_queue(): void
    {
        $request = $this->requestOn([], ['session' => $this->session]);
        $exception = InvalidTransitionException::makeVariant(
            InvalidTransitionException::INVALID_TRANSITION,
            'SESSION_UNDER_REVIEW',
            [],
            ['practice_session_id' => $this->session->id],
        );

        $this->assertSame($this->queueUrl(), BookingRedirect::after($exception, $request));
    }

    public function test_every_transition_error_code_and_a_busy_system_go_to_the_same_queue(): void
    {
        $request = $this->requestOn([], ['session' => $this->session]);

        foreach (InvalidTransitionException::CODES as $code) {
            $this->assertSame($this->queueUrl(), BookingRedirect::after(InvalidTransitionException::make($code), $request), $code);
        }

        $this->assertSame($this->queueUrl(), BookingRedirect::after(BookingException::make(BookingException::SYSTEM_BUSY), $request));
    }

    public function test_the_date_comes_from_the_session_not_from_the_request(): void
    {
        $request = $this->requestOn(['tanggal' => '2030-01-01', 'sesi' => '999', 'kembali' => '/admin/pendaftaran'], ['session' => $this->session]);

        $this->assertSame($this->queueUrl(), BookingRedirect::after($this->notEnded(), $request));
    }

    /** @return array<string, array{mixed}> */
    public static function unusableParameters(): array
    {
        return [
            'huruf' => ['abc'],
            'nol' => ['0'],
            'negatif' => ['-3'],
            'id tidak ada' => ['999999'],
            'larik' => [['1']],
            'desimal' => ['1.5'],
        ];
    }

    #[DataProvider('unusableParameters')]
    public function test_an_unusable_session_parameter_returns_to_the_plain_queue(mixed $parameter): void
    {
        $request = $this->requestOn([], ['session' => $parameter]);

        $this->assertSame(route('admin.queue.index'), BookingRedirect::after($this->notEnded(), $request));
    }

    public function test_a_hostile_context_never_breaks_the_url(): void
    {
        $request = $this->requestOn();
        $exception = InvalidTransitionException::make(InvalidTransitionException::SESSION_NOT_ENDED, ['time' => '12.00'], ['practice_session_id' => ['1']]);

        $this->assertSame(route('admin.queue.index'), BookingRedirect::after($exception, $request));
    }
}
