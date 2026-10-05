<?php

namespace Tests\Feature\Public;

use App\Enums\DoctorStatus;
use App\Enums\ExceptionType;
use App\Enums\SessionStatus;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\PracticeSession;
use App\Models\User;
use App\Services\Media\MediaService;
use App\Services\Scheduling\PracticeSessionGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\Support\ImageFixtures;
use Tests\Support\InteractsWithMasterData;
use Tests\Support\InteractsWithSchedules;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

/**
 * Halaman #7 `doctors.show` (05 hal. 7, 07 W-04, F3-R9/R17): 404 untuk dokter yang tidak tampil (juga bagi staf,
 * A-42), identitas dan biodata teks biasa, SEO, serta jadwal 14 hari dari DoctorAvailabilityQuery. Mencakup
 * CRD-03 (URL langsung) dan Review Focus 4.
 */
class PublicDoctorPagesTest extends TestCase
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

    private function markHorizonGenerated(): void
    {
        $today = CarbonImmutable::today();
        Cache::put(PracticeSessionGenerator::markerKey($today), true, $today->endOfDay());
    }

    private function url(Doctor $doctor): string
    {
        return route('doctors.show', $doctor->slug);
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

    /** Baris tabel jadwal (bukan baris judul) yang memuat tanggal tersebut. */
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

    private function fakeRoute(string $name, string $uri): void
    {
        if (! Route::has($name)) {
            Route::middleware('web')->get($uri, fn () => 'x')->name($name);
            Route::getRoutes()->refreshNameLookups();
        }
    }

    // --- 404 (F3-R17, A-42, CRD-03) ------------------------------------------------------------

    public function test_doctors_that_are_not_publicly_visible_are_404_for_guests_patients_and_every_staff_role(): void
    {
        $inactiveClinic = $this->createClinic(['is_active' => false]);
        $hidden = [
            $this->createDoctor(['name' => 'Nonaktif Satu', 'status' => DoctorStatus::Inactive]),
            $this->createDoctor(['name' => 'Arsip Dua', 'status' => DoctorStatus::Archived, 'archived_at' => now()->subDays(3)]),
            $this->createDoctor(['name' => 'Poli Mati Tiga'], $inactiveClinic),
        ];

        foreach ($hidden as $doctor) {
            $this->get($this->url($doctor))->assertNotFound()->assertDontSee($doctor->name);

            foreach (['content-admin', 'registration-officer', 'super-admin'] as $role) {
                $this->actingAs($this->createStaff($role))->get($this->url($doctor))->assertNotFound();
            }

            $this->actingAs(User::factory()->patient()->create())->get($this->url($doctor))->assertNotFound();
        }
    }

    public function test_unknown_slug_is_404(): void
    {
        $this->get('/dokter/slug-yang-tidak-ada')->assertNotFound();
    }

    public function test_a_doctor_becomes_404_as_soon_as_the_status_changes(): void
    {
        $doctor = $this->createDoctor(['name' => 'Rani Oktaviani', 'title_prefix' => 'dr.', 'title_suffix' => null]);

        $this->get($this->url($doctor))->assertOk()->assertSee('dr. Rani Oktaviani');

        $doctor->forceFill(['status' => DoctorStatus::Inactive])->save();
        $this->get($this->url($doctor))->assertNotFound();

        $doctor->forceFill(['status' => DoctorStatus::Archived, 'archived_at' => now()])->save();
        $this->get($this->url($doctor))->assertNotFound();
    }

    public function test_the_page_is_open_to_every_actor_for_a_visible_doctor(): void
    {
        $doctor = $this->createDoctor(['name' => 'Rani Oktaviani', 'title_prefix' => 'dr.', 'title_suffix' => null]);

        $this->get($this->url($doctor))->assertOk();
        $this->actingAs(User::factory()->patient()->create())->get($this->url($doctor))->assertOk();

        foreach (['content-admin', 'registration-officer', 'super-admin'] as $role) {
            $this->actingAs($this->createStaff($role))->get($this->url($doctor))->assertOk();
        }
    }

    // --- identitas, biodata, SEO ---------------------------------------------------------------

    public function test_show_renders_identity_specialties_clinic_and_profile(): void
    {
        $clinic = $this->createClinic(['name' => 'Poliklinik Jantung', 'location' => 'Gedung B Lt. 1']);
        $jantung = $this->createSpecialty(['name' => 'Jantung']);
        $dalam = $this->createSpecialty(['name' => 'Penyakit Dalam']);
        $doctor = $this->createDoctor([
            'name' => 'Maya Kartika Sari', 'title_prefix' => 'dr.', 'title_suffix' => 'Sp.JP',
            'short_bio' => 'Melayani konsultasi jantung.', 'bio' => "Paragraf satu.\n\nParagraf dua.",
        ], $clinic, [$jantung, $dalam]);

        $response = $this->get($this->url($doctor))->assertOk();

        $response->assertSee('dr. Maya Kartika Sari, Sp.JP')
            ->assertSee('Jantung (utama)')
            ->assertSee('Penyakit Dalam')
            ->assertSee('Poliklinik Jantung')
            ->assertSee('Gedung B Lt. 1')
            ->assertSee('href="'.route('clinics.show', $clinic).'"', false)
            ->assertSee('Profil singkat')
            ->assertSee('Melayani konsultasi jantung.')
            ->assertSee('Biodata')
            ->assertSee("Paragraf satu.\n\nParagraf dua.")
            ->assertSee('Jadwal praktik 14 hari ke depan')
            ->assertSee('Lihat jadwal mingguan semua dokter')
            ->assertSee('href="'.route('schedules.index').'"', false);
        $this->assertSame(1, substr_count($response->getContent(), '<h1'));
    }

    public function test_the_biography_is_plain_text_shown_with_pre_line(): void
    {
        $doctor = $this->createDoctor(['bio' => "Baris satu.\n\n<script>alert(1)</script> <b>tebal</b>"]);

        $this->get($this->url($doctor))
            ->assertOk()
            ->assertSee('<div class="doctor-bio">', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt; &lt;b&gt;tebal&lt;/b&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertDontSee('<b>tebal</b>', false);
    }

    public function test_inactive_specialties_are_hidden_and_the_card_survives_without_any_badge(): void
    {
        $hidden = $this->createSpecialty(['name' => 'Spesialis Nonaktif Utama', 'is_active' => false]);
        $shown = $this->createSpecialty(['name' => 'Spesialis Aktif Tambahan']);
        $both = $this->createDoctor(['name' => 'Dokter Dua Spesialis'], null, [$hidden, $shown]);
        $only = $this->createDoctor(['name' => 'Dokter Tanpa Lencana'], null, [$hidden]);

        $this->get($this->url($both))->assertOk()->assertSee('Spesialis Aktif Tambahan')->assertDontSee('Spesialis Nonaktif Utama');
        $this->get($this->url($only))->assertOk()->assertSee('Dokter Tanpa Lencana')->assertDontSee('Spesialis Nonaktif Utama');
    }

    public function test_title_and_description_fall_back_to_name_specialty_and_short_bio(): void
    {
        $anak = $this->createSpecialty(['name' => 'Anak']);
        $doctor = $this->createDoctor([
            'name' => 'Laras Wulandari', 'title_prefix' => 'dr.', 'title_suffix' => 'Sp.A',
            'short_bio' => str_repeat('Melayani konsultasi anak. ', 12), 'meta_title' => null, 'meta_description' => null,
        ], null, [$anak]);

        $html = $this->get($this->url($doctor))->assertOk()
            ->assertSee('<title>dr. Laras Wulandari, Sp.A — Anak — '.config('app.name').'</title>', false)
            ->assertSee('<meta property="og:title" content="dr. Laras Wulandari, Sp.A — Anak">', false)
            ->assertSee('<link rel="canonical" href="'.$this->url($doctor).'">', false)
            ->assertDontSee('og:image', false)
            ->getContent();

        preg_match('/<meta name="description" content="([^"]*)">/', $html, $match);
        $this->assertNotEmpty($match);
        $this->assertLessThanOrEqual(160, mb_strlen(html_entity_decode($match[1])));
        $this->assertStringStartsWith('Melayani konsultasi anak.', html_entity_decode($match[1]));
    }

    public function test_meta_fields_override_the_defaults_and_a_photo_adds_og_image(): void
    {
        $doctor = $this->createDoctor(['name' => 'Laras Wulandari', 'meta_title' => 'Dokter Anak Terdekat', 'meta_description' => 'Deskripsi SEO khusus.']);
        $this->app->make(MediaService::class)->store(ImageFixtures::jpeg(600, 600, 'foto.jpg'), 'photo', $doctor, ['alt_text' => 'Foto contoh dokter']);

        $this->get($this->url($doctor))
            ->assertOk()
            ->assertSee('<title>Dokter Anak Terdekat — '.config('app.name').'</title>', false)
            ->assertSee('<meta name="description" content="Deskripsi SEO khusus.">', false)
            ->assertSee('<meta property="og:image" content="', false);
    }

    public function test_missing_photo_uses_the_local_placeholder_with_an_alt_text(): void
    {
        $doctor = $this->createDoctor(['name' => 'Laras Wulandari', 'title_prefix' => 'dr.', 'title_suffix' => null]);

        $this->get($this->url($doctor))
            ->assertOk()
            ->assertSee('images/placeholders/doctor.svg', false)
            ->assertSee('alt="Foto dr. Laras Wulandari"', false);
    }

    public function test_breadcrumb_ends_with_the_doctor_name_without_a_link(): void
    {
        $doctor = $this->createDoctor(['name' => 'Laras Wulandari', 'title_prefix' => 'dr.', 'title_suffix' => null]);

        $this->get($this->url($doctor))
            ->assertOk()
            ->assertSeeInOrder(['aria-label="Breadcrumb"', 'Beranda', 'Dokter', 'dr. Laras Wulandari'], false)
            ->assertSee('<li class="breadcrumb-item"><a href="'.route('doctors.index').'">Dokter</a></li>', false)
            ->assertSee('<li class="breadcrumb-item active" aria-current="page">dr. Laras Wulandari</li>', false);
    }

    // --- jadwal 14 hari (F3-R12 label, JDW-05) -------------------------------------------------

    public function test_the_schedule_covers_today_through_day_thirteen_and_skips_ended_sessions(): void
    {
        $doctor = $this->createDoctor();
        $this->sessionOf($doctor, '2026-10-05', ['start_time' => '09:00:00', 'end_time' => '12:00:00']);
        $this->sessionOf($doctor, '2026-10-18');
        $this->sessionOf($doctor, '2026-10-19');

        $html = $this->get($this->url($doctor))->assertOk()->getContent();

        $this->assertStringContainsString('datetime="2026-10-05"', $html);
        $this->assertStringContainsString('datetime="2026-10-18"', $html);
        $this->assertStringNotContainsString('datetime="2026-10-19"', $html);

        $this->travelTo('2026-10-05 13:00:00');

        $this->assertStringNotContainsString('datetime="2026-10-05"', $this->get($this->url($doctor))->assertOk()->getContent());
    }

    public function test_each_session_shows_its_label_remaining_quota_and_time(): void
    {
        $doctor = $this->createDoctor();
        $leave = $this->createException(['doctor_id' => $doctor->id, 'type' => ExceptionType::DoctorLeave, 'start_date' => '2026-10-09', 'end_date' => '2026-10-09']);
        $holiday = $this->createException(['doctor_id' => null, 'type' => ExceptionType::HospitalHoliday, 'start_date' => '2026-10-12', 'end_date' => '2026-10-12']);
        $this->sessionOf($doctor, '2026-10-05', ['start_time' => '07:30:00', 'end_time' => '09:00:00']);
        $this->sessionOf($doctor, '2026-10-06', ['quota' => 5, 'booked_count' => 5]);
        $this->sessionOf($doctor, '2026-10-07', ['status' => SessionStatus::Closed]);
        $this->sessionOf($doctor, '2026-10-08', ['status' => SessionStatus::NeedsReview, 'pending_change' => ['source' => 'template_time', 'schedule_id' => 1, 'new_start_time' => '10:00', 'new_end_time' => '13:00']]);
        $this->sessionOf($doctor, '2026-10-09', ['status' => SessionStatus::Cancelled, 'schedule_exception_id' => $leave->id]);
        $this->sessionOf($doctor, '2026-10-12', ['status' => SessionStatus::Cancelled, 'schedule_exception_id' => $holiday->id]);
        $this->sessionOf($doctor, '2026-10-13', ['start_time' => '13:00:00', 'end_time' => '15:30:00', 'quota' => 15, 'booked_count' => 4]);

        $html = $this->get($this->url($doctor))->assertOk()->getContent();

        $expected = [
            '2026-10-05' => ['booking_closed', '07.30–09.00', '20 dari 20'],
            '2026-10-06' => ['full', '09.00–12.00', '0 dari 5'],
            '2026-10-07' => ['closed', '09.00–12.00', '20 dari 20'],
            '2026-10-08' => ['under_review', '09.00–12.00', '20 dari 20'],
            '2026-10-09' => ['leave', '09.00–12.00', 'tidak berlaku'],
            '2026-10-12' => ['holiday', '09.00–12.00', 'tidak berlaku'],
            '2026-10-13' => ['available', '13.00–15.30', '11 dari 15'],
        ];

        foreach ($expected as $date => [$availability, $time, $quota]) {
            $row = $this->rowFor($html, $date);

            $this->assertStringContainsString('data-availability="'.$availability.'"', $row, $date);
            $this->assertStringContainsString($time, $row, $date);
            $this->assertStringContainsString($quota, $row, $date);
        }

        $this->assertStringContainsString('Tersedia · sisa 11', $this->rowFor($html, '2026-10-13'));
        $this->assertStringContainsString('Keterangan status', $html);
    }

    public function test_the_register_button_exists_only_for_online_bookable_sessions_when_the_route_exists(): void
    {
        $this->fakeRoute('patient.appointments.create', '/_uji/pasien/pendaftaran/baru');
        $doctor = $this->createDoctor();
        $open = $this->sessionOf($doctor, '2026-10-07');
        $full = $this->sessionOf($doctor, '2026-10-08', ['quota' => 1, 'booked_count' => 1]);
        $closed = $this->sessionOf($doctor, '2026-10-09', ['status' => SessionStatus::Closed]);

        $html = $this->get($this->url($doctor))->assertOk()->getContent();

        $this->assertStringContainsString('href="'.route('patient.appointments.create', ['sesi' => $open->id]).'"', $this->rowFor($html, '2026-10-07'));
        $this->assertStringNotContainsString('?sesi='.$full->id.'"', $html);
        $this->assertStringNotContainsString('?sesi='.$closed->id.'"', $html);
    }

    public function test_an_empty_schedule_shows_the_empty_state_without_a_contact_link_when_the_route_is_missing(): void
    {
        if (Route::has('contact')) {
            $this->markTestSkipped('Route contact sudah ada (fase 4a): tautan diuji di tes fase tersebut.');
        }

        $doctor = $this->createDoctor();

        $this->get($this->url($doctor))
            ->assertOk()
            ->assertSee('Belum ada jadwal praktik dua minggu ke depan')
            ->assertDontSee('Hubungi Kami')
            ->assertDontSee('<table', false);
    }

    public function test_an_empty_schedule_links_to_contact_when_the_route_exists(): void
    {
        $this->fakeRoute('contact', '/_uji/kontak');
        $doctor = $this->createDoctor();

        $this->get($this->url($doctor))
            ->assertOk()
            ->assertSee('Belum ada jadwal praktik dua minggu ke depan')
            ->assertSee('href="'.route('contact').'"', false);
    }

    // --- tautan ke halaman dokter di kartu dan tabel (route kini ada) ---------------------------

    public function test_doctor_cards_and_schedule_tables_link_to_the_doctor_page(): void
    {
        $doctor = $this->createDoctor();
        $this->sessionOf($doctor, '2026-10-07');

        $this->get(route('doctors.index'))->assertOk()->assertSee('href="'.$this->url($doctor).'"', false);
        $this->get(route('schedules.index'))->assertOk()->assertSee('href="'.$this->url($doctor).'"', false);
    }

    // --- pembangkitan sesi harian (F3-R9) dan mode ketat ---------------------------------------

    public function test_visiting_the_page_triggers_the_daily_generation(): void
    {
        $doctor = $this->createDoctor();
        $this->createSchedule(['doctor_id' => $doctor->id, 'clinic_id' => $doctor->clinic_id, 'day_of_week' => 3, 'valid_from' => '2026-10-05']);
        Cache::forget(PracticeSessionGenerator::markerKey(CarbonImmutable::today()));

        $this->assertSame(0, PracticeSession::query()->count());

        $this->get($this->url($doctor))->assertOk()->assertSee('datetime="2026-10-07"', false);

        $this->assertSame(5, PracticeSession::query()->count());
    }

    public function test_many_sessions_and_specialties_render_in_strict_mode(): void
    {
        $this->assertTrue(Model::preventsLazyLoading());

        $a = $this->createSpecialty(['name' => 'Anak']);
        $b = $this->createSpecialty(['name' => 'Dokter Umum']);
        $other = Clinic::factory()->create(['name' => 'Poliklinik Lain']);
        $doctor = $this->createDoctor(['name' => 'Rani Oktaviani'], null, [$a, $b]);

        foreach (range(0, 13) as $offset) {
            $date = CarbonImmutable::today()->addDays($offset)->toDateString();
            $this->sessionOf($doctor, $date);
            $this->sessionOf($doctor, $date, ['start_time' => '13:00:00', 'end_time' => '15:00:00', 'clinic_id' => $other->id]);
        }

        $html = $this->get($this->url($doctor))->assertOk()->getContent();

        $this->assertSame(28, substr_count($html, '<th scope="row"'));
        $this->assertStringContainsString('Poliklinik Lain', $html);
    }

    public function test_the_page_has_no_inline_handlers(): void
    {
        $doctor = $this->createDoctor();
        $this->sessionOf($doctor, '2026-10-07');

        $html = $this->get($this->url($doctor))->assertOk()->getContent();

        $this->assertSame(0, preg_match('/\son[a-z]+\s*=/i', $html));
        $this->assertStringNotContainsString('onerror', $html);
        $this->assertStringNotContainsString('javascript:', $html);
    }
}
