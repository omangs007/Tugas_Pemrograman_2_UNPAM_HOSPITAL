<?php

namespace Tests\Feature\Public;

use App\Enums\ClinicType;
use App\Enums\DoctorStatus;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\User;
use App\Services\Media\MediaService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\Support\ImageFixtures;
use Tests\Support\InteractsWithMasterData;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

/**
 * Halaman #5 `clinics.show` (05 hal. 5, 07 W-06, A-42).
 */
class PublicClinicDetailTest extends TestCase
{
    use InteractsWithMasterData;
    use InteractsWithStaff;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    private function url(Clinic $clinic): string
    {
        return route('clinics.show', $clinic->slug);
    }

    private function fakeRoute(string $name, string $uri): void
    {
        if (! Route::has($name)) {
            Route::middleware('web')->get($uri, fn () => 'x')->name($name);
            Route::getRoutes()->refreshNameLookups();
        }
    }

    // --- 404 (A-42) --------------------------------------------------------------------------

    public function test_inactive_clinic_is_404_for_guests_patients_and_every_staff_role(): void
    {
        $clinic = $this->createClinic(['name' => 'Poliklinik Tersembunyi', 'is_active' => false]);

        $this->get($this->url($clinic))->assertNotFound()->assertDontSee('Poliklinik Tersembunyi');

        foreach (['content-admin', 'registration-officer', 'super-admin'] as $role) {
            $this->actingAs($this->createStaff($role))->get($this->url($clinic))->assertNotFound();
        }

        $this->actingAs(User::factory()->patient()->create())->get($this->url($clinic))->assertNotFound();
    }

    public function test_unknown_slug_is_404(): void
    {
        $this->get('/layanan/slug-yang-tidak-ada')->assertNotFound();
    }

    // --- isi halaman -------------------------------------------------------------------------

    public function test_show_renders_the_clinic_details(): void
    {
        $clinic = $this->createClinic([
            'name' => 'Poliklinik Anak',
            'code' => 'ANK',
            'location' => 'Gedung A Lt. 1',
            'short_description' => 'Konsultasi tumbuh kembang anak.',
            'is_bookable' => true,
            'description' => '<p>Layanan <strong>ramah</strong> anak.</p><ul><li>Imunisasi</li></ul>',
        ]);

        $response = $this->get($this->url($clinic))->assertOk();

        $response->assertSee('Poliklinik Anak')
            ->assertSee('Gedung A Lt. 1')
            ->assertSee('Kode antrean: ANK')
            ->assertSee('Konsultasi tumbuh kembang anak.')
            ->assertSee('<strong>ramah</strong>', false)
            ->assertSee('Imunisasi')
            ->assertSee('Dapat didaftar online')
            ->assertSee('Informasi layanan');
        $this->assertSame(1, substr_count($response->getContent(), '<h1'));
    }

    public function test_description_is_rendered_sanitized(): void
    {
        $clinic = $this->createClinic([
            'description' => '<p>Halo <strong>dunia</strong></p><script>alert(1)</script><img src="x" onerror="alert(1)"><a href="javascript:alert(1)">klik</a>',
        ]);

        $this->assertStringNotContainsString('<script', (string) $clinic->fresh()->description);

        $this->get($this->url($clinic))
            ->assertOk()
            ->assertSee('<strong>dunia</strong>', false)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertDontSee('onerror', false)
            ->assertDontSee('javascript:', false);
    }

    public function test_description_updated_through_the_model_is_sanitized_before_rendering(): void
    {
        $clinic = $this->createClinic(['description' => '<p>Awal</p>']);
        $clinic->update(['description' => '<p onclick="x()">Baru</p><script>alert(2)</script>']);

        $this->get($this->url($clinic))
            ->assertOk()
            ->assertSee('Baru')
            ->assertDontSee('onclick', false)
            ->assertDontSee('alert(2)', false);
    }

    public function test_empty_description_renders_only_the_summary(): void
    {
        $clinic = $this->createClinic(['description' => null, 'short_description' => 'Ringkasan saja.']);

        $this->get($this->url($clinic))
            ->assertOk()
            ->assertSee('Ringkasan saja.')
            ->assertDontSee('clinic-description', false);
    }

    // --- varian penunjang (W-06) -------------------------------------------------------------

