<?php

namespace Tests\Feature\Patient;

use App\Enums\ClinicType;
use App\Enums\DoctorStatus;
use App\Enums\SessionStatus;
use App\Models\Doctor;
use App\Models\InsurancePartner;
use App\Models\PracticeSession;
use App\Models\User;
use App\Services\Scheduling\PracticeSessionGenerator;
use App\Services\Settings\SettingsService;
use Carbon\CarbonImmutable;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InteractsWithMasterData;
use Tests\Support\InteractsWithPatients;
use Tests\Support\InteractsWithSchedules;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

/**
 * Wizard pendaftaran langkah 1 sampai 4 (#29, F6-R17/R18/R19): AKS-01/03 dan AKS-07 asli (D-151), V-09 (VAL-09),
 * JDW-12/CRD-02 (sumber jadwal tunggal), JDW-03/04, EMP-04, SEK-15, Review Focus 3 dan 4. Waktu: Senin 5 Oktober 2026
 * 07.00; jendela pendaftaran 30 hari berakhir Rabu 4 November; sesi uji 09.00 sampai 12.00.
 */
class BookingWizardTest extends TestCase
{
    use InteractsWithMasterData;
    use InteractsWithPatients;
    use InteractsWithSchedules;
    use InteractsWithStaff;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-05 07:00:00');

