<?php

namespace Tests\Feature\View;

use App\Enums\ExceptionType;
use App\Enums\SessionStatus;
use App\Models\Doctor;
use App\Services\Scheduling\AvailabilityFilter;
use App\Services\Scheduling\DoctorAvailabilityQuery;
use App\Services\Scheduling\SessionAvailability;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Tests\Support\InteractsWithMasterData;
use Tests\Support\InteractsWithSchedules;
use Tests\TestCase;

/**
 * Prop `nextSession` pada `x-doctor-card` (F3-R25, 10 §3.3): lencana ketersediaan, teks "Jadwal terdekat", dan
 * tombol Daftar yang hanya muncul bila route pendaftaran ada dan sesi dapat dipesan online. Tidak ada tombol
 * "Lihat Jadwal" terpisah dan tidak ada tautan bersarang.
 */
class DoctorCardNextSessionTest extends TestCase
{
    use InteractsWithMasterData;
    use InteractsWithSchedules;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-05 07:00:00'); // Senin
    }

    /** @param  array<string, mixed>  $overrides */
    private function nextSessionFor(Doctor $doctor, array $overrides = []): SessionAvailability
    {
        $session = $this->createSession(array_merge([
            'doctor_id' => $doctor->id,
            'clinic_id' => $doctor->clinic_id,
            'session_date' => '2026-10-06',
            'start_time' => '09:00:00',
            'end_time' => '12:00:00',
            'quota' => 20,
        ], $overrides));

        return app(DoctorAvailabilityQuery::class)->sessions(new AvailabilityFilter(
            doctorId: $doctor->id,
            from: CarbonImmutable::today(),
            to: CarbonImmutable::today()->addDays(10),
            includePast: true,
        ))->first(fn (SessionAvailability $item): bool => $item->session->id === $session->id);
    }

    private function render(Doctor $doctor, ?SessionAvailability $next): string
    {
        $doctor = Doctor::query()->with(['specialties', 'media', 'clinic'])->findOrFail($doctor->id);

        return Blade::render('<x-doctor-card :doctor="$doctor" :next-session="$next" />', ['doctor' => $doctor, 'next' => $next]);
    }

    private function fakeRegistrationRoute(): void
    {
        if (! Route::has('patient.appointments.create')) {
            Route::middleware('web')->get('/_uji/pasien/pendaftaran/baru', fn () => 'x')->name('patient.appointments.create');
            Route::getRoutes()->refreshNameLookups();
        }
    }

    public function test_without_a_next_session_the_card_shows_no_schedule_information(): void
    {
        $doctor = $this->createDoctor();

        $html = $this->render($doctor, null);

        $this->assertStringNotContainsString('Jadwal terdekat', $html);
        $this->assertStringNotContainsString('availability-badge', $html);
        $this->assertStringNotContainsString('Lihat Jadwal', $html);
    }

    public function test_it_shows_the_availability_badge_and_the_nearest_schedule_text(): void
    {
        $doctor = $this->createDoctor();
        $next = $this->nextSessionFor($doctor);

        $html = $this->render($doctor, $next);

        $this->assertStringContainsString('Tersedia · sisa 20', $html);
        $this->assertStringContainsString('Jadwal terdekat: <time datetime="2026-10-06">Selasa, 6 Oktober 2026</time> · 09.00–12.00', $html);
        $this->assertStringNotContainsString('Lihat Jadwal', $html);
    }

    public function test_a_leave_session_shows_its_status_without_a_register_button(): void
    {
        $this->fakeRegistrationRoute();
        $doctor = $this->createDoctor();
        $exception = $this->createException(['doctor_id' => $doctor->id, 'type' => ExceptionType::DoctorLeave, 'start_date' => '2026-10-06', 'end_date' => '2026-10-06']);
        $next = $this->nextSessionFor($doctor, ['status' => SessionStatus::Cancelled, 'schedule_exception_id' => $exception->id]);

        $html = $this->render($doctor, $next);

        $this->assertStringContainsString('Cuti', $html);
        $this->assertStringNotContainsString('>Daftar</a>', $html);
    }

    public function test_register_button_appears_only_for_an_online_bookable_session_when_the_route_exists(): void
    {
        $this->fakeRegistrationRoute();
        $doctor = $this->createDoctor();
        $open = $this->nextSessionFor($doctor);
        $fullDoctor = $this->createDoctor();
        $full = $this->nextSessionFor($fullDoctor, ['quota' => 1, 'booked_count' => 1]);

        $html = $this->render($doctor, $open);

        $this->assertStringContainsString('>Daftar</a>', $html);
        $this->assertStringContainsString('href="'.route('patient.appointments.create', ['sesi' => $open->session->id]).'"', $html);
        $this->assertStringContainsString('position-relative z-2', $html);
        $this->assertStringNotContainsString('>Daftar</a>', $this->render($fullDoctor, $full));
        $this->assertStringContainsString('Penuh', $this->render($fullDoctor, $full));
    }

    public function test_the_card_never_nests_links_even_with_the_register_button(): void
    {
        $this->fakeRegistrationRoute();
        $doctor = $this->createDoctor();

        $html = $this->render($doctor, $this->nextSessionFor($doctor));

        $this->assertSame(0, preg_match('/<a\b[^>]*>(?:(?!<\/a>).)*<a\b/s', $html));
        $this->assertSame(0, preg_match('/\son[a-z]+\s*=/i', $html));
    }
}
