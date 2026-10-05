<?php

namespace Tests\Feature\Public;

use App\Enums\AppointmentStatus;
use App\Enums\Availability;
use App\Enums\DoctorStatus;
use App\Enums\ExceptionType;
use App\Enums\SessionStatus;
use App\Http\Controllers\Public\DoctorController;
use App\Http\Controllers\Public\ScheduleController;
use App\Models\Doctor;
use App\Models\PracticeSession;
use App\Models\User;
use App\Services\Scheduling\AvailabilityFilter;
use App\Services\Scheduling\DoctorAvailabilityQuery;
use App\Services\Scheduling\PracticeSessionGenerator;
use App\Services\Scheduling\ScheduleChangeService;
use App\Support\Actor;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use Tests\Support\InteractsWithMasterData;
use Tests\Support\InteractsWithSchedules;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

/**
 * Halaman #8 `schedules.index` (05 hal. 8, 07 W-05, F3-R9/R17/R18/R24): grid mingguan dan daftar bertumpuk dari
 * koleksi DoctorAvailabilityQuery yang sama. Mencakup CRD-05, JDW-12 (kontrak: #7 dan #8 memakai kelas yang sama dan
 * menampilkan nilai yang sama), VAL-07, dan Review Focus 1/4.
 */
class PublicScheduleTest extends TestCase
{
    use InteractsWithMasterData;
    use InteractsWithSchedules;
    use InteractsWithStaff;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->travelTo('2026-10-05 07:00:00'); // Senin; jendela pendaftaran 30 hari berakhir Rabu 4 November
        $this->markHorizonGenerated();
    }

    private function markHorizonGenerated(): void
    {
        $today = CarbonImmutable::today();
        Cache::put(PracticeSessionGenerator::markerKey($today), true, $today->endOfDay());
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

    private function rowFor(string $html, string $date): string
    {
        preg_match_all('#<tr>.*?</tr>#s', $html, $matches);

        foreach ($matches[0] as $row) {
            if (! str_contains($row, 'scope="col"') && str_contains($row, 'datetime="'.$date.'"')) {
                return $row;
            }
        }

        $this->fail("Baris jadwal untuk {$date} tidak ditemukan.");
    }

    /** Bagian grid mingguan saja (tanpa daftar bertumpuk dan legenda). */
    private function gridOf(string $html): string
    {
        $start = strpos($html, 'data-week-grid');
        $end = strpos($html, 'data-week-stack');
        $this->assertNotFalse($start);

        return substr($html, $start, ($end === false ? strlen($html) : $end) - $start);
    }

    private function fakeRegistrationRoute(): void
    {
        if (! Route::has('patient.appointments.create')) {
            Route::middleware('web')->get('/_uji/pasien/pendaftaran/baru', fn () => 'x')->name('patient.appointments.create');
            Route::getRoutes()->refreshNameLookups();
        }
    }

    // --- judul, SEO, breadcrumb, legenda (F3-R18) -----------------------------------------------

    public function test_index_renders_the_static_title_subtitle_meta_description_and_breadcrumb(): void
    {
        $response = $this->get(route('schedules.index'))->assertOk();

        $response->assertSee('<title>Jadwal Dokter — '.config('app.name').'</title>', false)
            ->assertSee('Jadwal praktik per minggu')
            ->assertSee('<meta name="description" content="Jadwal praktik dokter UNPAM HOSPITAL per minggu dengan sisa kuota.">', false)
            ->assertSeeInOrder(['aria-label="Breadcrumb"', 'Beranda', 'Jadwal Dokter'], false)
            ->assertSee('<li class="breadcrumb-item active" aria-current="page">Jadwal Dokter</li>', false)
            ->assertSee('<link rel="canonical" href="'.route('schedules.index').'">', false);
        $this->assertSame(1, substr_count($response->getContent(), '<h1'));
    }

    public function test_the_status_legend_is_always_visible_even_without_sessions(): void
    {
        $html = $this->get(route('schedules.index'))->assertOk()->assertSee('Keterangan status')->getContent();

        foreach (Availability::cases() as $case) {
            $this->assertStringContainsString('data-availability="'.$case->value.'"', $html);
            $this->assertStringContainsString($case->label(), $html);
        }
    }

    // --- minggu berjalan dan grid ---------------------------------------------------------------

    public function test_the_default_week_is_the_current_week_and_a_week_without_sessions_shows_no_grid(): void
    {
        $this->get(route('schedules.index'))->assertOk()->assertSee('Minggu 5 Oktober – 11 Oktober 2026');

        $this->createDoctor();
        $html = $this->get(route('schedules.index'))->getContent();

        $this->assertStringNotContainsString('data-week-grid', $html, 'Tanpa sesi, grid tidak ditampilkan.');
        $this->assertStringContainsString('Tidak ada jadwal praktik pada pekan ini', $html);
    }

    public function test_grid_has_a_row_per_doctor_a_column_per_day_and_one_cell_per_session(): void
    {
        $laras = $this->createDoctor(['name' => 'Laras Wulandari', 'title_prefix' => 'dr.', 'title_suffix' => 'Sp.A', 'sort_order' => 1]);
        $rani = $this->createDoctor(['name' => 'Rani Oktaviani', 'title_prefix' => 'dr.', 'title_suffix' => null, 'sort_order' => 2]);
        $this->sessionOf($laras, '2026-10-06');
        $this->sessionOf($laras, '2026-10-06', ['start_time' => '13:00:00', 'end_time' => '15:00:00']);
        $this->sessionOf($rani, '2026-10-08', ['start_time' => '10:00:00', 'end_time' => '14:00:00', 'quota' => 15, 'booked_count' => 3]);
        $this->sessionOf($rani, '2026-10-14'); // minggu berikutnya: tidak tampil

        $grid = $this->gridOf($this->get(route('schedules.index'))->assertOk()->getContent());

        $this->assertStringContainsString('<caption class="visually-hidden">Jadwal praktik dokter 5 Oktober – 11 Oktober 2026</caption>', $grid);
        $this->assertSame(8, substr_count($grid, '<th scope="col"'), 'Satu kolom dokter dan tujuh kolom hari.');
        $this->assertStringContainsString('<time datetime="2026-10-05">Sen 5</time>', $grid);
        $this->assertStringContainsString('<time datetime="2026-10-11">Min 11</time>', $grid);
        $this->assertSame(2, substr_count($grid, '<th scope="row">'));
        $this->assertLessThan(strpos($grid, 'dr. Rani Oktaviani'), strpos($grid, 'dr. Laras Wulandari, Sp.A'), 'Urut sort_order.');
        $this->assertStringContainsString('09.00–12.00', $grid);
        $this->assertStringContainsString('13.00–15.00', $grid);
        $this->assertStringContainsString('10.00–14.00', $grid);
        $this->assertStringContainsString('Sisa 12', $grid);
        $this->assertStringContainsString('<span class="visually-hidden">Tersedia, </span>', $grid);
        $this->assertStringContainsString('tidak ada praktik', $grid);
        $this->assertSame(3, substr_count($grid, 'class="week-cell-item'), 'Tiga sesi minggu ini.');
    }

    public function test_the_stacked_list_has_a_heading_and_a_table_per_day_with_sessions(): void
    {
        $doctor = $this->createDoctor(['name' => 'Laras Wulandari', 'title_prefix' => 'dr.', 'title_suffix' => 'Sp.A']);
        $this->sessionOf($doctor, '2026-10-06');
        $this->sessionOf($doctor, '2026-10-08');

        $html = $this->get(route('schedules.index'))->assertOk()->getContent();
        $stack = substr($html, (int) strpos($html, 'data-week-stack'));

        $this->assertStringContainsString('<h2 class="h5 mb-2" id="hari-2026-10-06">Selasa, 6 Oktober 2026</h2>', $stack);
        $this->assertStringContainsString('<h2 class="h5 mb-2" id="hari-2026-10-08">Kamis, 8 Oktober 2026</h2>', $stack);
        $this->assertStringNotContainsString('id="hari-2026-10-07"', $stack);
        $this->assertSame(2, substr_count($stack, '<table'));
        $this->assertStringContainsString('table-cards', $stack);
        $this->assertStringContainsString('dr. Laras Wulandari, Sp.A', $this->rowFor($stack, '2026-10-06'));
    }

    public function test_sessions_that_ended_earlier_today_are_labelled_as_past(): void
    {
        $this->travelTo('2026-10-05 13:00:00');
        $this->markHorizonGenerated();
        $doctor = $this->createDoctor();
        $this->sessionOf($doctor, '2026-10-05'); // 09.00-12.00 sudah berakhir
        $this->sessionOf($doctor, '2026-10-05', ['start_time' => '16:00:00', 'end_time' => '19:00:00']);

        $grid = $this->gridOf($this->get(route('schedules.index'))->assertOk()->getContent());

        $this->assertStringContainsString('data-availability="past"', $grid);
        $this->assertStringContainsString('Sudah lewat', $grid);
        $this->assertStringContainsString('Sisa 20', $grid);
    }

    public function test_leave_and_holiday_cells_show_their_label_and_are_never_links(): void
    {
        $this->fakeRegistrationRoute();
        $doctor = $this->createDoctor();
        $leave = $this->createException(['doctor_id' => $doctor->id, 'type' => ExceptionType::DoctorLeave, 'start_date' => '2026-10-07', 'end_date' => '2026-10-07']);
        $holiday = $this->createException(['doctor_id' => null, 'type' => ExceptionType::NationalHoliday, 'start_date' => '2026-10-08', 'end_date' => '2026-10-08']);
        $this->sessionOf($doctor, '2026-10-07', ['status' => SessionStatus::Cancelled, 'schedule_exception_id' => $leave->id]);
        $this->sessionOf($doctor, '2026-10-08', ['status' => SessionStatus::Cancelled, 'schedule_exception_id' => $holiday->id]);
        $open = $this->sessionOf($doctor, '2026-10-09');

        $grid = $this->gridOf($this->get(route('schedules.index'))->assertOk()->getContent());

        $this->assertStringContainsString('data-availability="leave"', $grid);
        $this->assertStringContainsString('data-availability="holiday"', $grid);
        $this->assertSame(1, substr_count($grid, 'week-cell-link'), 'Hanya sel yang dapat didaftar berupa tautan.');
        $this->assertStringContainsString('href="'.route('patient.appointments.create', ['sesi' => $open->id]).'"', $grid);
    }

    public function test_no_cell_is_a_register_link_while_the_registration_route_does_not_exist(): void
    {
        if (Route::has('patient.appointments.create')) {
            $this->markTestSkipped('Route patient.appointments.create sudah ada (fase 6): tautan diuji di tes fase 6.');
        }

        $doctor = $this->createDoctor();
        $this->sessionOf($doctor, '2026-10-07');

        $html = $this->get(route('schedules.index'))->assertOk()->getContent();

        $this->assertStringNotContainsString('week-cell-link', $html);
        $this->assertStringNotContainsString('?sesi=', $html);
    }

    // --- navigasi minggu dan batas jendela (Review Focus 1, F3-R24) -----------------------------

    public function test_the_previous_week_is_disabled_on_the_first_week_and_the_next_week_is_a_link(): void
    {
        $html = $this->get(route('schedules.index'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#<span class="btn btn-outline-secondary disabled" aria-disabled="true">[^<]*<i[^>]*></i> Minggu sebelumnya</span>#', $html);
        $this->assertStringContainsString('href="'.route('schedules.index', ['minggu' => '2026-10-12']).'" rel="next"', $html);
    }

    public function test_navigation_links_keep_the_clinic_and_doctor_filters(): void
    {
        $clinic = $this->createClinic(['name' => 'Poliklinik Anak']);
        $doctor = $this->createDoctor(['name' => 'Laras Wulandari'], $clinic);
        $this->sessionOf($doctor, '2026-10-14');

        $html = $this->get(route('schedules.index', ['minggu' => '2026-10-14', 'poli' => $clinic->slug, 'dokter' => $doctor->slug]))->assertOk()->getContent();

        $this->assertStringContainsString('Minggu 12 Oktober – 18 Oktober 2026', $html);
        $this->assertStringContainsString('minggu=2026-10-05', $html);
        $this->assertStringContainsString('minggu=2026-10-19', $html);
        $this->assertStringContainsString('dokter='.$doctor->slug, $html);
    }

    public function test_a_date_in_the_middle_of_a_week_is_normalised_to_monday(): void
    {
        $this->get(route('schedules.index', ['minggu' => '2026-10-14']))
            ->assertOk()
            ->assertSee('Minggu 12 Oktober – 18 Oktober 2026')
            ->assertDontSee('data-schedule-notice', false);
    }

    public function test_a_week_before_the_current_one_falls_back_to_this_week_with_a_message(): void
    {
        $this->get(route('schedules.index', ['minggu' => '2026-09-01']))
            ->assertOk()
            ->assertSee('Minggu 5 Oktober – 11 Oktober 2026')
            ->assertSee('Menampilkan minggu ini.');
    }

    public function test_a_week_after_the_window_falls_back_to_the_last_valid_week_with_a_message(): void
    {
        $html = $this->get(route('schedules.index', ['minggu' => '2027-01-04']))
            ->assertOk()
            ->assertSee('Minggu 2 November – 8 November 2026')
            ->assertSee('Jadwal hanya tersedia sampai 4 November 2026.')
            ->getContent();

        $this->assertMatchesRegularExpression('#<span class="btn btn-outline-secondary disabled" aria-disabled="true">Minggu berikutnya#', $html);
    }

    public function test_the_week_that_contains_the_last_day_of_the_window_is_the_last_one_offered(): void
    {
        $this->get(route('schedules.index', ['minggu' => '2026-11-02']))
            ->assertOk()
            ->assertSee('Minggu 2 November – 8 November 2026')
            ->assertDontSee('data-schedule-notice', false);

        $this->get(route('schedules.index', ['minggu' => '2026-11-09']))
            ->assertOk()
            ->assertSee('Minggu 2 November – 8 November 2026')
            ->assertSee('Jadwal hanya tersedia sampai 4 November 2026.');
    }

    // --- parameter tidak valid (VAL-07) dan filter ---------------------------------------------

    /** @return array<string, array{array<string, mixed>}> */
    public static function invalidFilters(): array
    {
        return [
            'minggu bukan tanggal' => [['minggu' => 'xx']],
            'minggu array' => [['minggu' => ['2026-10-05']]],
            'minggu mustahil' => [['minggu' => '2026-02-31']],
            'poli array' => [['poli' => ['x']]],
            'dokter array' => [['dokter' => ['x']]],
            'dokter terlalu panjang' => [['dokter' => str_repeat('a', 300)]],
            'poli dan dokter tak dikenal' => [['poli' => 'tidak-ada', 'dokter' => 'juga-tidak-ada']],
        ];
    }

    /** @param  array<string, mixed>  $query */
    #[DataProvider('invalidFilters')]
    public function test_val_07_invalid_parameters_never_cause_an_error(array $query): void
    {
        $this->get(route('schedules.index', $query))->assertOk()->assertSee('Minggu 5 Oktober – 11 Oktober 2026');
    }

    public function test_an_invalid_week_format_gets_a_message(): void
    {
        $this->get(route('schedules.index', ['minggu' => 'xx']))
            ->assertOk()
            ->assertSee('Format minggu tidak valid. Menampilkan minggu ini.');
    }

    public function test_unknown_clinic_and_doctor_slugs_are_ignored_with_inline_messages(): void
    {
        $doctor = $this->createDoctor(['name' => 'Laras Wulandari']);
        $this->sessionOf($doctor, '2026-10-06');

        $this->get(route('schedules.index', ['poli' => 'tidak-ada', 'dokter' => 'juga-tidak-ada']))
            ->assertOk()
            ->assertSee('Poliklinik tidak dikenal. Filter ini diabaikan.')
            ->assertSee('Dokter tidak dikenal. Filter ini diabaikan.')
            ->assertSee('Laras Wulandari');
    }

    public function test_the_clinic_filter_from_the_service_page_and_the_doctor_filter_narrow_the_grid(): void
    {
        $anak = $this->createClinic(['name' => 'Poliklinik Anak']);
        $mata = $this->createClinic(['name' => 'Poliklinik Mata']);
        $laras = $this->createDoctor(['name' => 'Laras Wulandari'], $anak);
        $intan = $this->createDoctor(['name' => 'Intan Permatasari'], $mata);
        $this->sessionOf($laras, '2026-10-06');
        $this->sessionOf($intan, '2026-10-06');

        $byClinic = $this->gridOf($this->get(route('schedules.index', ['poli' => $anak->slug]))->assertOk()->getContent());
        $byDoctor = $this->gridOf($this->get(route('schedules.index', ['dokter' => $intan->slug]))->assertOk()->getContent());

        $this->assertStringContainsString('Laras Wulandari', $byClinic);
        $this->assertStringNotContainsString('Intan Permatasari', $byClinic);
        $this->assertStringContainsString('Intan Permatasari', $byDoctor);
        $this->assertStringNotContainsString('Laras Wulandari', $byDoctor);
    }

    public function test_filter_selects_keep_their_values_and_offer_only_visible_doctors_and_active_clinics(): void
    {
        $poli = $this->createClinic(['name' => 'Poliklinik Anak']);
        $this->createClinic(['name' => 'Poliklinik Tutup', 'is_active' => false]);
        $visible = $this->createDoctor(['name' => 'Laras Wulandari', 'title_prefix' => 'dr.', 'title_suffix' => null], $poli);
        $this->createDoctor(['name' => 'Nonaktif Satu', 'status' => DoctorStatus::Inactive]);

        $html = $this->get(route('schedules.index', ['poli' => $poli->slug, 'dokter' => $visible->slug]))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<option value="'.preg_quote($poli->slug, '/').'"\s+selected>/', $html);
        $this->assertMatchesRegularExpression('/<option value="'.preg_quote($visible->slug, '/').'"\s+selected>/', $html);
        $this->assertStringNotContainsString('Poliklinik Tutup', $html);
        $this->assertStringNotContainsString('Nonaktif Satu', $html);
    }

    public function test_an_empty_week_offers_the_next_week_and_a_reset(): void
    {
        $doctor = $this->createDoctor();
        $this->sessionOf($doctor, '2026-10-14');

        $this->get(route('schedules.index'))
            ->assertOk()
            ->assertSee('Tidak ada jadwal praktik pada pekan ini')
            ->assertSee('Lihat minggu berikutnya')
            ->assertSee('Reset');
    }

    // --- dokter yang tampil (F3-R17) -----------------------------------------------------------

    public function test_sessions_of_doctors_that_are_not_publicly_visible_never_appear(): void
    {
        $inactiveClinic = $this->createClinic(['is_active' => false]);
        $visible = $this->createDoctor(['name' => 'Laras Wulandari']);
        $hidden = [
            $this->createDoctor(['name' => 'Nonaktif Satu', 'status' => DoctorStatus::Inactive]),
            $this->createDoctor(['name' => 'Arsip Dua', 'status' => DoctorStatus::Archived, 'archived_at' => now()->subDays(3)]),
            $this->createDoctor(['name' => 'Poli Mati Tiga'], $inactiveClinic),
        ];
        $this->sessionOf($visible, '2026-10-06');

        foreach ($hidden as $doctor) {
            $this->sessionOf($doctor, '2026-10-06');
        }

        $this->get(route('schedules.index'))
            ->assertOk()
            ->assertSee('Laras Wulandari')
            ->assertDontSee('Nonaktif Satu')
            ->assertDontSee('Arsip Dua')
            ->assertDontSee('Poli Mati Tiga');
    }

    public function test_the_grid_row_header_shows_only_an_active_specialty(): void
    {
        $hidden = $this->createSpecialty(['name' => 'Spesialis Nonaktif Utama', 'is_active' => false]);
        $shown = $this->createSpecialty(['name' => 'Spesialis Aktif Tambahan']);
        $doctor = $this->createDoctor(['name' => 'Laras Wulandari'], null, [$hidden, $shown]);
        $this->sessionOf($doctor, '2026-10-06');

        $grid = $this->gridOf($this->get(route('schedules.index'))->assertOk()->getContent());

        $this->assertStringContainsString('<span class="d-block small text-body-secondary">Spesialis Aktif Tambahan</span>', $grid);
        $this->assertStringNotContainsString('Spesialis Nonaktif Utama', $grid);
    }

    // --- mode ketat ----------------------------------------------------------------------------

    public function test_many_doctors_and_sessions_render_in_strict_mode(): void
    {
        $this->assertTrue(Model::preventsLazyLoading());

        $anak = $this->createSpecialty(['name' => 'Anak']);
        $umum = $this->createSpecialty(['name' => 'Dokter Umum']);

        foreach (range(1, 8) as $number) {
            $doctor = $this->createDoctor(['name' => sprintf('Dokter Uji %02d', $number), 'sort_order' => $number], null, [$anak, $umum]);

            foreach (['2026-10-05', '2026-10-06', '2026-10-08', '2026-10-10'] as $date) {
                $this->sessionOf($doctor, $date);
            }
        }

        $html = $this->get(route('schedules.index'))->assertOk()->getContent();

        $this->assertSame(8, substr_count($this->gridOf($html), '<th scope="row">'));
        $this->assertSame(32, substr_count($this->gridOf($html), 'class="week-cell-item'));
    }

    // --- pembangkitan sesi harian (F3-R9) dan CRD-05 --------------------------------------------

    public function test_visiting_the_page_triggers_the_daily_generation(): void
    {
        $doctor = $this->createDoctor();
        $this->createSchedule(['doctor_id' => $doctor->id, 'clinic_id' => $doctor->clinic_id, 'day_of_week' => 3, 'start_time' => '09:00:00', 'end_time' => '12:00:00', 'valid_from' => '2026-10-05']);
        Cache::forget(PracticeSessionGenerator::markerKey(CarbonImmutable::today()));

        $this->get(route('schedules.index'))->assertOk()->assertSee('09.00–12.00')->assertSee('Sisa 20');

        $this->assertSame(5, PracticeSession::query()->count());
    }

    public function test_crd_05_a_new_template_shows_up_and_deactivating_or_a_leave_removes_or_relabels_it(): void
    {
        $doctor = $this->createDoctor(['name' => 'Rani Oktaviani', 'title_prefix' => 'dr.', 'title_suffix' => null]);
        $service = app(ScheduleChangeService::class);
        $schedule = $this->createSchedule(['doctor_id' => $doctor->id, 'clinic_id' => $doctor->clinic_id, 'day_of_week' => 1, 'start_time' => '09:00:00', 'end_time' => '12:00:00', 'quota' => 15, 'valid_from' => '2026-10-05']);
        $service->applyTemplateCreated($schedule, Actor::system());

        $detail = $this->get(route('doctors.show', $doctor->slug))->assertOk()->getContent();
        $week = $this->get(route('schedules.index'))->assertOk()->getContent();

        $this->assertStringContainsString('15 dari 15', $this->rowFor($detail, '2026-10-05'));
        $this->assertStringContainsString('09.00–12.00', $this->rowFor($week, '2026-10-05'));
        $this->assertStringContainsString('dr. Rani Oktaviani', $this->gridOf($week));

        $exception = $this->createException(['doctor_id' => $doctor->id, 'type' => ExceptionType::DoctorLeave, 'start_date' => '2026-10-12', 'end_date' => '2026-10-12']);
        $service->applyException($exception, null, Actor::system());

        $afterLeave = $this->get(route('doctors.show', $doctor->slug))->assertOk()->getContent();

        $this->assertStringContainsString('data-availability="leave"', $this->rowFor($afterLeave, '2026-10-12'));
        $this->assertStringContainsString('data-availability="available"', $this->rowFor($afterLeave, '2026-10-05'));

        $schedule->is_active = false;
        $before = $schedule->getOriginal();
        $schedule->save();
        $service->applyTemplateChange($schedule, $before, Actor::system());

        $this->get(route('doctors.show', $doctor->slug))->assertOk()->assertSee('Belum ada jadwal praktik dua minggu ke depan');
        $this->get(route('schedules.index'))->assertOk()->assertSee('Tidak ada jadwal praktik pada pekan ini');
    }

    // --- JDW-12: satu sumber jadwal ------------------------------------------------------------

    public function test_jdw_12_detail_and_week_pages_show_exactly_what_the_shared_query_returns(): void
    {
        $doctor = $this->createDoctor(['name' => 'Rani Oktaviani', 'title_prefix' => 'dr.', 'title_suffix' => null]);
        $leave = $this->createException(['doctor_id' => $doctor->id, 'type' => ExceptionType::DoctorLeave, 'start_date' => '2026-10-09', 'end_date' => '2026-10-09']);
        $full = $this->sessionOf($doctor, '2026-10-06', ['quota' => 2]);
        $this->bookAppointment($full, AppointmentStatus::Confirmed);
        $this->bookAppointment($full, AppointmentStatus::Pending);
        $this->sessionOf($doctor, '2026-10-07', ['quota' => 15, 'booked_count' => 4]);
        $this->sessionOf($doctor, '2026-10-08', ['status' => SessionStatus::Closed]);
        $this->sessionOf($doctor, '2026-10-09', ['status' => SessionStatus::Cancelled, 'schedule_exception_id' => $leave->id]);
        $this->sessionOf($doctor, '2026-10-10', ['start_time' => '13:00:00', 'end_time' => '16:30:00']);

        $expected = app(DoctorAvailabilityQuery::class)->sessions(new AvailabilityFilter(
            doctorId: $doctor->id,
            from: CarbonImmutable::today(),
            to: CarbonImmutable::today()->addDays(13),
        ));
        $this->assertCount(5, $expected);

        $detail = $this->get(route('doctors.show', $doctor->slug))->assertOk()->getContent();
        $week = $this->get(route('schedules.index'))->assertOk()->getContent();
        $grid = $this->gridOf($week);

        foreach ($expected as $item) {
            $date = $item->session->session_date->toDateString();
            $detailRow = $this->rowFor($detail, $date);
            $weekRow = $this->rowFor(substr($week, (int) strpos($week, 'data-week-stack')), $date);

            foreach ([$detailRow, $weekRow] as $row) {
                $this->assertStringContainsString('data-availability="'.$item->availability->value.'"', $row, $date);
                $this->assertStringContainsString($item->session->time_range, $row, $date);

                if (! in_array($item->availability, [Availability::Leave, Availability::Holiday, Availability::Cancelled], true)) {
                    $this->assertStringContainsString($item->remaining.' dari '.$item->session->quota, $row, $date);
                }
            }

            $this->assertStringContainsString('data-availability="'.$item->availability->value.'"', $grid);

            if ($item->availability === Availability::Available) {
                $this->assertStringContainsString('Sisa '.$item->remaining, $grid);
            }
        }

        $this->assertSame(['full', 'available', 'closed', 'leave', 'available'], $expected->map(fn ($item): string => $item->availability->value)->all());
    }

    public function test_jdw_12_both_public_controllers_use_the_shared_query_and_the_daily_generator(): void
    {
        foreach ([DoctorController::class, ScheduleController::class] as $controller) {
            $source = (string) file_get_contents((new ReflectionClass($controller))->getFileName());

            $this->assertStringContainsString('DoctorAvailabilityQuery', $source, $controller);
            $this->assertStringContainsString('ensureDailyHorizon()', $source, $controller);
            $this->assertStringNotContainsString('PracticeSession::', $source, $controller);
            $this->assertStringNotContainsString('DB::', $source, $controller);
        }
    }

    // --- akses publik (AKS #8) dan CSP ---------------------------------------------------------

    public function test_the_page_is_open_to_every_actor(): void
    {
        $this->get(route('schedules.index'))->assertOk();
        $this->actingAs(User::factory()->patient()->create())->get(route('schedules.index'))->assertOk();

        foreach (['content-admin', 'registration-officer', 'super-admin'] as $role) {
            $this->actingAs($this->createStaff($role))->get(route('schedules.index'))->assertOk();
        }
    }

    public function test_the_page_has_no_inline_handlers(): void
    {
        $doctor = $this->createDoctor();
        $this->sessionOf($doctor, '2026-10-07');

        $html = $this->get(route('schedules.index'))->assertOk()->getContent();

        $this->assertSame(0, preg_match('/\son[a-z]+\s*=/i', $html));
        $this->assertStringNotContainsString('onerror', $html);
        $this->assertStringNotContainsString('javascript:', $html);
    }
}