        $today = CarbonImmutable::today();
        Cache::put(PracticeSessionGenerator::markerKey($today), true, $today->endOfDay());
    }

    // --- pembantu ----------------------------------------------------------------------------

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

    private ?User $patient = null;

    /** Pasien uji (satu per tes; email tetap `siti@contoh.test`). */
    private function patient(): User
    {
        return $this->patient ??= $this->createPatient(['name' => 'Siti Rahma', 'email' => 'siti@contoh.test'], ['full_name' => 'Siti Rahma']);
    }

    /** @param  array<string, string>  $query */
    private function wizard(array $query = []): string
    {
        return route('patient.appointments.create', $query);
    }

    private function open(string $url): string
    {
        return $this->actingAs($this->patient())->get($url)->assertOk()->getContent();
    }

    // --- akses (AKS-01, AKS-03, AKS-07) ------------------------------------------------------

    public function test_the_route_is_registered_with_the_portal_middleware(): void
    {
        $route = Route::getRoutes()->getByName('patient.appointments.create');

        $this->assertNotNull($route);
        $this->assertSame('pasien/pendaftaran/baru', $route->uri());
        $this->assertSame(['GET', 'HEAD'], $route->methods());
        $this->assertSame(['web', 'auth', 'account:patient', 'active'], array_values(array_filter($route->gatherMiddleware(), 'is_string')));
        $this->assertSame('App\\Http\\Controllers\\Patient\\AppointmentController@create', $route->getActionName());
    }

    public function test_guests_go_to_the_login_and_staff_to_the_admin_dashboard(): void
    {
        $this->get($this->wizard())->assertRedirect(route('login'));

        $staff = $this->createStaff('registration-officer');
        $this->actingAs($staff)->get($this->wizard())->assertRedirect(route('admin.dashboard'));
        $this->actingAs($staff)->getJson($this->wizard())->assertForbidden();
    }

    /** AKS-07 asli (D-151): tamu dengan `?sesi=ID` masuk lewat `/masuk` dan kembali ke URL yang sama, query utuh. */
    public function test_aks_07_a_guest_with_a_bookable_session_returns_to_the_wizard_with_the_query_intact(): void
    {
        $this->patient();
        $session = $this->sessionOf($this->createDoctor(), '2026-10-06');
        $path = '/pasien/pendaftaran/baru?sesi='.$session->id;

        $this->get($path)->assertRedirect(route('login'));

        $this->from(route('login'))
            ->post(route('login.store'), ['email' => 'siti@contoh.test', 'password' => $this->patientPassword()])
            ->assertRedirect(url($path))
            ->assertSessionMissing('url.intended');

        $this->get($path)->assertOk()->assertSee('Langkah 4 - Data pendaftaran');
    }

    public function test_a_patient_with_an_inactive_profile_or_without_a_profile_is_forbidden(): void
    {
        $this->actingAs($this->createPatient([], ['is_active' => false]))->get($this->wizard())->assertForbidden();
        $this->actingAs(User::factory()->patient()->create())->get($this->wizard())->assertForbidden();
    }

    public function test_the_page_is_never_cached_and_is_noindex_with_one_heading(): void
    {
        $response = $this->actingAs($this->patient())->get($this->wizard())->assertOk();
        $html = $response->getContent();

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('<title>Pendaftaran Kunjungan — '.config('app.name').'</title>', $html);
        $this->assertStringContainsString('<meta name="robots" content="noindex,nofollow">', $html);
        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertStringContainsString('data-wizard-heading', $html);
        $this->assertStringContainsString('<li class="breadcrumb-item active" aria-current="page">Pendaftaran Kunjungan</li>', $html);
        $this->assertStringContainsString('<li class="breadcrumb-item"><a href="'.route('patient.dashboard').'">Portal Pasien</a></li>', $html);
    }

    public function test_the_portal_menu_marks_the_booking_item_as_current_on_every_wizard_step(): void
    {
        $session = $this->sessionOf($this->createDoctor(), '2026-10-06');

        foreach ([$this->wizard(), $this->wizard(['sesi' => $session->id])] as $url) {
            $html = $this->open($url);

            $this->assertMatchesRegularExpression('#aria-current="page"[^>]*>\s*<i class="bi bi-calendar-plus" aria-hidden="true"></i>\s*<span>Daftar Kunjungan</span>#', $html, $url);
        }
    }

    public function test_the_navigation_config_marks_review_as_a_booking_page_too(): void
    {
        $item = collect(config('navigation.patient'))->firstWhere('route', 'patient.appointments.create');

        $this->assertSame(['patient.appointments.create', 'patient.appointments.review'], $item['active']);
    }

    // --- langkah 1: poliklinik ---------------------------------------------------------------

    public function test_step_one_lists_bookable_polyclinics_and_dims_those_without_a_schedule(): void
    {
        $withSchedule = $this->createClinic(['name' => 'Poliklinik Anak Uji', 'code' => 'ANK']);
        $withoutSchedule = $this->createClinic(['name' => 'Poliklinik Mata Uji', 'code' => 'MTA']);
        $onlyCancelled = $this->createClinic(['name' => 'Poliklinik Gigi Uji', 'code' => 'GGI']);
        $support = $this->createClinic(['name' => 'Laboratorium Uji', 'code' => 'LAB', 'type' => ClinicType::Penunjang, 'is_bookable' => false]);
        $inactive = $this->createClinic(['name' => 'Poliklinik Tutup Uji', 'code' => 'TTP', 'is_active' => false]);
        $notBookable = $this->createClinic(['name' => 'Poliklinik Rujukan Uji', 'code' => 'RJK', 'is_bookable' => false]);

        $this->sessionOf($this->createDoctor([], $withSchedule), '2026-10-06');
        $this->sessionOf($this->createDoctor([], $onlyCancelled), '2026-10-07', ['status' => SessionStatus::Cancelled]);
        $this->sessionOf($this->createDoctor([], $support), '2026-10-06');
        $this->sessionOf($this->createDoctor([], $notBookable), '2026-10-06');

        $html = $this->open($this->wizard());

        $this->assertStringContainsString('Langkah 1 - Pilih poliklinik', $html);
        $this->assertStringContainsString('Poliklinik Anak Uji', $html);
        $this->assertStringContainsString('(ANK)', $html);
        $this->assertStringContainsString('href="'.e($this->wizard(['poli' => $withSchedule->slug])).'"', $html);
        $this->assertStringContainsString('Poliklinik Mata Uji', $html);
        $this->assertStringContainsString('Poliklinik Gigi Uji', $html);
        $this->assertSame(2, substr_count($html, 'Belum ada jadwal'), 'Mata (tanpa sesi) dan Gigi (hanya sesi dibatalkan) tampil redup.');
        $this->assertStringNotContainsString('poli='.$withoutSchedule->slug, $html);
        $this->assertStringNotContainsString('poli='.$onlyCancelled->slug, $html);

        foreach ([$support, $inactive, $notBookable] as $hidden) {
            $this->assertStringNotContainsString($hidden->name, $html);
        }

        $this->assertSame(1, substr_count($html, 'aria-current="step"'));
    }

    public function test_step_one_shows_an_empty_state_with_the_contact_link_when_no_polyclinic_is_bookable(): void
    {
        $this->createClinic(['name' => 'Poliklinik Tutup Uji', 'is_active' => false]);

        $html = $this->open($this->wizard());

        $this->assertStringContainsString('Belum ada poliklinik yang dapat didaftar', $html);
        $this->assertStringContainsString('href="'.route('contact').'"', $html);
        $this->assertStringNotContainsString('Poliklinik Tutup Uji', $html);
    }

    // --- langkah 2: dokter -------------------------------------------------------------------

    public function test_step_two_lists_the_doctors_of_the_polyclinic_with_their_nearest_session(): void
    {
        $clinic = $this->createClinic(['name' => 'Poliklinik Anak Uji']);
        $open = $this->createDoctor(['name' => 'Laras Wulandari'], $clinic);
        $full = $this->createDoctor(['name' => 'Bima Santoso'], $clinic);
        $none = $this->createDoctor(['name' => 'Citra Lestari'], $clinic);
        $inactive = $this->createDoctor(['name' => 'Dedi Nonaktif', 'status' => DoctorStatus::Inactive], $clinic);
        $elsewhere = $this->createDoctor(['name' => 'Eko Lain']);

        $this->sessionOf($open, '2026-10-06', ['booked_count' => 3]);
        $this->sessionOf($full, '2026-10-06', ['quota' => 2, 'booked_count' => 2]);
        $this->sessionOf($elsewhere, '2026-10-06');

        $html = $this->open($this->wizard(['poli' => $clinic->slug]));

        $this->assertStringContainsString('Langkah 2 - Pilih dokter', $html);
        $this->assertStringContainsString('Poliklinik: <strong>Poliklinik Anak Uji</strong>', $html);
        $this->assertStringContainsString('href="'.$this->wizard().'"', $html, 'Tautan Ubah dan langkah 1 yang selesai.');

        $this->assertStringContainsString('dr. Laras Wulandari, Sp.PD', $html);
        $this->assertStringContainsString('Sel, 6 Okt 2026', $html);
        $this->assertStringContainsString('09.00–12.00', $html);
        $this->assertStringContainsString('Sisa 17 dari 20', $html);
        $this->assertStringContainsString('href="'.e($this->wizard(['poli' => $clinic->slug, 'dokter' => $open->slug])).'"', $html);

        $this->assertStringContainsString('dr. Bima Santoso, Sp.PD', $html);
        $this->assertStringContainsString('Penuh', $html);
        $this->assertStringNotContainsString('dokter='.$full->slug, $html, 'Dokter tanpa sesi yang dapat dipesan tidak punya tombol Pilih.');
        $this->assertStringContainsString('(tidak tersedia)', $html);

        $this->assertStringContainsString('dr. Citra Lestari, Sp.PD', $html);
        $this->assertSame(1, substr_count($html, 'Belum ada jadwal dalam 30 hari ke depan'));
        $this->assertStringNotContainsString('dokter='.$none->slug, $html);

        $this->assertStringNotContainsString('Dedi Nonaktif', $html);
        $this->assertStringNotContainsString('Eko Lain', $html);
        $this->assertSame(1, substr_count($html, 'aria-current="step"'));
    }

    public function test_step_two_shows_only_active_specialties_of_the_doctor(): void
    {
        $clinic = $this->createClinic();
        $active = $this->createSpecialty(['name' => 'Spesialis Anak Uji']);
        $inactive = $this->createSpecialty(['name' => 'Spesialis Lama Uji', 'is_active' => false]);
        $doctor = $this->createDoctor(['name' => 'Laras Wulandari'], $clinic, [$inactive, $active]);
        $this->sessionOf($doctor, '2026-10-06');

        $html = $this->open($this->wizard(['poli' => $clinic->slug]));

        $this->assertStringContainsString('Spesialis Anak Uji', $html);
        $this->assertStringNotContainsString('Spesialis Lama Uji', $html);
    }

    // --- pembangkitan sesi harian dan sumber jadwal tunggal (JDW-12, CRD-02) ------------------

    public function test_the_wizard_triggers_the_daily_session_generation_and_uses_the_generated_sessions(): void
    {
        Cache::forget(PracticeSessionGenerator::markerKey(CarbonImmutable::today()));

        $clinic = $this->createClinic(['name' => 'Poliklinik Anak Uji']);
        $doctor = $this->createDoctor(['name' => 'Laras Wulandari'], $clinic);
        $this->createSchedule(['doctor_id' => $doctor->id]); // templat Senin 08.00-12.00 berlaku mulai hari ini

        $this->assertSame(0, PracticeSession::query()->count());

        $html = $this->open($this->wizard(['poli' => $clinic->slug]));

        $this->assertGreaterThan(0, PracticeSession::query()->count());
        $this->assertTrue(Cache::has(PracticeSessionGenerator::markerKey(CarbonImmutable::today())));
        // Senin 5 Oktober 07.00: sesi 08.00 sudah ditutup (batas 60 menit), sesi terdekat yang dapat dipesan Senin 12 Oktober.
        $this->assertStringContainsString('Sen, 12 Okt 2026', $html);
        $this->assertStringContainsString('Sisa 20 dari 20', $html);
    }

    // --- langkah 3: tanggal dan sesi ---------------------------------------------------------

    public function test_step_three_shows_date_chips_with_reasons_and_the_sessions_of_the_selected_date(): void
    {
        $clinic = $this->createClinic(['name' => 'Poliklinik Anak Uji']);
        $doctor = $this->createDoctor(['name' => 'Laras Wulandari'], $clinic);
        $this->sessionOf($doctor, '2026-10-06', ['quota' => 5, 'booked_count' => 5]);
        $this->sessionOf($doctor, '2026-10-07', ['status' => SessionStatus::Cancelled]);
        $morning = $this->sessionOf($doctor, '2026-10-08', ['booked_count' => 4]);
        $afternoon = $this->sessionOf($doctor, '2026-10-08', ['start_time' => '13:00:00', 'end_time' => '15:00:00', 'status' => SessionStatus::Closed, 'quota' => 10]);
        $this->sessionOf($doctor, '2026-10-09');

        $html = $this->open($this->wizard(['poli' => $clinic->slug, 'dokter' => $doctor->slug]));

        $this->assertStringContainsString('Langkah 3 - Pilih tanggal dan sesi', $html);
        $this->assertStringContainsString('Poliklinik: <strong>Poliklinik Anak Uji</strong>', $html);
        $this->assertStringContainsString('Dokter: <strong>dr. Laras Wulandari, Sp.PD</strong>', $html);
        $this->assertStringContainsString('href="'.$this->wizard().'" aria-label="Ubah poliklinik"', $html);
        $this->assertStringContainsString('href="'.e($this->wizard(['poli' => $clinic->slug])).'" aria-label="Ubah dokter"', $html);

        // Tanggal tanpa sesi yang dapat dipesan nonaktif dengan alasan; sisanya tautan GET yang membawa poli, dokter, tanggal.
        $this->assertStringContainsString('Sel 6 Okt - Penuh', $html);
        $this->assertStringContainsString('Rab 7 Okt - Dibatalkan', $html);
        $this->assertStringNotContainsString('tanggal=2026-10-06', $html);
        $this->assertStringNotContainsString('tanggal=2026-10-07', $html);
        $this->assertStringContainsString('href="'.e($this->wizard(['poli' => $clinic->slug, 'dokter' => $doctor->slug, 'tanggal' => '2026-10-09'])).'"', $html);

        // Tanpa `tanggal`, tanggal pertama yang dapat dipesan dipilih (Kamis 8 Oktober).
        $this->assertStringContainsString('Sesi pada Kamis, 8 Oktober 2026', $html);
        $this->assertStringContainsString('value="'.$morning->id.'"', $html);
        $this->assertStringContainsString('Sisa 16 dari 20', $html);
        $this->assertMatchesRegularExpression('#<input[^>]*type="radio"[^>]*name="sesi"[^>]*value="'.$morning->id.'"[^>]*checked#', $html);
        $this->assertMatchesRegularExpression('#<input[^>]*name="sesi"[^>]*value="'.$afternoon->id.'"[^>]*disabled#', $html);
        $this->assertStringContainsString('Sesi ditutup oleh petugas.', $html);

        // Form tanpa JavaScript: GET ke `create` dengan field tersembunyi poli, dokter, tanggal.
        $this->assertStringContainsString('<form method="GET" action="'.$this->wizard().'"', $html);
        $this->assertStringContainsString('<input type="hidden" name="poli" value="'.$clinic->slug.'">', $html);
        $this->assertStringContainsString('<input type="hidden" name="dokter" value="'.$doctor->slug.'">', $html);
        $this->assertStringContainsString('<input type="hidden" name="tanggal" value="2026-10-08">', $html);
        $this->assertStringContainsString('Lanjut: Data Pendaftaran', $html);
        $this->assertSame(1, substr_count($html, 'aria-current="step"'));
    }

    public function test_step_three_follows_the_requested_date_when_it_is_selectable(): void
    {
        $clinic = $this->createClinic();
        $doctor = $this->createDoctor([], $clinic);
        $this->sessionOf($doctor, '2026-10-08');
        $this->sessionOf($doctor, '2026-10-09');

        $html = $this->open($this->wizard(['poli' => $clinic->slug, 'dokter' => $doctor->slug, 'tanggal' => '2026-10-09']));

        $this->assertStringContainsString('Sesi pada Jumat, 9 Oktober 2026', $html);
        $this->assertStringContainsString('<input type="hidden" name="tanggal" value="2026-10-09">', $html);
    }

    public function test_step_three_ignores_a_requested_date_that_cannot_be_booked(): void
    {
        $clinic = $this->createClinic();
        $doctor = $this->createDoctor([], $clinic);
        $this->sessionOf($doctor, '2026-10-06', ['quota' => 1, 'booked_count' => 1]);
        $this->sessionOf($doctor, '2026-10-08');

        $html = $this->open($this->wizard(['poli' => $clinic->slug, 'dokter' => $doctor->slug, 'tanggal' => '2026-10-06']));

        $this->assertStringContainsString('Sesi pada Kamis, 8 Oktober 2026', $html);
    }

    public function test_step_three_explains_a_session_closed_by_the_online_cutoff(): void
    {
        $this->travelTo('2026-10-06 08:30:00'); // 30 menit sebelum sesi 09.00: batas 60 menit sudah lewat

        $clinic = $this->createClinic();
        $doctor = $this->createDoctor([], $clinic);
        $closed = $this->sessionOf($doctor, '2026-10-06');
        $later = $this->sessionOf($doctor, '2026-10-06', ['start_time' => '13:00:00', 'end_time' => '16:00:00']);
        Cache::put(PracticeSessionGenerator::markerKey(CarbonImmutable::today()), true, CarbonImmutable::today()->endOfDay());

        $html = $this->open($this->wizard(['poli' => $clinic->slug, 'dokter' => $doctor->slug]));

        $this->assertMatchesRegularExpression('#<input[^>]*name="sesi"[^>]*value="'.$closed->id.'"[^>]*disabled#', $html);
        $this->assertMatchesRegularExpression('#<input[^>]*name="sesi"[^>]*value="'.$later->id.'"[^>]*checked#', $html);
        $this->assertStringContainsString('Pendaftaran online ditutup', $html);
        $this->assertStringContainsString('Alasan: batas pendaftaran online 60 menit sebelum sesi dimulai.', $html);
    }

    public function test_step_three_without_any_session_shows_an_empty_state_and_a_way_back(): void
    {
        $clinic = $this->createClinic();
        $doctor = $this->createDoctor([], $clinic);

        $html = $this->open($this->wizard(['poli' => $clinic->slug, 'dokter' => $doctor->slug]));

        $this->assertStringContainsString('Belum ada jadwal praktik dokter ini dalam 30 hari ke depan.', $html);
        $this->assertStringContainsString('href="'.e($this->wizard(['poli' => $clinic->slug])).'"', $html);
        $this->assertStringNotContainsString('name="sesi"', $html);
    }

    public function test_step_three_warns_when_no_date_can_be_booked_at_all(): void
    {
        $clinic = $this->createClinic();
        $doctor = $this->createDoctor([], $clinic);
        $this->sessionOf($doctor, '2026-10-06', ['quota' => 1, 'booked_count' => 1]);

        $html = $this->open($this->wizard(['poli' => $clinic->slug, 'dokter' => $doctor->slug]));

        $this->assertStringContainsString('Sel 6 Okt - Penuh', $html);
        $this->assertStringContainsString('Tidak ada tanggal yang dapat dipesan saat ini.', $html);
        $this->assertStringNotContainsString('name="sesi"', $html);
    }

    // --- tanpa JavaScript ----------------------------------------------------------------------

    /** Wizard berfungsi penuh tanpa JavaScript: tautan GET dan formulir biasa, tanpa skrip atau atribut event inline. */
    public function test_every_step_works_without_javascript(): void
    {
        $clinic = $this->createClinic();
        $doctor = $this->createDoctor([], $clinic);
        $session = $this->sessionOf($doctor, '2026-10-06');
        $urls = [
            $this->wizard(),
            $this->wizard(['poli' => $clinic->slug]),
            $this->wizard(['poli' => $clinic->slug, 'dokter' => $doctor->slug]),
            $this->wizard(['sesi' => $session->id]),
        ];
        $pages = [];

        foreach ($urls as $url) {
            $pages[$url] = $this->open($url);

            $this->assertDoesNotMatchRegularExpression('#<script(?![^>]*\bsrc=)#i', $pages[$url], $url);
            $this->assertDoesNotMatchRegularExpression('#\son[a-z]+\s*=#i', $pages[$url], $url);
        }

        $stepThree = $pages[$urls[2]];
        $this->assertStringContainsString('<form method="GET" action="'.$this->wizard().'"', $stepThree);
        $this->assertMatchesRegularExpression('#<input[^>]*type="radio"[^>]*name="sesi"[^>]*required#', $stepThree, 'Browser menegakkan pilihan sesi tanpa JavaScript.');

        $stepFour = $pages[$urls[3]];
        $this->assertDoesNotMatchRegularExpression('#data-payment-block="[a-z]+"[^>]*\shidden#', $stepFour, 'Semua blok pembayaran tampil; hanya JavaScript yang menyembunyikannya.');
        $this->assertDoesNotMatchRegularExpression('#name="(bpjs_number|policy_number|insurance_partner_id|save_bpjs)"[^>]*\sdisabled#', $stepFour);
        $this->assertSame(3, substr_count($stepFour, 'Isi hanya bila memilih'), 'Teks bantuan BPJS, mitra asuransi, dan nomor polis selalu terlihat.');
        $this->assertStringContainsString('name="_token"', $stepFour);
    }

    // --- langkah 4: data pendaftaran ---------------------------------------------------------

    public function test_step_four_shows_the_summary_and_the_payment_form_without_javascript(): void
    {
        $doctor = $this->createDoctor(['name' => 'Laras Wulandari'], $this->createClinic(['name' => 'Poliklinik Anak Uji']));
        $session = $this->sessionOf($doctor, '2026-10-06', ['booked_count' => 3]);
        $active = InsurancePartner::factory()->create(['name' => 'Asuransi Contoh Aktif']);
        InsurancePartner::factory()->inactive()->create(['name' => 'Asuransi Contoh Mati']);
        $patient = $this->patient();

        $html = $this->actingAs($patient)->get($this->wizard(['sesi' => $session->id]))->assertOk()->getContent();

        $this->assertStringContainsString('Langkah 4 - Data pendaftaran', $html);
        $this->assertStringContainsString('Poliklinik Anak Uji', $html);
        $this->assertStringContainsString('dr. Laras Wulandari, Sp.PD', $html);
        $this->assertStringContainsString('Selasa, 6 Oktober 2026', $html);
        $this->assertStringContainsString('09.00–12.00', $html);
        $this->assertStringContainsString('Siti Rahma', $html);
        $this->assertStringContainsString($patient->patientProfile->patient_number, $html);
        $this->assertStringContainsString('href="'.route('patient.profile.edit').'"', $html);

        $this->assertStringContainsString('Jenis pembayaran', $html);

        foreach (['Umum/Pribadi', 'BPJS Kesehatan', 'Asuransi'] as $label) {
            $this->assertStringContainsString($label, $html);
        }

        $this->assertSame(3, substr_count($html, 'name="payment_type"'));
        $this->assertDoesNotMatchRegularExpression('#name="payment_type"[^>]*checked#', $html, 'Jenis pembayaran belum dipilih.');
        $this->assertStringContainsString('data-payment-toggle', $html);
        $this->assertStringContainsString('data-payment-block="bpjs"', $html);
        $this->assertStringContainsString('data-payment-block="asuransi"', $html);
        $this->assertStringContainsString('name="bpjs_number"', $html);
        $this->assertStringContainsString('inputmode="numeric"', $html);
        $this->assertStringContainsString('autocomplete="off"', $html);
        $this->assertStringContainsString('Isi hanya bila memilih BPJS Kesehatan', $html);
        $this->assertStringContainsString('name="save_bpjs"', $html);
        $this->assertStringContainsString('Simpan nomor BPJS ke profil saya', $html);
        $this->assertStringContainsString('name="insurance_partner_id"', $html);
        $this->assertStringContainsString('Asuransi Contoh Aktif', $html);
        $this->assertStringNotContainsString('Asuransi Contoh Mati', $html);
        $this->assertStringContainsString('value="'.$active->id.'"', $html);
        $this->assertStringContainsString('name="policy_number"', $html);
        $this->assertStringContainsString('name="complaint"', $html);
        $this->assertStringContainsString('<input type="hidden" id="practice_session_id" name="practice_session_id" value="'.$session->id.'">', $html);
        $this->assertStringContainsString('Tinjau Pendaftaran', $html);
        $this->assertStringContainsString('href="'.e($this->wizard(['poli' => $doctor->clinic->slug, 'dokter' => $doctor->slug, 'tanggal' => '2026-10-06'])).'"', $html);
        $this->assertSame(1, substr_count($html, 'aria-current="step"'));
    }

    public function test_step_four_offers_the_stored_bpjs_number_only_in_masked_form(): void
    {
        $session = $this->sessionOf($this->createDoctor(), '2026-10-06');
        $patient = $this->createPatient(['email' => 'siti@contoh.test'], ['bpjs_number' => '0009900000011']);

        $html = $this->actingAs($patient)->get($this->wizard(['sesi' => $session->id]))->assertOk()->getContent();

        $this->assertStringContainsString('Kosongkan untuk memakai nomor BPJS tersimpan (•••••••••0011).', $html);
        $this->assertStringNotContainsString('0009900000011', $html);
        $this->assertDoesNotMatchRegularExpression('#name="bpjs_number"[^>]*value="[^"]#', $html);
    }

    public function test_step_four_without_a_stored_bpjs_number_does_not_mention_one(): void
    {
        $session = $this->sessionOf($this->createDoctor(), '2026-10-06');

        $this->assertStringNotContainsString('Kosongkan untuk memakai nomor BPJS tersimpan', $this->open($this->wizard(['sesi' => $session->id])));
    }

    /** SEK-15: nama pasien, nama dokter, dan nama poliklinik berisi tag tampil sebagai teks. */
    public function test_step_four_escapes_names(): void
    {
        $clinic = $this->createClinic(['name' => '<b>Poli</b> Uji']);
        $doctor = $this->createDoctor(['name' => '<script>alert(1)</script>'], $clinic);
        $session = $this->sessionOf($doctor, '2026-10-06');
        $patient = $this->createPatient(['email' => 'siti@contoh.test'], ['full_name' => '<i>Siti</i>']);

        $html = $this->actingAs($patient)->get($this->wizard(['sesi' => $session->id]))->assertOk()->getContent();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringNotContainsString('<b>Poli</b>', $html);
        $this->assertStringNotContainsString('<i>Siti</i>', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringContainsString('&lt;b&gt;Poli&lt;/b&gt; Uji', $html);
    }

    // --- `?sesi=` langsung (F6-R18) ----------------------------------------------------------

    /** @return array<string, array{array<string, mixed>, string}> */
    public static function unbookableSessions(): array
    {
        return [
            'penuh' => [['quota' => 2, 'booked_count' => 2], 'Penuh'],
            'ditutup petugas' => [['status' => SessionStatus::Closed], 'Ditutup'],
            'sedang diperbarui' => [['status' => SessionStatus::NeedsReview, 'pending_change' => ['source' => 'manual_cancel', 'reason' => 'Uji']], 'Sedang diperbarui'],
            'dibatalkan' => [['status' => SessionStatus::Cancelled], 'Dibatalkan'],
            'sudah lewat' => [['session_date' => '2026-10-04'], 'Sudah lewat'],
        ];
    }

    /** @param  array<string, mixed>  $overrides */
    #[DataProvider('unbookableSessions')]
    public function test_a_direct_session_that_cannot_be_booked_falls_back_to_step_three_with_the_reason(array $overrides, string $label): void
    {
        $clinic = $this->createClinic();
        $doctor = $this->createDoctor(['name' => 'Laras Wulandari'], $clinic);
        $session = $this->sessionOf($doctor, '2026-10-06', $overrides);
        $this->sessionOf($doctor, '2026-10-08');

        $html = $this->open($this->wizard(['sesi' => $session->id]));

        $this->assertStringContainsString('Langkah 3 - Pilih tanggal dan sesi', $html);
        $this->assertStringContainsString('Sesi yang dipilih tidak dapat dipesan: '.$label.'.', $html);
        $this->assertStringContainsString('dr. Laras Wulandari, Sp.PD', $html);
        $this->assertStringNotContainsString('name="practice_session_id"', $html);
    }

    public function test_a_direct_session_closed_by_the_online_cutoff_falls_back_to_step_three(): void
    {
        $this->travelTo('2026-10-06 08:30:00');
        Cache::put(PracticeSessionGenerator::markerKey(CarbonImmutable::today()), true, CarbonImmutable::today()->endOfDay());

        $doctor = $this->createDoctor();
        $session = $this->sessionOf($doctor, '2026-10-06');

        $html = $this->open($this->wizard(['sesi' => $session->id]));

        $this->assertStringContainsString('Sesi yang dipilih tidak dapat dipesan: Pendaftaran online ditutup.', $html);
        $this->assertStringContainsString('Langkah 3 - Pilih tanggal dan sesi', $html);
    }

    public function test_a_direct_bookable_session_jumps_to_step_four_for_a_patient_who_is_already_logged_in(): void
    {
        $session = $this->sessionOf($this->createDoctor(), '2026-10-06');

        $html = $this->open($this->wizard(['sesi' => $session->id]));

        $this->assertStringContainsString('Langkah 4 - Data pendaftaran', $html);
        $this->assertStringNotContainsString('Sesi yang dipilih tidak dapat dipesan', $html);
    }

    /**
     * @return array<string, array{array<string, mixed>, array<string, mixed>}>
     */
    public static function hiddenOwners(): array
    {
        return [
            'dokter nonaktif' => [['status' => DoctorStatus::Inactive->value], []],
            'dokter diarsipkan' => [['status' => DoctorStatus::Archived->value], []],
            'poliklinik nonaktif' => [[], ['is_active' => false]],
        ];
    }

    /**
     * FX2: nama dokter atau poliklinik yang tidak tampil publik (F3-R17) tidak boleh bocor lewat `?sesi=`.
     *
     * @param  array<string, mixed>  $doctorState
     * @param  array<string, mixed>  $clinicState
     */
    #[DataProvider('hiddenOwners')]
    public function test_a_direct_session_of_a_hidden_doctor_or_clinic_is_treated_as_unusable(array $doctorState, array $clinicState): void
    {
        $clinic = $this->createClinic(['name' => 'Poliklinik Rahasia Uji'] + $clinicState);
        $doctor = $this->createDoctor(['name' => 'Laras Wulandari'] + $doctorState, $clinic);
        $session = $this->sessionOf($doctor, '2026-10-06');

        $response = $this->actingAs($this->patient())->get($this->wizard(['sesi' => $session->id]));

        $response->assertRedirect($this->wizard())->assertSessionHas('warning', self::INVALID);
        $this->assertStringNotContainsString('Laras Wulandari', (string) $response->getContent());
        $this->assertStringNotContainsString('Poliklinik Rahasia Uji', (string) $response->getContent());

        $followed = $this->actingAs($this->patient())->get($this->wizard())->assertOk()->getContent();
        $this->assertStringNotContainsString('Laras Wulandari', $followed);

        if ($clinicState !== []) {
            $this->assertStringNotContainsString('Poliklinik Rahasia Uji', $followed);
        }
    }

    /** Tautan "Daftar" pada jadwal publik (`?sesi=ID`) dan tombol poliklinik (`?poli=slug`) mendarat di langkah yang benar. */
    public function test_the_public_register_links_land_on_the_right_wizard_step(): void
    {
        $clinic = $this->createClinic(['name' => 'Poliklinik Anak Uji']);
        $doctor = $this->createDoctor(['name' => 'Laras Wulandari'], $clinic);
        $session = $this->sessionOf($doctor, '2026-10-06');

        $this->get(route('schedules.index'))->assertOk()->assertSee('href="'.route('patient.appointments.create', ['sesi' => $session->id]).'"', false);
        $this->get(route('clinics.show', $clinic))->assertOk()->assertSee('href="'.route('patient.appointments.create', ['poli' => $clinic->slug]).'"', false);

        $patient = $this->patient();
        $this->actingAs($patient)->get(route('patient.appointments.create', ['sesi' => $session->id]))->assertOk()->assertSee('Langkah 4 - Data pendaftaran');
        $this->actingAs($patient)->get(route('patient.appointments.create', ['poli' => $clinic->slug]))->assertOk()->assertSee('Langkah 2 - Pilih dokter');
    }

    public function test_the_session_in_the_query_wins_over_a_mismatching_clinic_and_doctor(): void
    {
        $other = $this->createDoctor();
        $session = $this->sessionOf($this->createDoctor(['name' => 'Laras Wulandari']), '2026-10-06');

        $html = $this->open($this->wizard(['poli' => $other->clinic->slug, 'dokter' => $other->slug, 'sesi' => $session->id]));

        $this->assertStringContainsString('Langkah 4 - Data pendaftaran', $html);
        $this->assertStringContainsString('dr. Laras Wulandari, Sp.PD', $html);
    }

    // --- query dirusak (V-09, Review Focus 4) -------------------------------------------------

    private const INVALID = 'Poliklinik, dokter, atau sesi yang dipilih tidak tersedia. Silakan pilih kembali.';

    private const CLINIC_FIRST = 'Pilih poliklinik terlebih dahulu.';

    /** @return array<string, array{string, string}> */
    public static function invalidQueries(): array
    {
        return [
            'poli larik' => ['poli[]=x', self::INVALID],
            'poli tidak ada' => ['poli=tidak-ada', self::INVALID],
            'poli terlalu panjang' => ['poli='.str_repeat('a', 101), self::INVALID],
            'poli nonaktif' => ['poli=__POLI_NONAKTIF__', self::INVALID],
            'poli penunjang' => ['poli=__POLI_PENUNJANG__', self::INVALID],
            'poli valid dengan dokter tidak ada' => ['poli=__POLI__&dokter=tidak-ada', self::INVALID],
            'poli valid dengan dokter larik' => ['poli=__POLI__&dokter[]=x', self::INVALID],
            'dokter nonaktif' => ['poli=__POLI__&dokter=__DOKTER_NONAKTIF__', self::INVALID],
            'dokter tanpa poli' => ['dokter=__DOKTER__', self::CLINIC_FIRST],
            'dokter tidak ada tanpa poli' => ['dokter=tidak-ada', self::INVALID],
            'dokter larik tanpa poli' => ['dokter[]=x', self::INVALID],
            'sesi teks' => ['sesi=abc', self::INVALID],
            'sesi larik' => ['sesi[]=1', self::INVALID],
            'sesi nol' => ['sesi=0', self::INVALID],
            'sesi negatif' => ['sesi=-1', self::INVALID],
            'sesi eksponen' => ['sesi=1e3', self::INVALID],
            'sesi tidak ada' => ['sesi=999999', self::INVALID],
            'tanggal terlalu jauh' => ['poli=__POLI__&dokter=__DOKTER__&tanggal=2099-01-01', self::INVALID],
            'tanggal kemarin' => ['poli=__POLI__&dokter=__DOKTER__&tanggal=2026-10-04', self::INVALID],
            'tanggal teks' => ['poli=__POLI__&dokter=__DOKTER__&tanggal=besok', self::INVALID],
            'tanggal larik' => ['poli=__POLI__&dokter=__DOKTER__&tanggal[]=x', self::INVALID],
            'tanggal tidak ada di kalender' => ['poli=__POLI__&dokter=__DOKTER__&tanggal=2026-13-45', self::INVALID],
            'tanggal tepat di luar jendela' => ['poli=__POLI__&dokter=__DOKTER__&tanggal=2026-11-05', self::INVALID],
        ];
    }

    #[DataProvider('invalidQueries')]
    public function test_a_broken_query_redirects_to_step_one_with_a_warning_and_never_fails(string $query, string $message): void
    {
        $clinic = $this->createClinic(['name' => 'Poliklinik Anak Uji']);
        $doctor = $this->createDoctor([], $clinic);
        $this->sessionOf($doctor, '2026-10-06');
        $inactiveClinic = $this->createClinic(['is_active' => false]);
        $support = $this->createClinic(['type' => ClinicType::Penunjang, 'is_bookable' => false]);
        $inactiveDoctor = $this->createDoctor(['status' => DoctorStatus::Inactive], $clinic);

        $query = strtr($query, [
            '__POLI__' => $clinic->slug,
            '__DOKTER__' => $doctor->slug,
            '__POLI_NONAKTIF__' => $inactiveClinic->slug,
            '__POLI_PENUNJANG__' => $support->slug,
            '__DOKTER_NONAKTIF__' => $inactiveDoctor->slug,
        ]);

        $this->actingAs($this->patient())->get('/pasien/pendaftaran/baru?'.$query)
            ->assertRedirect($this->wizard())
            ->assertSessionHas('warning', $message);

        $this->get($this->wizard())->assertOk()->assertSee('Langkah 1 - Pilih poliklinik');
    }

    public function test_the_last_day_of_the_window_is_accepted_and_unknown_parameters_are_ignored(): void
    {
        $clinic = $this->createClinic();
        $doctor = $this->createDoctor([], $clinic);
        $this->sessionOf($doctor, '2026-11-04');

        $this->actingAs($this->patient())
            ->get('/pasien/pendaftaran/baru?poli='.$clinic->slug.'&dokter='.$doctor->slug.'&tanggal=2026-11-04&foo=bar&sesi=')
            ->assertOk()
            ->assertSee('Langkah 3 - Pilih tanggal dan sesi')
            ->assertSee('Sesi pada Rabu, 4 November 2026');

        $this->get('/pasien/pendaftaran/baru?foo=bar&poli=')->assertOk()->assertSee('Langkah 1 - Pilih poliklinik');
    }

    public function test_the_window_follows_the_booking_setting(): void
    {
        $this->seed(SettingsSeeder::class);
        app(SettingsService::class)->forget();
        app(SettingsService::class)->set('booking.window_days', 7);

        $clinic = $this->createClinic();
        $doctor = $this->createDoctor([], $clinic);

        // 12 Oktober (hari ke-7) diterima, 13 Oktober (hari ke-8) ditolak.
        $this->actingAs($this->patient())
            ->get('/pasien/pendaftaran/baru?poli='.$clinic->slug.'&dokter='.$doctor->slug.'&tanggal=2026-10-12')
            ->assertOk();

        $this->get('/pasien/pendaftaran/baru?poli='.$clinic->slug.'&dokter='.$doctor->slug.'&tanggal=2026-10-13')
            ->assertRedirect($this->wizard())
            ->assertSessionHas('warning', self::INVALID);
    }
}