    public function test_support_clinic_shows_the_contact_note_instead_of_registration(): void
    {
        $clinic = $this->createClinic(['name' => 'Laboratorium', 'type' => ClinicType::Penunjang, 'is_bookable' => false, 'code' => 'LAB']);

        $this->get($this->url($clinic))
            ->assertOk()
            ->assertSee('Layanan ini tidak dapat didaftar online. Hubungi kami di (021) 0000-0000.')
            ->assertDontSee('Kode antrean')
            ->assertDontSee('Dapat didaftar online')
            ->assertDontSee('Dokter di poliklinik ini');
    }

    public function test_bookable_clinic_without_public_doctors_shows_the_empty_state(): void
    {
        $clinic = $this->createClinic(['is_bookable' => true]);

        $this->get($this->url($clinic))
            ->assertOk()
            ->assertSee('Dokter di poliklinik ini')
            ->assertSee('Belum ada dokter terjadwal');
    }

    public function test_registration_and_schedule_links_are_hidden_while_their_routes_do_not_exist(): void
    {
        if (Route::has('patient.appointments.create') || Route::has('schedules.index')) {
            $this->markTestSkipped('Route fase berikutnya sudah ada: tautan diuji di tes fase tersebut.');
        }

        $clinic = $this->createClinic(['is_bookable' => true]);

        $this->get($this->url($clinic))
            ->assertOk()
            ->assertDontSee('?poli=', false)
            ->assertDontSee('Daftar Kunjungan')
            ->assertDontSee('Jadwal Dokter');
    }

    public function test_registration_and_schedule_links_carry_the_clinic_slug_when_routes_exist(): void
    {
        $this->fakeRoute('patient.appointments.create', '/pasien/pendaftaran/baru');
        $this->fakeRoute('schedules.index', '/jadwal-dokter');
        $bookable = $this->createClinic(['is_bookable' => true]);
        $support = $this->createClinic(['type' => ClinicType::Penunjang, 'is_bookable' => false]);

        $this->get($this->url($bookable))
            ->assertOk()
            ->assertSee('href="'.route('patient.appointments.create', ['poli' => $bookable->slug]).'"', false)
            ->assertSee('href="'.route('schedules.index', ['poli' => $bookable->slug]).'"', false);

        $this->get($this->url($support))
            ->assertOk()
            ->assertDontSee('?poli='.$support->slug, false);
    }

    // --- dokter (F2-R9, F2-R28) --------------------------------------------------------------

    public function test_only_publicly_visible_doctors_of_the_clinic_are_listed(): void
    {
        $clinic = $this->createClinic(['name' => 'Poliklinik Uji']);
        $anak = $this->createSpecialty(['name' => 'Anak']);
        $umum = $this->createSpecialty(['name' => 'Dokter Umum']);
        $inactiveClinic = $this->createClinic(['is_active' => false]);

        $visible = $this->createDoctor(['name' => 'Laras Wulandari', 'title_prefix' => 'dr.', 'title_suffix' => 'Sp.A'], $clinic, [$anak, $umum]);
        $this->createDoctor(['name' => 'Nonaktif Satu', 'title_prefix' => 'dr.', 'title_suffix' => null, 'status' => DoctorStatus::Inactive], $clinic);
        $this->createDoctor(['name' => 'Arsip Dua', 'title_prefix' => 'dr.', 'title_suffix' => null, 'status' => DoctorStatus::Archived, 'archived_at' => now()->subDays(3)], $clinic);
        $this->createDoctor(['name' => 'Poli Lain Tiga', 'title_prefix' => 'dr.', 'title_suffix' => null], $inactiveClinic);

        $this->assertSame([$visible->id], Doctor::query()->publiclyVisible()->pluck('id')->all());

        $this->get($this->url($clinic))
            ->assertOk()
            ->assertSee('dr. Laras Wulandari, Sp.A')
            ->assertSee('Anak')
            ->assertDontSee('Dokter Umum')
            ->assertDontSee('Nonaktif Satu')
            ->assertDontSee('Arsip Dua')
            ->assertDontSee('Poli Lain Tiga');
    }

    public function test_doctor_cards_link_to_the_doctor_page_only_when_the_route_exists(): void
    {
        $clinic = $this->createClinic();
        $doctor = $this->createDoctor([], $clinic);

        if (! Route::has('doctors.show')) {
            $this->get($this->url($clinic))->assertOk()->assertDontSee('/dokter/'.$doctor->slug, false);
        }

        $this->fakeRoute('doctors.show', '/dokter/{doctor:slug}');

        $this->get($this->url($clinic))->assertOk()->assertSee('href="'.url('/dokter/'.$doctor->slug).'"', false);
    }

    // --- gambar (MED-08) ---------------------------------------------------------------------

