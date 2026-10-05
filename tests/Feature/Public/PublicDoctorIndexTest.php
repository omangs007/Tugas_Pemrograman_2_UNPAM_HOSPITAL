<?php

namespace Tests\Feature\Public;

use App\Enums\DoctorStatus;
use App\Enums\ExceptionType;
use App\Enums\SessionStatus;
use App\Http\Controllers\Public\DoctorController;
use App\Models\Doctor;
use App\Models\PracticeSession;
use App\Models\User;
use App\Services\Scheduling\PracticeSessionGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InteractsWithMasterData;
use Tests\Support\InteractsWithSchedules;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

/**
 * Halaman #6 `doctors.index` (05 hal. 6, 07 W-03, F3-R9/R17/R18/R25): direktori dokter yang tampil di publik,
 * filter `q`/`spesialisasi`/`poli`/`tanggal`, label ketersediaan dari DoctorAvailabilityQuery, dan pemicu
 * pembangkitan sesi harian. Mencakup CRD-01/CRD-03 (bagian #6), VAL-07, dan EMP-02.
 */
class PublicDoctorIndexTest extends TestCase
{
    use InteractsWithMasterData;
    use InteractsWithSchedules;
    use InteractsWithStaff;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->travelTo('2026-10-05 07:00:00'); // Senin
        $this->markHorizonGenerated();
    }

    /** Menandai pembangkitan harian sudah berjalan agar sesi buatan tes tidak bertambah oleh templat pabrik. */
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

    private function fakeRegistrationRoute(): void
    {
        if (! Route::has('patient.appointments.create')) {
            Route::middleware('web')->get('/_uji/pasien/pendaftaran/baru', fn () => 'x')->name('patient.appointments.create');
            Route::getRoutes()->refreshNameLookups();
        }
    }

    // --- judul, SEO, breadcrumb (F3-R18) -------------------------------------------------------

    public function test_index_renders_the_static_title_subtitle_meta_description_and_breadcrumb(): void
    {
        $this->createDoctor();

        $response = $this->get(route('doctors.index'))->assertOk();

        $response->assertSee('<title>Cari Dokter — '.config('app.name').'</title>', false)
            ->assertSee('Cari berdasarkan nama, spesialisasi, poliklinik, atau tanggal praktik')
            ->assertSee('<meta name="description" content="Cari dokter UNPAM HOSPITAL berdasarkan nama, spesialisasi, poliklinik, dan tanggal.">', false)
            ->assertSeeInOrder(['aria-label="Breadcrumb"', 'Beranda', 'Dokter'], false)
            ->assertSee('<li class="breadcrumb-item active" aria-current="page">Dokter</li>', false)
            ->assertSee('<link rel="canonical" href="'.route('doctors.index').'">', false)
            ->assertDontSee('<meta name="robots" content="noindex,nofollow">', false);
        $this->assertSame(1, substr_count($response->getContent(), '<h1'));
    }

    public function test_the_page_is_marked_noindex_only_when_there_is_no_result(): void
    {
        $this->get(route('doctors.index'))->assertOk()->assertSee('<meta name="robots" content="noindex,nofollow">', false);

        $this->createDoctor();

        $this->get(route('doctors.index'))->assertOk()->assertDontSee('<meta name="robots" content="noindex,nofollow">', false);
    }

    // --- dokter yang tampil (F3-R17, CRD-03 bagian #6) ------------------------------------------

    public function test_only_publicly_visible_doctors_are_listed(): void
    {
        $clinic = $this->createClinic();
        $inactiveClinic = $this->createClinic(['is_active' => false]);
        $anak = $this->createSpecialty(['name' => 'Anak']);

        $this->createDoctor(['name' => 'Laras Wulandari', 'title_prefix' => 'dr.', 'title_suffix' => 'Sp.A'], $clinic, [$anak]);
        $this->createDoctor(['name' => 'Nonaktif Satu', 'title_prefix' => 'dr.', 'title_suffix' => null, 'status' => DoctorStatus::Inactive], $clinic);
        $this->createDoctor(['name' => 'Arsip Dua', 'title_prefix' => 'dr.', 'title_suffix' => null, 'status' => DoctorStatus::Archived, 'archived_at' => now()->subDays(3)], $clinic);
        $this->createDoctor(['name' => 'Poli Mati Tiga', 'title_prefix' => 'dr.', 'title_suffix' => null], $inactiveClinic);

        $this->get(route('doctors.index'))
            ->assertOk()
            ->assertSee('dr. Laras Wulandari, Sp.A')
            ->assertSee('Menampilkan 1 dari 1 dokter')
            ->assertDontSee('Nonaktif Satu')
            ->assertDontSee('Arsip Dua')
            ->assertDontSee('Poli Mati Tiga');
    }

    public function test_a_new_doctor_appears_and_disappears_when_deactivated(): void
    {
        $doctor = $this->createDoctor(['name' => 'Rani Oktaviani', 'title_prefix' => 'dr.', 'title_suffix' => null]);

        $this->get(route('doctors.index'))->assertOk()->assertSee('dr. Rani Oktaviani');

        $doctor->forceFill(['status' => DoctorStatus::Inactive])->save();

        $this->get(route('doctors.index'))->assertOk()->assertDontSee('Rani Oktaviani');
    }

    public function test_the_page_is_identical_for_guests_patients_and_staff(): void
    {
        $this->createDoctor(['name' => 'Rani Oktaviani', 'title_prefix' => 'dr.', 'title_suffix' => null]);

        $this->get(route('doctors.index'))->assertOk()->assertSee('dr. Rani Oktaviani');
        $this->actingAs(User::factory()->patient()->create())->get(route('doctors.index'))->assertOk()->assertSee('dr. Rani Oktaviani');

        foreach (['content-admin', 'registration-officer', 'super-admin'] as $role) {
            $this->actingAs($this->createStaff($role))->get(route('doctors.index'))->assertOk()->assertSee('dr. Rani Oktaviani');
        }
    }

    public function test_inactive_specialties_are_not_shown_on_the_cards(): void
    {
        $hidden = $this->createSpecialty(['name' => 'Spesialis Nonaktif Utama', 'is_active' => false]);
        $shown = $this->createSpecialty(['name' => 'Spesialis Aktif Tambahan']);
        $this->createDoctor(['name' => 'Dokter Dua Spesialis'], null, [$hidden, $shown]);

        $html = $this->get(route('doctors.index'))->assertOk()->assertSee('Dokter Dua Spesialis')->getContent();

        $this->assertStringContainsString('<span class="badge text-bg-light border">Spesialis Aktif Tambahan</span>', $html);
        $this->assertStringNotContainsString('Spesialis Nonaktif Utama', $html);
    }

    // --- filter nama, spesialisasi, poliklinik (CRD-01) ----------------------------------------

    public function test_filters_by_name_specialty_and_clinic(): void
    {
        $anak = $this->createSpecialty(['name' => 'Anak']);
        $mata = $this->createSpecialty(['name' => 'Mata']);
        $poliAnak = $this->createClinic(['name' => 'Poliklinik Anak']);
        $poliMata = $this->createClinic(['name' => 'Poliklinik Mata']);
        $this->createDoctor(['name' => 'Laras Wulandari'], $poliAnak, [$anak]);
        $this->createDoctor(['name' => 'Intan Permatasari'], $poliMata, [$mata]);

        $this->get(route('doctors.index', ['spesialisasi' => $anak->slug]))->assertOk()->assertSee('Laras Wulandari')->assertDontSee('Intan Permatasari');
        $this->get(route('doctors.index', ['poli' => $poliMata->slug]))->assertOk()->assertSee('Intan Permatasari')->assertDontSee('Laras Wulandari');
        $this->get(route('doctors.index', ['q' => 'Intan']))->assertOk()->assertSee('Intan Permatasari')->assertDontSee('Laras Wulandari');
        $this->get(route('doctors.index', ['q' => 'Mata']))->assertOk()->assertSee('Intan Permatasari')->assertDontSee('Laras Wulandari');
        $this->get(route('doctors.index', ['spesialisasi' => $anak->slug, 'poli' => $poliMata->slug]))->assertOk()->assertSee('Tidak ada dokter yang cocok');
    }

    public function test_filter_values_are_kept_in_the_form(): void
    {
        $anak = $this->createSpecialty(['name' => 'Anak']);
        $poli = $this->createClinic(['name' => 'Poliklinik Anak']);
        $this->createDoctor(['name' => 'Laras Wulandari'], $poli, [$anak]);

        $html = $this->get(route('doctors.index', ['q' => 'Laras', 'spesialisasi' => $anak->slug, 'poli' => $poli->slug, 'tanggal' => '2026-10-07']))->assertOk()->getContent();

        $this->assertStringContainsString('value="Laras"', $html);
        $this->assertMatchesRegularExpression('/<option value="'.preg_quote($anak->slug, '/').'"\s+selected>/', $html);
        $this->assertMatchesRegularExpression('/<option value="'.preg_quote($poli->slug, '/').'"\s+selected>/', $html);
        $this->assertStringContainsString('value="2026-10-07"', $html);
        $this->assertStringContainsString('Filter (4 aktif)', $html);
    }

    public function test_a_new_specialty_appears_in_the_filter_and_a_deactivated_one_disappears(): void
    {
        $new = $this->createSpecialty(['name' => 'Contoh Baru']);
        $this->createSpecialty(['name' => 'Spesialis Nonaktif', 'is_active' => false]);

        $this->get(route('doctors.index'))
            ->assertOk()
            ->assertSee('<option value="'.$new->slug.'"', false)
            ->assertSee('Contoh Baru')
            ->assertDontSee('Spesialis Nonaktif');

        $new->update(['is_active' => false]);

        $this->get(route('doctors.index'))->assertOk()->assertDontSee('Contoh Baru');
    }

    public function test_only_active_polyclinics_are_offered_in_the_clinic_filter(): void
    {
        $this->createClinic(['name' => 'Poliklinik Aktif']);
        $this->createClinic(['name' => 'Poliklinik Tutup', 'is_active' => false]);
        $this->createClinic(['name' => 'Laboratorium Uji'])->forceFill(['type' => 'penunjang', 'is_bookable' => false])->save();

        $this->get(route('doctors.index'))
            ->assertOk()
            ->assertSee('Poliklinik Aktif')
            ->assertDontSee('Poliklinik Tutup')
            ->assertDontSee('Laboratorium Uji');
    }

    // --- filter tanggal dan label ketersediaan --------------------------------------------------

    public function test_date_filter_keeps_doctors_with_a_session_that_day_including_leave_and_shows_their_status(): void
    {
        $available = $this->createDoctor(['name' => 'Rani Oktaviani', 'title_prefix' => 'dr.', 'title_suffix' => null]);
        $onLeave = $this->createDoctor(['name' => 'Teguh Wicaksono', 'title_prefix' => 'dr.', 'title_suffix' => null]);
        $other = $this->createDoctor(['name' => 'Ayu Larasati', 'title_prefix' => 'dr.', 'title_suffix' => null]);
        $this->sessionOf($available, '2026-10-07');
        $exception = $this->createException(['doctor_id' => $onLeave->id, 'type' => ExceptionType::DoctorLeave, 'start_date' => '2026-10-07', 'end_date' => '2026-10-07']);
        $this->sessionOf($onLeave, '2026-10-07', ['status' => SessionStatus::Cancelled, 'schedule_exception_id' => $exception->id, 'status_reason' => 'Cuti tahunan']);
        $this->sessionOf($other, '2026-10-08');

        $this->get(route('doctors.index', ['tanggal' => '2026-10-07']))
            ->assertOk()
            ->assertSee('dr. Rani Oktaviani')
            ->assertSee('Tersedia · sisa 20')
            ->assertSee('Jadwal terdekat: <time datetime="2026-10-07">Rabu, 7 Oktober 2026</time> · 09.00–12.00', false)
            ->assertSee('dr. Teguh Wicaksono')
            ->assertSee('data-availability="leave"', false)
            ->assertDontSee('Ayu Larasati');
    }

    public function test_without_a_date_cards_show_the_nearest_session_that_is_not_a_cancellation(): void
    {
        $doctor = $this->createDoctor(['name' => 'Rani Oktaviani', 'title_prefix' => 'dr.', 'title_suffix' => null]);
        $exception = $this->createException(['doctor_id' => $doctor->id, 'type' => ExceptionType::DoctorLeave, 'start_date' => '2026-10-06', 'end_date' => '2026-10-06']);
        $this->sessionOf($doctor, '2026-10-06', ['status' => SessionStatus::Cancelled, 'schedule_exception_id' => $exception->id]);
        $this->sessionOf($doctor, '2026-10-08', ['start_time' => '13:00:00', 'end_time' => '15:00:00']);

        $this->get(route('doctors.index'))
            ->assertOk()
            ->assertSee('Jadwal terdekat: <time datetime="2026-10-08">Kamis, 8 Oktober 2026</time> · 13.00–15.00', false)
            ->assertDontSee('Selasa, 6 Oktober 2026');
    }

    public function test_a_doctor_without_upcoming_sessions_has_a_plain_card(): void
    {
        $this->createDoctor(['name' => 'Rani Oktaviani', 'title_prefix' => 'dr.', 'title_suffix' => null]);

        $this->get(route('doctors.index'))->assertOk()->assertSee('dr. Rani Oktaviani')->assertDontSee('Jadwal terdekat');
    }

    public function test_register_button_on_the_card_needs_the_route_and_an_online_bookable_session(): void
    {
        $this->fakeRegistrationRoute();
        $doctor = $this->createDoctor(['name' => 'Rani Oktaviani', 'title_prefix' => 'dr.', 'title_suffix' => null]);
        $session = $this->sessionOf($doctor, '2026-10-07');

        $this->get(route('doctors.index'))
            ->assertOk()
            ->assertSee('href="'.route('patient.appointments.create', ['sesi' => $session->id]).'"', false);

        $session->forceFill(['status' => SessionStatus::Closed])->save();

        $this->get(route('doctors.index'))->assertOk()->assertDontSee('?sesi=', false);
    }

    // --- parameter tidak valid (VAL-07) dan tanggal di luar jendela -----------------------------

    /** @return array<string, array{array<string, mixed>}> */
    public static function invalidFilters(): array
    {
        return [
            'tanggal tidak valid, spesialisasi tak dikenal, poli array' => [['tanggal' => 'abc', 'spesialisasi' => 'tidak-ada', 'poli' => ['x']]],
            'q array' => [['q' => ['x']]],
            'tanggal array' => [['tanggal' => ['2026-10-07']]],
            'tanggal mustahil' => [['tanggal' => '2026-13-45']],
            'tanggal tanpa nol' => [['tanggal' => '2026-10-7']],
            'halaman bukan angka' => [['page' => 'abc']],
            'halaman negatif' => [['page' => '-3']],
            'q terlalu panjang' => [['q' => str_repeat('a', 300)]],
            'poli tak dikenal' => [['poli' => 'tidak-ada']],
        ];
    }

    /** @param  array<string, mixed>  $query */
    #[DataProvider('invalidFilters')]
    public function test_val_07_invalid_parameters_are_ignored_and_never_cause_an_error(array $query): void
    {
        $this->createDoctor(['name' => 'Rani Oktaviani', 'title_prefix' => 'dr.', 'title_suffix' => null]);

        $this->get(route('doctors.index', $query))
            ->assertOk()
            ->assertSee('dr. Rani Oktaviani');
    }

    public function test_an_invalid_date_format_gets_an_inline_message_and_does_not_filter(): void
    {
        $this->createDoctor(['name' => 'Rani Oktaviani', 'title_prefix' => 'dr.', 'title_suffix' => null]);

        $this->get(route('doctors.index', ['tanggal' => 'abc']))
            ->assertOk()
            ->assertSee('Format tanggal tidak valid. Gunakan format TTTT-BB-HH.')
            ->assertSee('dr. Rani Oktaviani');
    }

    public function test_a_date_outside_the_booking_window_gets_an_inline_message_and_does_not_filter(): void
    {
        $this->createDoctor(['name' => 'Rani Oktaviani', 'title_prefix' => 'dr.', 'title_suffix' => null]);

        $this->get(route('doctors.index', ['tanggal' => '2026-11-05']))
            ->assertOk()
            ->assertSee('Tanggal praktik maksimal 30 hari ke depan.')
            ->assertSee('dr. Rani Oktaviani');

        $this->get(route('doctors.index', ['tanggal' => '2026-10-04']))
            ->assertOk()
            ->assertSee('Tanggal praktik tidak boleh sebelum hari ini.')
            ->assertSee('dr. Rani Oktaviani');
    }

    public function test_the_last_day_of_the_window_is_accepted_and_the_next_day_is_not(): void
    {
        $doctor = $this->createDoctor(['name' => 'Rani Oktaviani', 'title_prefix' => 'dr.', 'title_suffix' => null]);
        $this->createDoctor(['name' => 'Ayu Larasati', 'title_prefix' => 'dr.', 'title_suffix' => null]);
        $this->sessionOf($doctor, '2026-11-04');

        $this->get(route('doctors.index', ['tanggal' => '2026-11-04']))
            ->assertOk()
            ->assertSee('dr. Rani Oktaviani')
            ->assertDontSee('Ayu Larasati')
            ->assertDontSee('Tanggal praktik maksimal');

        $this->get(route('doctors.index', ['tanggal' => '2026-11-05']))->assertOk()->assertSee('Ayu Larasati')->assertSee('Tanggal praktik maksimal 30 hari ke depan.');
    }

    public function test_unknown_specialty_and_clinic_slugs_are_ignored_with_an_inline_message(): void
    {
        $this->createDoctor(['name' => 'Rani Oktaviani', 'title_prefix' => 'dr.', 'title_suffix' => null]);

        $this->get(route('doctors.index', ['spesialisasi' => 'tidak-ada', 'poli' => 'juga-tidak-ada']))
            ->assertOk()
            ->assertSee('Spesialisasi tidak dikenal. Filter ini diabaikan.')
            ->assertSee('Poliklinik tidak dikenal. Filter ini diabaikan.')
            ->assertSee('dr. Rani Oktaviani');
    }

    // --- kosong (EMP-02) dan halaman di luar jangkauan -----------------------------------------

    public function test_emp_02_a_search_without_result_explains_and_keeps_the_filters(): void
    {
        $this->createDoctor(['name' => 'Rani Oktaviani', 'title_prefix' => 'dr.', 'title_suffix' => null]);

        $html = $this->get(route('doctors.index', ['q' => 'zzzxqw', 'tanggal' => '2026-10-20']))
            ->assertOk()
            ->assertSee('Tidak ada dokter yang cocok')
            ->assertSee('Coba ubah kata kunci atau pilihan filter Anda.')
            ->assertSee('href="'.route('doctors.index').'"', false)
            ->assertSee('<meta name="robots" content="noindex,nofollow">', false)
            ->getContent();

        $this->assertStringContainsString('value="zzzxqw"', $html);
        $this->assertStringContainsString('value="2026-10-20"', $html);
        $this->assertStringContainsString('Reset filter', $html);
    }

    public function test_without_filters_and_without_doctors_a_neutral_empty_state_is_shown(): void
    {
        $this->get(route('doctors.index'))
            ->assertOk()
            ->assertSee('Belum ada dokter yang tampil')
            ->assertDontSee('Tidak ada dokter yang cocok');
    }

    public function test_a_page_beyond_the_last_one_offers_a_link_back_to_page_one(): void
    {
        $this->createDoctor(['name' => 'Rani Oktaviani', 'title_prefix' => 'dr.', 'title_suffix' => null]);

        $this->get(route('doctors.index', ['page' => 99]))
            ->assertOk()
            ->assertSee('Halaman yang diminta tidak berisi data.')
            ->assertSee('Kembali ke halaman 1');
    }

    // --- paginasi dan mode ketat ---------------------------------------------------------------

    public function test_twelve_doctors_per_page_with_strict_mode_and_many_sessions(): void
    {
        $this->assertTrue(Model::preventsLazyLoading());

        $anak = $this->createSpecialty(['name' => 'Anak']);
        $umum = $this->createSpecialty(['name' => 'Dokter Umum']);

        foreach (range(1, 13) as $number) {
            $doctor = $this->createDoctor(['name' => sprintf('Dokter Uji %02d', $number), 'title_prefix' => 'dr.', 'title_suffix' => null, 'sort_order' => $number], null, [$anak, $umum]);

            foreach (['2026-10-06', '2026-10-08', '2026-10-13'] as $date) {
                $this->sessionOf($doctor, $date);
            }
        }

        $first = $this->get(route('doctors.index'))->assertOk();
        $second = $this->get(route('doctors.index', ['page' => 2]))->assertOk();

        $this->assertSame(12, substr_count($first->getContent(), 'doctor-card"'));
        $this->assertSame(1, substr_count($second->getContent(), 'doctor-card"'));
        $first->assertSee('Menampilkan 12 dari 13 dokter')->assertSee('dr. Dokter Uji 12')->assertDontSee('dr. Dokter Uji 13');
        $second->assertSee('Menampilkan 1 dari 13 dokter')->assertSee('dr. Dokter Uji 13');
    }

    public function test_pagination_links_keep_the_active_filters(): void
    {
        $anak = $this->createSpecialty(['name' => 'Anak']);

        foreach (range(1, 13) as $number) {
            $this->createDoctor(['name' => sprintf('Dokter Uji %02d', $number), 'sort_order' => $number], null, [$anak]);
        }

        $this->get(route('doctors.index', ['spesialisasi' => $anak->slug]))
            ->assertOk()
            ->assertSee('spesialisasi='.$anak->slug, false)
            ->assertSee('page=2', false);
    }

    // --- pembangkitan sesi harian (F3-R8, F3-R9) -----------------------------------------------

    public function test_visiting_the_page_triggers_the_daily_generation_once(): void
    {
        $doctor = $this->createDoctor(['name' => 'Rani Oktaviani', 'title_prefix' => 'dr.', 'title_suffix' => null]);
        $this->createSchedule(['doctor_id' => $doctor->id, 'clinic_id' => $doctor->clinic_id, 'day_of_week' => 3, 'valid_from' => '2026-10-05']);
        Cache::forget(PracticeSessionGenerator::markerKey(CarbonImmutable::today()));

        $this->assertSame(0, PracticeSession::query()->count());

        $this->get(route('doctors.index'))->assertOk();

        $this->assertSame(5, PracticeSession::query()->count()); // Rabu 7, 14, 21, 28 Okt dan 4 Nov
        $this->assertTrue(Cache::has(PracticeSessionGenerator::markerKey(CarbonImmutable::today())));

        PracticeSession::query()->delete();
        $this->get(route('doctors.index'))->assertOk();

        $this->assertSame(0, PracticeSession::query()->count(), 'Penanda harian mencegah pembangkitan kedua.');
    }

    public function test_a_lock_held_by_another_process_does_not_block_the_page(): void
    {
        $doctor = $this->createDoctor(['name' => 'Rani Oktaviani', 'title_prefix' => 'dr.', 'title_suffix' => null]);
        $this->createSchedule(['doctor_id' => $doctor->id, 'clinic_id' => $doctor->clinic_id, 'day_of_week' => 3, 'valid_from' => '2026-10-05']);
        Cache::forget(PracticeSessionGenerator::markerKey(CarbonImmutable::today()));

        $lock = Cache::lock(PracticeSessionGenerator::LOCK_KEY, 30);
        $this->assertTrue($lock->get());

        try {
            $this->get(route('doctors.index'))->assertOk()->assertSee('dr. Rani Oktaviani');
        } finally {
            $lock->release();
        }

        $this->assertSame(0, PracticeSession::query()->count());
    }

    public function test_the_controller_reads_the_schedule_only_through_the_shared_query(): void
    {
        $source = (string) file_get_contents((new \ReflectionClass(DoctorController::class))->getFileName());

        $this->assertStringContainsString('DoctorAvailabilityQuery', $source);
        $this->assertStringContainsString('ensureDailyHorizon()', $source);
        $this->assertStringNotContainsString('PracticeSession::', $source);
        $this->assertStringNotContainsString('DB::', $source);
    }

    // --- CSP -----------------------------------------------------------------------------------

    public function test_the_page_has_no_inline_handlers_and_no_external_resources(): void
    {
        $doctor = $this->createDoctor(['name' => 'Rani Oktaviani', 'title_prefix' => 'dr.', 'title_suffix' => null]);
        $this->sessionOf($doctor, '2026-10-06');

        $html = $this->get(route('doctors.index'))->assertOk()->getContent();

        $this->assertSame(0, preg_match('/\son[a-z]+\s*=/i', $html));
        $this->assertStringNotContainsString('onerror', $html);
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringNotContainsString('blob:', $html);
    }
}
