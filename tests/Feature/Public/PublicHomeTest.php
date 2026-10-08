<?php

namespace Tests\Feature\Public;

use App\Enums\ArticleStatus;
use App\Http\Controllers\Public\HomeController;
use App\Models\Clinic;
use App\Models\PracticeSession;
use App\Models\User;
use App\Services\Scheduling\PracticeSessionGenerator;
use App\Services\Settings\SettingsService;
use Carbon\CarbonImmutable;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\Support\InteractsWithContent;
use Tests\Support\InteractsWithFacilities;
use Tests\Support\InteractsWithMasterData;
use Tests\Support\InteractsWithMedia;
use Tests\Support\InteractsWithSchedules;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

/**
 * Halaman #1 `home` (05 hal. 1, 07 W-02, F4A-R15 s.d. F4A-R17): hero (h1 dari `pages.home`, tiga tombol utama,
 * carousel banner), pencarian dokter cepat, enam layanan, empat dokter beserta sesi terdekat, tiga artikel terbaru,
 * dan CTA bantuan. Mencakup CRD-09 (banner), EMP-01, Review Focus 1 dan 3, serta pemicu pembangkitan sesi harian.
 */
class PublicHomeTest extends TestCase
{
    use InteractsWithContent;
    use InteractsWithFacilities;
    use InteractsWithMasterData;
    use InteractsWithMedia;
    use InteractsWithSchedules;
    use InteractsWithStaff;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->travelTo('2026-10-05 10:00:00'); // Senin
        $this->markHorizonGenerated();
    }

    /** Menandai pembangkitan harian sudah berjalan agar sesi buatan tes tidak bertambah oleh templat pabrik. */
    private function markHorizonGenerated(): void
    {
        $today = CarbonImmutable::today();
        Cache::put(PracticeSessionGenerator::markerKey($today), true, $today->endOfDay());
    }

    /** Potongan `<section aria-labelledby="...">` sampai `</section>` pertama setelahnya. */
    private function section(string $html, string $labelledBy): string
    {
        $found = preg_match('#<section[^>]*aria-labelledby="'.preg_quote($labelledBy, '#').'".*?</section>#s', $html, $matches);

        $this->assertSame(1, $found, "Bagian {$labelledBy} tidak ditemukan.");

        return $matches[0];
    }

    /** @param  list<string>  $needles */
    private function assertInOrder(array $needles, string $haystack): void
    {
        $offset = -1;

        foreach ($needles as $needle) {
            $position = strpos($haystack, $needle);

            $this->assertNotFalse($position, "{$needle} tidak ditemukan.");
            $this->assertGreaterThan($offset, $position, "{$needle} berada di urutan yang salah.");
            $offset = $position;
        }
    }

    private function fakeRoute(string $name, string $uri): void
    {
        if (! Route::has($name)) {
            Route::middleware('web')->get($uri, fn () => 'x')->name($name);
            Route::getRoutes()->refreshNameLookups();
        }
    }

    // --- DB kosong (EMP-01) --------------------------------------------------------------------

    public function test_an_empty_database_renders_the_static_hero_and_hides_every_optional_block(): void
    {
        $response = $this->get('/')->assertOk();
        $html = $response->getContent();

        $response->assertSee('<h1 id="judul-beranda" class="display-5 fw-bold text-primary">UNPAM HOSPITAL</h1>', false)
            ->assertSee('<p class="lead mb-4">Layanan kesehatan yang ramah dan terpercaya (contoh)</p>', false)
            ->assertSee('Cari Dokter')
            ->assertSee('Jadwal Dokter')
            ->assertSee('Cari dokter cepat')
            ->assertDontSee('id="banner-carousel"', false)
            ->assertDontSee('Layanan Unggulan')
            ->assertDontSee('Dokter Kami')
            ->assertDontSee('Artikel Terbaru')
            ->assertDontSee('Situs ini masih dalam tahap pembangunan.');

        $this->assertSame(1, substr_count($html, '<h1'));

        // Route `contact` ada (fase 4b): blok bantuan tetap tampil dengan satu tombol "Kirim Pesan" dan tanpa kontak pengaturan.
        $cta = $this->section($html, 'judul-bantuan');
        $this->assertStringContainsString('</i> Kirim Pesan</a>', $cta);

        foreach (['wa.me', 'IGD:', 'Petunjuk Arah', 'Telepon</a>'] as $absent) {
            $this->assertStringNotContainsString($absent, $cta);
        }
        // Tiga tombol utama: Cari Dokter, Jadwal Dokter, dan Daftar Kunjungan (route pendaftaran ada sejak fase 6, F6-R31).
        $this->assertSame(3, substr_count($html, ' btn-lg"'));
    }

    public function test_the_page_is_the_same_for_guests_patients_and_staff(): void
    {
        $this->createPage('home', ['title' => 'Selamat Datang Uji']);

        $this->get('/')->assertOk()->assertSee('Selamat Datang Uji');
        $this->actingAs(User::factory()->patient()->create())->get('/')->assertOk()->assertSee('Selamat Datang Uji');

        foreach (['content-admin', 'registration-officer', 'super-admin'] as $role) {
            $this->actingAs($this->createStaff($role))->get('/')->assertOk()->assertSee('Selamat Datang Uji');
        }
    }

    // --- judul, h1, SEO (F4A-R5, F4A-R17) -------------------------------------------------------

    public function test_the_title_has_no_suffix_and_meta_title_overrides_the_site_name_and_tagline(): void
    {
        $this->seed(SettingsSeeder::class);

        $this->get('/')->assertOk()->assertSee('<title>UNPAM HOSPITAL — Layanan kesehatan yang ramah dan terpercaya</title>', false);

        $this->createPage('home', ['meta_title' => 'Rumah Sakit Contoh Pamulang']);

        $this->get('/')
            ->assertOk()
            ->assertSee('<title>Rumah Sakit Contoh Pamulang</title>', false)
            ->assertSee('<meta property="og:title" content="Rumah Sakit Contoh Pamulang">', false)
            ->assertSee('<link rel="canonical" href="'.route('home').'">', false);
    }

    public function test_the_meta_description_follows_the_home_row_then_the_site_default(): void
    {
        $this->seed(SettingsSeeder::class);

        // Tanpa baris pages: teks bawaan 13 §8.8.
        $this->get('/')->assertSee('<meta name="description" content="Informasi layanan, dokter, jadwal praktik, dan pendaftaran kunjungan UNPAM HOSPITAL.">', false);

        $page = $this->createPage('home', ['meta_description' => 'Deskripsi beranda khusus untuk uji.']);

        $this->get('/')
            ->assertSee('<meta name="description" content="Deskripsi beranda khusus untuk uji.">', false)
            ->assertSee('<meta property="og:description" content="Deskripsi beranda khusus untuk uji.">', false);

        $page->forceFill(['meta_description' => null])->save();

        $this->get('/')->assertSee('<meta name="description" content="UNPAM HOSPITAL — informasi layanan, dokter, jadwal praktik, dan pendaftaran kunjungan rawat jalan (situs prototipe).">', false);
    }

    public function test_the_h1_and_subtitle_come_from_the_home_row_and_fall_back_to_the_tagline(): void
    {
        $this->seed(SettingsSeeder::class);
        $page = $this->createPage('home', ['title' => 'Selamat Datang di Rumah Sakit Contoh', 'subtitle' => 'Kesehatan Anda adalah prioritas kami']);

        $response = $this->get('/')->assertOk()
            ->assertSee('<h1 id="judul-beranda" class="display-5 fw-bold text-primary">Selamat Datang di Rumah Sakit Contoh</h1>', false)
            ->assertSee('<p class="lead mb-4">Kesehatan Anda adalah prioritas kami</p>', false);
        $this->assertSame(1, substr_count($response->getContent(), '<h1'));

        $page->forceFill(['subtitle' => null])->save();

        $this->get('/')->assertSee('<p class="lead mb-4">Layanan kesehatan yang ramah dan terpercaya</p>', false);
    }

    // --- banner (CRD-09, Review Focus 1) --------------------------------------------------------

    public function test_only_currently_active_banners_are_in_the_carousel_at_the_boundaries(): void
    {
        $this->createBanner(['title' => 'Banner Tanpa Periode', 'sort_order' => 1]);
        $this->createBanner(['title' => 'Banner Mulai Tepat Sekarang', 'sort_order' => 2, 'starts_at' => now()]);
        $this->createBanner(['title' => 'Banner Berakhir Tepat Sekarang', 'sort_order' => 3, 'ends_at' => now()]);
        $this->createBanner(['title' => 'Banner Berakhir Semenit Lalu', 'sort_order' => 4, 'ends_at' => now()->subMinute()]);
        $this->createBanner(['title' => 'Banner Mulai Semenit Lagi', 'sort_order' => 5, 'starts_at' => now()->addMinute()]);
        $this->createBanner(['title' => 'Banner Nonaktif Dalam Jendela', 'sort_order' => 6, 'is_active' => false, 'starts_at' => now()->subDay(), 'ends_at' => now()->addDay()]);

        $response = $this->get('/')->assertOk();
        $html = $response->getContent();

        $response->assertSeeInOrder(['Banner Tanpa Periode', 'Banner Mulai Tepat Sekarang', 'Banner Berakhir Tepat Sekarang'])
            ->assertDontSee('Banner Berakhir Semenit Lalu')
            ->assertDontSee('Banner Mulai Semenit Lagi')
            ->assertDontSee('Banner Nonaktif Dalam Jendela');
        $this->assertSame(3, substr_count($html, 'aria-roledescription="slide"'));
    }

    public function test_the_carousel_markup_follows_the_accessibility_contract(): void
    {
        $this->createBanner(['title' => 'Slide Pertama Uji', 'subtitle' => 'Keterangan slide pertama.', 'sort_order' => 1]);
        $this->createBanner(['title' => 'Slide Kedua Uji', 'sort_order' => 2]);

        $html = $this->get('/')->assertOk()->getContent();

        foreach ([
            'id="banner-carousel"',
            'role="region"',
            'aria-roledescription="carousel"',
            'aria-label="Banner"',
            ' data-banner-carousel',
            'data-banner-live aria-live="off"',
            'aria-roledescription="slide" aria-label="1 dari 2"',
            'aria-roledescription="slide" aria-label="2 dari 2"',
            'data-bs-slide-to="0"',
            'data-bs-slide-to="1"',
            'aria-label="Slide 1"',
            'aria-label="Slide 2"',
            'data-bs-slide="prev"',
            'data-bs-slide="next"',
            'data-banner-toggle',
            'aria-pressed="false"',
            'Jeda',
        ] as $needle) {
            $this->assertStringContainsString($needle, $html, "Markup carousel harus memuat {$needle}");
        }

        // Putaran dikendalikan app.js (bukan data-bs-ride); tombol Jeda tersembunyi sampai JavaScript aktif.
        $this->assertStringNotContainsString('data-bs-ride', $html);
        $this->assertMatchesRegularExpression('/<button[^>]*data-banner-toggle[^>]*\shidden[\s>]/', $html);

        // Judul slide bukan heading: satu-satunya h1 tetap judul halaman, dan tidak ada h2 berisi judul slide.
        $this->assertStringContainsString('<p class="h2 mb-1">Slide Pertama Uji</p>', $html);
        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertDoesNotMatchRegularExpression('/<h[1-6][^>]*>\s*Slide (Pertama|Kedua) Uji/', $html);
    }

    public function test_the_first_slide_image_loads_eagerly_and_the_others_lazily_with_the_banner_alt(): void
    {
        $this->createBanner(['title' => 'Slide Gambar Satu', 'sort_order' => 1]);
        $this->createBanner(['title' => 'Slide Gambar Dua', 'sort_order' => 2]);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertSame(2, preg_match_all('/<img[^>]*images\/placeholders\/banner\.svg[^>]*>/', $html, $matches));
        $this->assertStringNotContainsString('loading="lazy"', $matches[0][0]);
        $this->assertStringContainsString('loading="lazy"', $matches[0][1]);
        $this->assertStringContainsString('alt="Slide Gambar Satu"', $matches[0][0]);
        $this->assertStringContainsString('alt="Slide Gambar Dua"', $matches[0][1]);
    }

    public function test_a_single_banner_renders_without_controls_or_script_hooks(): void
    {
        $this->createBanner(['title' => 'Satu-satunya Slide Uji']);

        $this->get('/')
            ->assertOk()
            ->assertSee('Satu-satunya Slide Uji')
            ->assertSee('aria-roledescription="carousel"', false)
            ->assertDontSee(' data-banner-carousel', false)
            ->assertDontSee('data-banner-toggle', false)
            ->assertDontSee('data-bs-slide-to', false);
    }

    public function test_the_banner_media_alt_and_the_open_graph_image_are_used(): void
    {
        $banner = $this->createBanner(['title' => 'Banner Bergambar Uji']);
        $media = $this->createMediaRow([
            'model_type' => $banner->getMorphClass(),
            'model_id' => $banner->id,
            'collection' => 'image',
            'directory' => 'image/2026/10',
            'alt_text' => 'Pemandangan lobi rumah sakit',
            'width' => 1600,
            'height' => 600,
        ]);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('alt="Pemandangan lobi rumah sakit"', $html);
        $this->assertStringContainsString('src="'.$media->url.'"', $html);
        $this->assertStringContainsString('data-fallback="'.asset('images/placeholders/banner.svg').'"', $html);
        $this->assertStringContainsString('<meta property="og:image" content="'.url($media->url).'">', $html);
    }

    public function test_the_banner_button_appears_only_for_a_usable_link(): void
    {
        $this->createBanner(['title' => 'Slide Rute Ada', 'sort_order' => 1, 'button_label' => 'Temukan Dokter', 'button_url' => '/dokter']);
        $this->createBanner(['title' => 'Slide Rute Belum Ada', 'sort_order' => 2, 'button_label' => 'Menuju Halaman Hilang', 'button_url' => '/halaman-tidak-ada']);
        $this->createBanner(['title' => 'Slide Tautan Https', 'sort_order' => 3, 'button_label' => 'Buka Promo', 'button_url' => 'https://contoh.test/promo']);
        $this->createBanner(['title' => 'Slide Label Saja', 'sort_order' => 4, 'button_label' => 'Label Tanpa Tautan', 'button_url' => null]);

        $this->get('/')
            ->assertOk()
            ->assertSee('<a class="btn btn-light mt-3" href="/dokter">Temukan Dokter</a>', false)
            ->assertSee('<a class="btn btn-light mt-3" href="https://contoh.test/promo" rel="noopener noreferrer">Buka Promo</a>', false)
            ->assertDontSee('Menuju Halaman Hilang')
            ->assertDontSee('Label Tanpa Tautan');
    }

    // --- layanan unggulan (F4A-R16) --------------------------------------------------------------

    public function test_featured_clinics_are_the_first_six_active_ones_in_display_order_regardless_of_type(): void
    {
        Clinic::factory()->support()->create(['name' => 'Laboratorium Uji', 'sort_order' => 0]);
        Clinic::factory()->inactive()->create(['name' => 'Poli Nonaktif Uji', 'sort_order' => 0]);

        foreach (range(1, 8) as $number) {
            $this->createClinic(['name' => "Poli Uji {$number}", 'sort_order' => $number]);
        }

        $html = $this->get('/')->assertOk()->getContent();
        $section = $this->section($html, 'judul-layanan-unggulan');

        $this->assertInOrder(['Laboratorium Uji', 'Poli Uji 1', 'Poli Uji 2', 'Poli Uji 3', 'Poli Uji 4', 'Poli Uji 5'], $section);
        $this->assertSame(6, substr_count($section, 'class="card h-100 service-card"'));

        foreach (['Poli Uji 6', 'Poli Uji 7', 'Poli Uji 8', 'Poli Nonaktif Uji'] as $hidden) {
            $this->assertStringNotContainsString($hidden, $section);
        }

        $this->assertStringContainsString('<h2 id="judul-layanan-unggulan"', $section);
        $this->assertStringContainsString('href="'.route('clinics.index').'">Lihat semua layanan</a>', $section);
    }

    // --- dokter (F4A-R16) ------------------------------------------------------------------------

    public function test_four_doctors_with_their_next_session_are_shown_in_display_order(): void
    {
        $doctors = [];

        foreach (['Satu', 'Dua', 'Tiga', 'Empat', 'Lima'] as $index => $word) {
            $doctors[$word] = $this->createDoctor(['name' => "Uji {$word}", 'title_prefix' => 'dr.', 'title_suffix' => null, 'sort_order' => $index + 1]);
        }

        $this->createDoctor(['name' => 'Uji Nonaktif', 'title_prefix' => 'dr.', 'title_suffix' => null, 'sort_order' => 0, 'status' => 'inactive']);

        $this->createSession([
            'doctor_id' => $doctors['Satu']->id,
            'clinic_id' => $doctors['Satu']->clinic_id,
            'session_date' => '2026-10-06',
            'start_time' => '09:00:00',
            'end_time' => '12:00:00',
            'quota' => 20,
        ]);

        $html = $this->get('/')->assertOk()->getContent();
        $section = $this->section($html, 'judul-dokter-kami');

        $this->assertInOrder(['dr. Uji Satu', 'dr. Uji Dua', 'dr. Uji Tiga', 'dr. Uji Empat'], $section);
        $this->assertStringNotContainsString('Uji Lima', $section);
        $this->assertStringNotContainsString('Uji Nonaktif', $section);
        $this->assertStringContainsString('href="'.route('doctors.index').'">Lihat semua dokter</a>', $section);

        // Hanya dr. Uji Satu yang punya sesi: lencana, tanggal, dan tanpa tombol "Lihat Jadwal" terpisah.
        $this->assertSame(1, substr_count($section, 'Jadwal terdekat'));
        $this->assertStringContainsString('Selasa, 6 Oktober 2026', $section);
        $this->assertStringContainsString('Tersedia', $section);
        $this->assertStringNotContainsString('Lihat Jadwal', $section);
    }

    public function test_the_page_query_parameter_does_not_shift_the_featured_doctors(): void
    {
        foreach (['Satu', 'Dua', 'Tiga', 'Empat', 'Lima'] as $index => $word) {
            $this->createDoctor(['name' => "Uji {$word}", 'title_prefix' => 'dr.', 'title_suffix' => null, 'sort_order' => $index + 1]);
        }

        foreach (['/?page=2', '/?page=abc', '/?page[]=1', '/?page=-5'] as $uri) {
            $section = $this->section($this->get($uri)->assertOk()->getContent(), 'judul-dokter-kami');

            $this->assertStringContainsString('dr. Uji Satu', $section, $uri);
            $this->assertStringContainsString('dr. Uji Empat', $section, $uri);
            $this->assertStringNotContainsString('Uji Lima', $section, $uri);
        }
    }

    // --- artikel terbaru (Review Focus 1) ---------------------------------------------------------

    public function test_the_three_newest_published_articles_are_shown_and_unpublished_ones_never(): void
    {
        $gizi = $this->createArticleCategory(['name' => 'Gizi Uji']);

        $this->createArticle(['title' => 'Artikel Tepat Sekarang', 'published_at' => now()], $gizi);
        $this->createArticle(['title' => 'Artikel Semenit Lalu', 'published_at' => now()->subMinute()], $gizi);
        $this->createArticle(['title' => 'Artikel Tanpa Kategori', 'published_at' => now()->subDay()]);
        $this->createArticle(['title' => 'Artikel Terlalu Lama', 'published_at' => now()->subDays(5)], $gizi);
        $this->createArticle(['title' => 'Artikel Semenit Lagi', 'published_at' => now()->addMinute()], $gizi);
        $this->createArticle(['title' => 'Artikel Draf Berlabel Tanggal', 'status' => ArticleStatus::Draft, 'published_at' => now()->subHours(2)], $gizi);
        $this->createArticle(['title' => 'Artikel Terhapus', 'published_at' => now()->subHours(3)], $gizi)->delete();

        $html = $this->get('/')->assertOk()->getContent();
        $section = $this->section($html, 'judul-artikel-terbaru');

        $this->assertInOrder(['Artikel Tepat Sekarang', 'Artikel Semenit Lalu', 'Artikel Tanpa Kategori'], $section);

        foreach (['Artikel Terlalu Lama', 'Artikel Semenit Lagi', 'Artikel Draf Berlabel Tanggal', 'Artikel Terhapus'] as $hidden) {
            $this->assertStringNotContainsString($hidden, $section);
        }

        $this->assertStringContainsString('<h2 id="judul-artikel-terbaru"', $section);
        $this->assertStringContainsString('href="'.route('articles.index').'">Lihat semua artikel</a>', $section);
        $this->assertStringContainsString('Gizi Uji', $section);
    }

    // --- ketersediaan kamar (F4B-R31) ------------------------------------------------------------

    /** @param  array<string, mixed>  $overrides */
    private function homeRoom(int $number, array $overrides = []): void
    {
        $this->createRoomCategory(array_merge([
            'name' => "Kamar Beranda {$number}",
            'slug' => "kamar-beranda-{$number}",
            'bed_capacity' => 20,
            'beds_available' => 12,
            'sample_rate' => 400000,
            'rate_unit' => 'per malam',
            'availability_updated_at' => now()->subHours(2),
            'sort_order' => $number,
            'is_active' => true,
        ], $overrides));
    }

    public function test_the_room_block_is_hidden_without_active_categories(): void
    {
        $this->homeRoom(1, ['name' => 'Kamar Nonaktif Uji', 'is_active' => false]);

        $this->get('/')
            ->assertOk()
            ->assertDontSee('Ketersediaan Kamar Rawat Inap')
            ->assertDontSee('id="judul-kamar"', false)
            ->assertDontSee('Kamar Nonaktif Uji');
    }

    public function test_the_room_block_shows_the_first_four_active_categories_in_display_order(): void
    {
        foreach (range(1, 5) as $number) {
            $this->homeRoom($number);
        }

        $this->homeRoom(0, ['name' => 'Kamar Mati Uji', 'slug' => 'kamar-mati-uji', 'is_active' => false]);

        $section = $this->section($this->get('/')->assertOk()->getContent(), 'judul-kamar');

        $this->assertInOrder(['Kamar Beranda 1', 'Kamar Beranda 2', 'Kamar Beranda 3', 'Kamar Beranda 4'], $section);
        $this->assertSame(4, substr_count($section, 'class="card h-100 room-card"'));

        foreach (['Kamar Beranda 5', 'Kamar Mati Uji'] as $hidden) {
            $this->assertStringNotContainsString($hidden, $section);
        }

        $this->assertSame(4, HomeController::FEATURED_ROOMS);
    }

    public function test_each_room_card_has_the_name_link_the_occupancy_the_badge_and_the_update_label(): void
    {
        $this->homeRoom(1);
        $this->homeRoom(2, ['beds_available' => 0, 'availability_updated_at' => null]);

        $section = $this->section($this->get('/')->assertOk()->getContent(), 'judul-kamar');

        $this->assertStringContainsString('<h2 id="judul-kamar" class="mb-0">Ketersediaan Kamar Rawat Inap</h2>', $section);
        $this->assertStringContainsString('href="'.route('rooms.show', 'kamar-beranda-1').'">Kamar Beranda 1</a>', $section);
        $this->assertStringContainsString('12 dari 20 tempat tidur tersedia', $section);
        $this->assertStringContainsString('<span class="badge text-bg-success"><i class="bi bi-check-circle" aria-hidden="true"></i> Tersedia</span>', $section);
        $this->assertStringContainsString('Diperbarui manual oleh petugas pada 5 Okt 2026, pukul 08.00 WIB', $section);
        $this->assertStringContainsString('0 dari 20 tempat tidur tersedia', $section);
        $this->assertStringContainsString('<span class="badge text-bg-danger"><i class="bi bi-x-circle" aria-hidden="true"></i> Penuh</span>', $section);
        $this->assertStringContainsString('Belum diperbarui', $section);
    }

    public function test_the_room_block_has_the_short_notice_and_the_detail_link_without_forbidden_words(): void
    {
        $this->homeRoom(1);

        $section = $this->section($this->get('/')->assertOk()->getContent(), 'judul-kamar');

        $this->assertStringContainsString('Diperbarui manual oleh petugas. Hubungi rumah sakit untuk memastikan ketersediaan.', $section);
        $this->assertStringContainsString('href="'.route('rooms.index').'">Lihat detail kamar</a>', $section);
        $this->assertDoesNotMatchRegularExpression('/real[- ]?time|langsung|\blive\b/i', strip_tags($section));
    }

    public function test_the_room_block_sits_between_the_doctors_and_the_latest_articles(): void
    {
        $this->createDoctor(['name' => 'Uji Urutan', 'title_prefix' => 'dr.', 'title_suffix' => null]);
        $this->createArticle(['title' => 'Artikel Urutan Uji']);
        $this->homeRoom(1);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertInOrder(['id="judul-dokter-kami"', 'id="judul-kamar"', 'id="judul-artikel-terbaru"'], $html);
    }

    public function test_a_page_with_many_active_categories_renders_in_strict_mode(): void
    {
        foreach (range(1, 7) as $number) {
            $this->homeRoom($number);
        }

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertSame(4, substr_count($html, 'class="card h-100 room-card"'));
        $this->assertSame(1, substr_count($html, '<h1'));
    }

    // --- CTA bantuan -----------------------------------------------------------------------------

    public function test_the_help_block_uses_the_contact_settings(): void
    {
        $this->seed(SettingsSeeder::class);

        $cta = $this->section($this->get('/')->assertOk()->getContent(), 'judul-bantuan');

        $this->assertStringContainsString('Butuh bantuan segera?', $cta);
        $this->assertStringContainsString('IGD: ', $cta);
        $this->assertStringContainsString('<a class="text-white fw-bold" href="tel:02100000119">(021) 0000-0119</a>', $cta);
        $this->assertStringContainsString('href="https://wa.me/6280000000000" target="_blank" rel="noopener noreferrer"', $cta);
        $this->assertStringContainsString('href="tel:02100000000"', $cta);
        $this->assertStringContainsString('Petunjuk Arah', $cta);
        $this->assertStringContainsString(e(app(SettingsService::class)->string('map.directions_url')), $cta);
        if (! Route::has('contact')) {
            $this->assertStringNotContainsString('Kirim Pesan', $cta);
        }
    }

    public function test_help_block_entries_disappear_when_their_setting_is_empty(): void
    {
        $this->seed(SettingsSeeder::class);
        $settings = app(SettingsService::class);
        $settings->setMany(['contact.whatsapp' => null, 'map.directions_url' => null, 'contact.emergency_phone' => null]);

        $cta = $this->section($this->get('/')->assertOk()->getContent(), 'judul-bantuan');

        $this->assertStringNotContainsString('wa.me', $cta);
        $this->assertStringNotContainsString('Petunjuk Arah', $cta);
        $this->assertStringNotContainsString('IGD:', $cta);
        $this->assertStringContainsString('href="tel:02100000000"', $cta);
    }

    public function test_a_phone_number_without_digits_renders_no_empty_tel_link(): void
    {
        $this->seed(SettingsSeeder::class);
        app(SettingsService::class)->setMany(['contact.emergency_phone' => '-', 'contact.phone' => '-']);

        $cta = $this->section($this->get('/')->assertOk()->getContent(), 'judul-bantuan');

        $this->assertStringNotContainsString('href="tel:"', $cta);
        $this->assertStringContainsString('IGD:', $cta);
        $this->assertStringNotContainsString('Telepon</a>', $cta);
    }

    public function test_the_send_message_button_appears_only_when_the_contact_route_exists(): void
    {
        $this->seed(SettingsSeeder::class);

        if (! Route::has('contact')) {
            $this->assertStringNotContainsString('Kirim Pesan', $this->get('/')->assertOk()->getContent());
        }

        $this->fakeRoute('contact', '/_uji/kontak');

        $cta = $this->section($this->get('/')->assertOk()->getContent(), 'judul-bantuan');

        // Tautan memuat ikon sebelum label, sehingga href dan label diperiksa terpisah.
        $this->assertStringContainsString('href="'.route('contact').'">', $cta);
        $this->assertStringContainsString('</i> Kirim Pesan</a>', $cta);
    }

    // --- pencarian dokter cepat -------------------------------------------------------------------

    public function test_the_quick_search_is_a_get_form_to_the_doctor_directory_with_a_date_window(): void
    {
        $this->seed(SettingsSeeder::class);
        app(SettingsService::class)->set('booking.window_days', 14);
        $anak = $this->createSpecialty(['name' => 'Anak']);
        $this->createSpecialty(['name' => 'Spesialis Nonaktif Uji', 'is_active' => false]);
        $this->createClinic(['name' => 'Poliklinik Anak Uji']);
        Clinic::factory()->support()->create(['name' => 'Laboratorium Penunjang Uji']);

        $response = $this->get('/')->assertOk();
        $html = $response->getContent();

        $response->assertSee('method="GET" action="'.route('doctors.index').'" role="search"', false)
            ->assertSee('name="q"', false)
            ->assertSee('name="spesialisasi"', false)
            ->assertSee('name="poli"', false)
            ->assertSee('name="tanggal"', false)
            ->assertSee('min="2026-10-05"', false)
            ->assertSee('max="2026-10-19"', false)
            ->assertSee('<option value="'.$anak->slug.'"', false)
            ->assertSee('Poliklinik Anak Uji')
            ->assertDontSee('Spesialis Nonaktif Uji');
        $this->assertStringContainsString('data-bs-target="#cari-cepat" aria-expanded="false"', $html);

        // Layanan penunjang tetap tampil di blok layanan unggulan (F4A-R16), tetapi bukan pilihan poliklinik.
        $this->assertSame(1, preg_match('#<select[^>]*name="poli".*?</select>#s', $html, $poli));
        $this->assertStringContainsString('Poliklinik Anak Uji', $poli[0]);
        $this->assertStringNotContainsString('Laboratorium Penunjang Uji', $poli[0]);
    }

    // --- pembangkitan sesi harian (D-66, F3-R8/R9) -------------------------------------------------

    public function test_visiting_the_home_page_triggers_the_daily_generation_once(): void
    {
        $doctor = $this->createDoctor(['name' => 'Rani Oktaviani', 'title_prefix' => 'dr.', 'title_suffix' => null]);
        $this->createSchedule(['doctor_id' => $doctor->id, 'clinic_id' => $doctor->clinic_id, 'day_of_week' => 3, 'valid_from' => '2026-10-05']);
        Cache::forget(PracticeSessionGenerator::markerKey(CarbonImmutable::today()));

        $this->assertSame(0, PracticeSession::query()->count());

        $this->get('/')->assertOk();

        $this->assertSame(5, PracticeSession::query()->count()); // Rabu 7, 14, 21, 28 Okt dan 4 Nov
        $this->assertTrue(Cache::has(PracticeSessionGenerator::markerKey(CarbonImmutable::today())));

        PracticeSession::query()->delete();
        $this->get('/')->assertOk();

        $this->assertSame(0, PracticeSession::query()->count(), 'Penanda harian mencegah pembangkitan kedua.');
    }

    public function test_a_lock_held_by_another_process_does_not_block_the_home_page(): void
    {
        $doctor = $this->createDoctor(['name' => 'Rani Oktaviani', 'title_prefix' => 'dr.', 'title_suffix' => null]);
        $this->createSchedule(['doctor_id' => $doctor->id, 'clinic_id' => $doctor->clinic_id, 'day_of_week' => 3, 'valid_from' => '2026-10-05']);
        Cache::forget(PracticeSessionGenerator::markerKey(CarbonImmutable::today()));

        $lock = Cache::lock(PracticeSessionGenerator::LOCK_KEY, 30);
        $this->assertTrue($lock->get());

        try {
            $this->get('/')->assertOk()->assertSee('dr. Rani Oktaviani');
        } finally {
            $lock->release();
        }

        $this->assertSame(0, PracticeSession::query()->count());
    }

    // --- mode ketat dan sumber data ---------------------------------------------------------------

    public function test_a_page_full_of_rows_renders_under_strict_mode_without_lazy_loading(): void
    {
        $category = $this->createArticleCategory(['name' => 'Kategori Banyak']);

        foreach (range(1, 4) as $number) {
            $banner = $this->createBanner(['title' => "Banner Banyak {$number}", 'sort_order' => $number, 'button_label' => 'Lihat Dokter', 'button_url' => '/dokter']);
            $this->createMediaRow(['model_type' => $banner->getMorphClass(), 'model_id' => $banner->id, 'collection' => 'image', 'directory' => 'image/2026/10', 'alt_text' => "Alt banner {$number}"]);
        }

        foreach (range(1, 8) as $number) {
            $clinic = $this->createClinic(['name' => "Poli Banyak {$number}", 'sort_order' => $number]);
            $this->createMediaRow(['model_type' => $clinic->getMorphClass(), 'model_id' => $clinic->id, 'collection' => 'cover', 'directory' => 'cover/2026/10', 'alt_text' => "Alt poli {$number}"]);
        }

        foreach (range(1, 6) as $number) {
            $doctor = $this->createDoctor(['name' => "Dokter Banyak {$number}", 'sort_order' => $number]);
            $this->createSession(['doctor_id' => $doctor->id, 'clinic_id' => $doctor->clinic_id, 'session_date' => '2026-10-06']);
        }

        foreach (range(1, 5) as $number) {
            $article = $this->createArticle(['title' => "Artikel Banyak {$number}", 'published_at' => now()->subDays($number)], $category);
            $this->createMediaRow(['model_type' => $article->getMorphClass(), 'model_id' => $article->id, 'collection' => 'cover', 'directory' => 'cover/2026/10', 'alt_text' => "Alt artikel {$number}"]);
        }

        $this->get('/')->assertOk()->assertSee('Banner Banyak 4')->assertSee('Artikel Banyak 3')->assertDontSee('Artikel Banyak 4');
    }

    public function test_the_controller_reads_the_schedule_only_through_the_shared_query(): void
    {
        $source = (string) file_get_contents((new \ReflectionClass(HomeController::class))->getFileName());

        $this->assertStringContainsString('DoctorAvailabilityQuery', $source);
        $this->assertStringContainsString('ensureDailyHorizon()', $source);
        $this->assertStringContainsString("Page::forKey('home')", $source);
        $this->assertStringNotContainsString('PracticeSession::', $source);
        $this->assertStringNotContainsString('DB::', $source);
    }

    public function test_the_home_views_use_escaped_output_and_no_inline_event_handlers(): void
    {
        foreach (['home', 'partials/_banner-carousel', 'partials/_quick-search', 'partials/_featured-clinics', 'partials/_featured-doctors', 'partials/_room-summary', 'partials/_latest-articles', 'partials/_help-cta'] as $view) {
            $source = (string) file_get_contents(resource_path("views/public/{$view}.blade.php"));

            $this->assertNotSame('', $source, $view);
            $this->assertStringNotContainsString('{!!', $source, $view);
            $this->assertSame(0, preg_match('/\son[a-z]{3,}\s*=\s*["\']/i', $source), $view);
        }
    }
}