    public function test_missing_cover_and_photo_use_local_placeholders(): void
    {
        $clinic = $this->createClinic();
        $this->createDoctor([], $clinic);

        $this->get($this->url($clinic))
            ->assertOk()
            ->assertSee('images/placeholders/clinic.svg', false)
            ->assertSee('images/placeholders/doctor.svg', false)
            ->assertDontSee('og:image', false);
    }

    public function test_cover_file_missing_on_disk_keeps_alt_and_a_delegated_fallback(): void
    {
        $clinic = $this->createClinic(['name' => 'Poliklinik Anak']);
        $media = $this->app->make(MediaService::class)->store(ImageFixtures::jpeg(1200, 800, 'cover.jpg'), 'cover', $clinic, ['alt_text' => 'Foto contoh Poliklinik Anak']);
        Storage::disk('public')->delete([$media->directory.'/'.$media->file_name, (string) $media->thumb_path]);

        $this->get($this->url($clinic))
            ->assertOk()
            ->assertSee('alt="Foto contoh Poliklinik Anak"', false)
            ->assertSee('data-fallback="'.asset('images/placeholders/clinic.svg').'"', false)
            ->assertDontSee('onerror', false);
    }

    // --- galeri ------------------------------------------------------------------------------

    public function test_gallery_renders_thumbnails_and_one_modal_carousel(): void
    {
        $clinic = $this->createClinic();
        $media = $this->app->make(MediaService::class);

        foreach (range(1, 3) as $number) {
            $media->store(ImageFixtures::jpeg(900, 600, "g{$number}.jpg"), 'gallery', $clinic, ['alt_text' => "Foto galeri {$number}"]);
        }

        $html = $this->get($this->url($clinic))->assertOk()->getContent();

        $this->assertStringContainsString('<h2 id="galeri-judul"', $html);
        $this->assertStringContainsString('id="galeri-modal"', $html);
        $this->assertStringContainsString('data-gallery-modal', $html);
        $this->assertSame(3, substr_count($html, 'data-gallery-index='));
        $this->assertSame(3, substr_count($html, 'class="carousel-item'));
        $this->assertStringContainsString('alt="Foto galeri 2"', $html);
        $this->assertStringContainsString('Perbesar foto 1 dari 3: Foto galeri 1', $html);
    }

    public function test_page_without_gallery_has_no_modal(): void
    {
        $clinic = $this->createClinic();

        $this->get($this->url($clinic))->assertOk()->assertDontSee('galeri-modal', false)->assertDontSee('Galeri');
    }

    // --- SEO (05 §7) dan breadcrumb (05 §8.1) ------------------------------------------------

    public function test_title_description_and_og_tags_follow_the_seo_rules(): void
    {
        $clinic = $this->createClinic(['name' => 'Poliklinik Anak', 'short_description' => 'Konsultasi tumbuh kembang anak.', 'meta_title' => null, 'meta_description' => null]);
        $this->app->make(MediaService::class)->store(ImageFixtures::jpeg(1200, 800, 'cover.jpg'), 'cover', $clinic, ['alt_text' => 'Foto contoh Poliklinik Anak']);

        $this->get($this->url($clinic))
            ->assertOk()
            ->assertSee('<title>Poliklinik Anak — '.config('app.name').'</title>', false)
            ->assertSee('<meta name="description" content="Konsultasi tumbuh kembang anak.">', false)
            ->assertSee('<meta property="og:title" content="Poliklinik Anak">', false)
            ->assertSee('<meta property="og:image" content="', false);
    }

    public function test_meta_fields_override_the_defaults_and_the_description_is_cut_at_160(): void
    {
        $custom = $this->createClinic(['name' => 'Poliklinik Mata', 'meta_title' => 'Dokter Mata Terdekat', 'meta_description' => 'Deskripsi SEO khusus.']);
        $long = $this->createClinic(['short_description' => str_repeat('Layanan unggulan ', 15)]);

        $this->get($this->url($custom))
            ->assertOk()
            ->assertSee('<title>Dokter Mata Terdekat — '.config('app.name').'</title>', false)
            ->assertSee('<meta name="description" content="Deskripsi SEO khusus.">', false);

        $html = $this->get($this->url($long))->assertOk()->getContent();
        preg_match('/<meta name="description" content="([^"]*)">/', $html, $match);

        $this->assertNotEmpty($match);
        $this->assertLessThanOrEqual(160, mb_strlen(html_entity_decode($match[1])));
    }

