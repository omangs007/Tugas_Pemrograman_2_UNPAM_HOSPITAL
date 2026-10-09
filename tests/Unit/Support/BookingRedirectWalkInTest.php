<?php

namespace Tests\Unit\Support;

use App\Exceptions\BookingException;
use App\Models\PatientProfile;
use App\Support\BookingRedirect;
use App\Support\WalkInReturn;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as RoutingRoute;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InteractsWithMasterData;
use Tests\Support\InteractsWithSchedules;
use Tests\TestCase;

/**
 * F7A-R20: tujuan pengalihan `BookingException` pada `admin.appointments.store` (form walk-in) dan pembangun URL
 * bersama `WalkInReturn`.
 */
class BookingRedirectWalkInTest extends TestCase
{
    use InteractsWithMasterData;
    use InteractsWithSchedules;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-06 07:00:00');
    }

    /** @param  array<string, mixed>  $input */
    private function storeRequest(array $input): Request
    {
        $route = (new RoutingRoute(['POST'], '/uji', fn () => 'ok'))->name('admin.appointments.store');
        $request = Request::create('/uji', 'POST', $input);
        $route->bind($request);
        $request->setRouteResolver(fn () => $route);
        $this->app->instance('request', $request);

        return $request;
    }

    /** @return array<string, array{string, bool}> kode, apakah `sesi` ikut */
    public static function codes(): array
    {
        return [
            'SESSION_UNAVAILABLE' => [BookingException::SESSION_UNAVAILABLE, false],
            'OUTSIDE_WINDOW' => [BookingException::OUTSIDE_WINDOW, false],
            'CUTOFF_PASSED' => [BookingException::CUTOFF_PASSED, false],
            'QUOTA_FULL' => [BookingException::QUOTA_FULL, false],
            'DUPLICATE_BOOKING' => [BookingException::DUPLICATE_BOOKING, false],
            'OVERLAPPING_BOOKING' => [BookingException::OVERLAPPING_BOOKING, false],
            'INVALID_PAYMENT' => [BookingException::INVALID_PAYMENT, true],
            'SYSTEM_BUSY' => [BookingException::SYSTEM_BUSY, true],
        ];
    }

    #[DataProvider('codes')]
    public function test_each_code_returns_to_the_right_step_of_the_walk_in_form(string $code, bool $withSession): void
    {
        $doctor = $this->createDoctor(['name' => 'Sinta Maharani']);
        $session = $this->createSession(['doctor_id' => $doctor->id, 'clinic_id' => $doctor->clinic_id, 'session_date' => '2026-10-06']);
        $profile = PatientProfile::factory()->create();
        $request = $this->storeRequest(['patient_profile_id' => (string) $profile->id, 'practice_session_id' => (string) $session->id]);

        $query = ['pasien' => $profile->id, 'poli' => $doctor->clinic->slug, 'dokter' => $doctor->slug, 'tanggal' => '2026-10-06'];

        if ($withSession) {
            $query['sesi'] = $session->id;
        }

        $this->assertSame(
            route('admin.appointments.create', $query),
            BookingRedirect::after(BookingException::make($code, [], ['practice_session_id' => $session->id]), $request),
        );
    }

    public function test_an_inactive_profile_returns_to_the_form_without_a_selected_patient(): void
    {
        $request = $this->storeRequest(['patient_profile_id' => '5', 'practice_session_id' => '9']);

        $this->assertSame(route('admin.appointments.create'), BookingRedirect::after(BookingException::profileInactive(), $request));
    }

    public function test_a_missing_or_hostile_input_never_breaks_the_url(): void
    {
        $request = $this->storeRequest(['patient_profile_id' => ['1'], 'practice_session_id' => 'abc']);

        $this->assertSame(
            route('admin.appointments.create'),
            BookingRedirect::after(BookingException::make(BookingException::QUOTA_FULL, [], ['practice_session_id' => 3]), $request),
        );
    }

    public function test_walk_in_return_ignores_unknown_sessions_and_non_positive_ids(): void
    {
        $this->assertSame(route('admin.appointments.create'), WalkInReturn::url(null, null, true));
        $this->assertSame(route('admin.appointments.create', ['pasien' => 4]), WalkInReturn::url('4', '999999', true));
        $this->assertSame(route('admin.appointments.create'), WalkInReturn::url('0', '-3', true));
        $this->assertSame(route('admin.appointments.create'), WalkInReturn::url(['1'], 1.5, true));
    }
}
