<?php

namespace Tests\Feature\View;

use App\Enums\AppointmentStatus;
use App\Enums\ExceptionType;
use App\Enums\SessionStatus;
use App\Models\Doctor;
use App\Models\PracticeSession;
use App\Services\Scheduling\AvailabilityFilter;
use App\Services\Scheduling\DoctorAvailabilityQuery;
use App\Services\Scheduling\SessionAvailability;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Tests\Support\InteractsWithMasterData;
use Tests\Support\InteractsWithSchedules;
use Tests\TestCase;

/**
 * `x-schedule-table` (10 §3.3, F3-R24/F3-R25): tabel jadwal dari koleksi SessionAvailability; di bawah 768 px
 * baris menjadi kartu (`.table-cards` + `data-label`). Tombol Daftar hanya bila route pendaftaran ada dan sesi
 * dapat dipesan online.
 */
class ScheduleTableComponentTest extends TestCase
{
    use InteractsWithMasterData;
    use InteractsWithSchedules;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-05 07:00:00'); // Senin
    }

    private function doctor(string $name = 'Laras Wulandari'): Doctor
    {
        return $this->createDoctor(['name' => $name, 'title_prefix' => 'dr.', 'title_suffix' => 'Sp.A']);
    }

    /** @param  array<string, mixed>  $overrides */
    private function sessionOf(Doctor $doctor, string $date, array $overrides = []): PracticeSession
    {
        return $this->createSession(array_merge([
            'doctor_id' => $doctor->id,
            'clinic_id' => $doctor->clinic_id,
            'session_date' => $date,
            'start_time' => '09:00:00',
            'end_time' => '12:00:00',
            'quota' => 20,
        ], $overrides));
    }

    /** @return Collection<int, SessionAvailability> */
    private function items(): Collection
    {
        return app(DoctorAvailabilityQuery::class)->sessions(new AvailabilityFilter(
            from: CarbonImmutable::today(),
            to: CarbonImmutable::today()->addDays(10),
            includePast: true,
        ));
    }

    private function render(Collection $items, string $attributes = ''): string
    {
        return Blade::render('<x-schedule-table :sessions="$items" '.$attributes.' />', ['items' => $items]);
    }

    private function fakeRegistrationRoute(): void
    {
        if (! Route::has('patient.appointments.create')) {
            Route::middleware('web')->get('/_uji/pasien/pendaftaran/baru', fn () => 'x')->name('patient.appointments.create');
            Route::getRoutes()->refreshNameLookups();
        }
    }

    public function test_it_renders_a_table_with_caption_scoped_headers_and_mobile_labels(): void
    {
        $doctor = $this->doctor();
        $this->sessionOf($doctor, '2026-10-06');

        $html = $this->render($this->items(), 'caption="Jadwal uji komponen"');

        $this->assertStringContainsString('<caption class="visually-hidden">Jadwal uji komponen</caption>', $html);
        $this->assertStringContainsString('table-cards', $html);

        foreach (['Tanggal', 'Jam', 'Dokter', 'Poliklinik', 'Sisa kuota', 'Status', 'Aksi'] as $heading) {
            $this->assertStringContainsString('<th scope="col">'.$heading.'</th>', $html);
        }

        $this->assertStringContainsString('<th scope="row" class="fw-semibold"><time datetime="2026-10-06">Sel, 6 Okt 2026</time></th>', $html);

        foreach (['Jam', 'Dokter', 'Poliklinik', 'Sisa kuota', 'Status', 'Aksi'] as $label) {
            $this->assertStringContainsString('data-label="'.$label.'"', $html);
        }

        $this->assertStringContainsString('09.00–12.00', $html);
        $this->assertStringContainsString('dr. Laras Wulandari, Sp.A', $html);
        $this->assertStringContainsString('20 dari 20', $html);
        $this->assertStringContainsString('Tersedia · sisa 20', $html);
    }

    public function test_show_doctor_false_drops_the_doctor_column(): void
    {
        $doctor = $this->doctor();
        $this->sessionOf($doctor, '2026-10-06');

        $html = $this->render($this->items(), ':show-doctor="false" :show-action="false"');

        $this->assertStringNotContainsString('<th scope="col">Dokter</th>', $html);
        $this->assertStringNotContainsString('data-label="Dokter"', $html);
        $this->assertStringNotContainsString('dr. Laras Wulandari', $html);
        $this->assertStringContainsString('<th scope="col">Poliklinik</th>', $html);
    }

    public function test_show_action_false_drops_the_action_column(): void
    {
        $this->sessionOf($this->doctor(), '2026-10-06');

        $html = $this->render($this->items(), ':show-action="false"');

        $this->assertStringNotContainsString('<th scope="col">Aksi</th>', $html);
        $this->assertStringNotContainsString('data-label="Aksi"', $html);
    }

    public function test_register_button_is_shown_only_for_online_bookable_sessions_when_the_route_exists(): void
    {
        $this->fakeRegistrationRoute();
        $doctor = $this->doctor();
        $open = $this->sessionOf($doctor, '2026-10-06');
        $this->sessionOf($doctor, '2026-10-07', ['quota' => 1, 'booked_count' => 1]);
        $this->sessionOf($doctor, '2026-10-08', ['status' => SessionStatus::Closed]);

        $html = $this->render($this->items());

        $this->assertSame(1, substr_count($html, '>Daftar</a>'));
        $this->assertStringContainsString('href="'.route('patient.appointments.create', ['sesi' => $open->id]).'"', $html);
        $this->assertStringContainsString('aria-label="Daftar ke dr. Laras Wulandari, Sp.A, Selasa, 6 Oktober 2026, 09.00 sampai 12.00"', $html);
        $this->assertStringContainsString('Penuh', $html);
        $this->assertStringContainsString('Ditutup', $html);
    }

    public function test_register_button_is_hidden_while_the_registration_route_does_not_exist(): void
    {
        if (Route::has('patient.appointments.create')) {
            $this->markTestSkipped('Route patient.appointments.create sudah ada (fase 6): tombol diuji di tes fase 6.');
        }

        $this->sessionOf($this->doctor(), '2026-10-06');

        $html = $this->render($this->items());

        $this->assertStringNotContainsString('>Daftar</a>', $html);
        $this->assertStringContainsString('<span class="small text-body-secondary">Tersedia</span>', $html);
    }

    public function test_leave_holiday_and_cancelled_sessions_show_a_dash_instead_of_the_quota(): void
    {
        $doctor = $this->doctor();
        $exception = $this->createException(['doctor_id' => $doctor->id, 'type' => ExceptionType::DoctorLeave, 'start_date' => '2026-10-06', 'end_date' => '2026-10-06']);
        $this->sessionOf($doctor, '2026-10-06', ['status' => SessionStatus::Cancelled, 'schedule_exception_id' => $exception->id, 'status_reason' => 'Cuti tahunan']);

        $html = $this->render($this->items());

        $this->assertStringContainsString('Cuti', $html);
        $this->assertStringContainsString('<span aria-hidden="true">–</span><span class="visually-hidden">tidak berlaku</span>', $html);
        $this->assertStringNotContainsString('20 dari 20', $html);
    }

    public function test_an_empty_collection_renders_the_empty_state_with_custom_texts(): void
    {
        $html = $this->render(new Collection, 'empty-title="Belum ada jadwal praktik dua minggu ke depan" empty-message="Silakan hubungi kami."');

        $this->assertStringContainsString('Belum ada jadwal praktik dua minggu ke depan', $html);
        $this->assertStringContainsString('Silakan hubungi kami.', $html);
        $this->assertStringNotContainsString('<table', $html);
    }

    public function test_an_empty_collection_uses_the_default_empty_title(): void
    {
        $this->assertStringContainsString('Belum ada jadwal praktik', $this->render(new Collection));
    }

    public function test_past_sessions_are_labelled_without_an_action(): void
    {
        $this->travelTo('2026-10-05 13:00:00');
        $this->sessionOf($this->doctor(), '2026-10-05');

        $html = $this->render($this->items());

        $this->assertStringContainsString('Sudah lewat', $html);
        $this->assertStringNotContainsString('>Daftar</a>', $html);
    }

    public function test_only_active_specialties_are_shown_under_the_doctor_name(): void
    {
        $hidden = $this->createSpecialty(['name' => 'Spesialis Nonaktif Utama', 'is_active' => false]);
        $shown = $this->createSpecialty(['name' => 'Spesialis Aktif Tambahan']);
        $doctor = $this->createDoctor(['name' => 'Laras Wulandari'], null, [$hidden, $shown]);
        $this->sessionOf($doctor, '2026-10-06');

        $html = $this->render($this->items());

        $this->assertStringContainsString('<span class="d-block small text-body-secondary">Spesialis Aktif Tambahan</span>', $html);
        $this->assertStringNotContainsString('Spesialis Nonaktif Utama', $html);
    }

    public function test_many_rows_render_in_strict_mode_without_lazy_loading(): void
    {
        $this->assertTrue(Model::preventsLazyLoading());

        foreach (range(1, 6) as $number) {
            $doctor = $this->doctor("Dokter Uji {$number}");

            foreach (['2026-10-06', '2026-10-08'] as $date) {
                $session = $this->sessionOf($doctor, $date);
                $this->bookAppointment($session, AppointmentStatus::Confirmed);
            }
        }

        $html = $this->render($this->items());

        $this->assertSame(12, substr_count($html, '<th scope="row"'));
        $this->assertStringContainsString('dr. Dokter Uji 6, Sp.A', $html);
        $this->assertStringContainsString('19 dari 20', $html);
    }

    public function test_no_inline_handlers_or_nested_links_are_rendered(): void
    {
        $this->fakeRegistrationRoute();
        $this->sessionOf($this->doctor(), '2026-10-06');

        $html = $this->render($this->items());

        $this->assertSame(0, preg_match('/\son[a-z]+\s*=/i', $html));
        $this->assertSame(0, preg_match('/<a\b[^>]*>(?:(?!<\/a>).)*<a\b/s', $html));
    }
}