    public function test_breadcrumb_trail_ends_with_the_clinic_name_without_a_link(): void
    {
        $clinic = $this->createClinic(['name' => 'Poliklinik Saraf']);

        $this->get($this->url($clinic))
            ->assertOk()
            ->assertSeeInOrder(['aria-label="Breadcrumb"', 'Beranda', 'Layanan', 'Poliklinik Saraf'], false)
            ->assertSee('<li class="breadcrumb-item active" aria-current="page">Poliklinik Saraf</li>', false)
            ->assertSee('<li class="breadcrumb-item"><a href="'.route('clinics.index').'">Layanan</a></li>', false);
    }

    // --- mode ketat dengan banyak baris + CSP ------------------------------------------------

    public function test_show_renders_many_doctors_with_photos_and_gallery_in_strict_mode(): void
    {
        $this->assertTrue(Model::preventsLazyLoading());

        $clinic = $this->createClinic(['name' => 'Poliklinik Ramai']);
        $media = $this->app->make(MediaService::class);
        $media->store(ImageFixtures::jpeg(1200, 800, 'cover.jpg'), 'cover', $clinic, ['alt_text' => 'Foto contoh Poliklinik Ramai']);
        $media->store(ImageFixtures::jpeg(900, 600, 'g.jpg'), 'gallery', $clinic, ['alt_text' => 'Galeri satu']);
        $anak = $this->createSpecialty(['name' => 'Anak']);
        $umum = $this->createSpecialty(['name' => 'Dokter Umum']);

        foreach (range(1, 6) as $number) {
            $doctor = $this->createDoctor(['name' => "Dokter Uji {$number}", 'title_prefix' => 'dr.', 'title_suffix' => null, 'sort_order' => $number], $clinic, [$anak, $umum]);
            $media->store(ImageFixtures::jpeg(600, 600, 'p.jpg'), 'photo', $doctor, ['alt_text' => "Foto dokter {$number}"]);
        }

        $html = $this->get($this->url($clinic))->assertOk()->getContent();

        $this->assertSame(6, substr_count($html, 'doctor-card"'));
        $this->assertStringContainsString('dr. Dokter Uji 6', $html);
    }

    public function test_inactive_primary_specialty_is_hidden_and_the_active_one_is_shown_on_the_doctor_card(): void
    {
        $clinic = $this->createClinic();
        $hidden = $this->createSpecialty(['name' => 'Spesialis Nonaktif Utama', 'is_active' => false]);
        $shown = $this->createSpecialty(['name' => 'Spesialis Aktif Tambahan']);
        $this->createDoctor(['name' => 'Dokter Dua Spesialis'], $clinic, [$hidden, $shown]);

        $html = (string) $this->get($this->url($clinic))->assertOk()->assertSee('Dokter Dua Spesialis')->getContent();

        $this->assertStringContainsString('Spesialis Aktif Tambahan', $html);
        $this->assertStringNotContainsString('Spesialis Nonaktif Utama', $html);
    }

    public function test_doctor_whose_only_specialty_is_inactive_has_no_badge_but_the_card_still_renders(): void
    {
        $clinic = $this->createClinic();
        $hidden = $this->createSpecialty(['name' => 'Spesialis Nonaktif Tunggal', 'is_active' => false]);
        $this->createDoctor(['name' => 'Dokter Tanpa Lencana'], $clinic, [$hidden]);

        $html = (string) $this->get($this->url($clinic))->assertOk()->assertSee('Dokter Tanpa Lencana')->getContent();

        $this->assertStringNotContainsString('Spesialis Nonaktif Tunggal', $html);
        $this->assertStringNotContainsString('badge text-bg-light border', $html);
    }

    public function test_rendered_pages_are_compatible_with_the_strict_csp(): void
    {
        $clinic = $this->createClinic(['description' => '<p>Isi</p>']);
        $this->app->make(MediaService::class)->store(ImageFixtures::jpeg(900, 600, 'g.jpg'), 'gallery', $clinic, ['alt_text' => 'Galeri']);

        foreach ([route('clinics.index'), $this->url($clinic)] as $uri) {
            $html = (string) $this->get($uri)->assertOk()->getContent();

            $this->assertSame(0, preg_match('/\son[a-z]+\s*=/i', $html), "Atribut event inline ditemukan di {$uri}.");
            $this->assertStringNotContainsString('javascript:', $html);
            $this->assertStringNotContainsString('<script>', $html);
            $this->assertStringNotContainsString('blob:', $html);
        }
    }
}
