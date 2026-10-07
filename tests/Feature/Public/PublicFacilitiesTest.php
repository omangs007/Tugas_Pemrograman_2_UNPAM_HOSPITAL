<?php

namespace Tests\Feature\Public;

use App\Enums\FacilityCategory;
use App\Models\Facility;
use App\Models\Media;
use App\Models\User;
use App\Services\Media\MediaService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\ImageFixtures;
use Tests\Support\InteractsWithContent;
use Tests\Support\InteractsWithFacilities;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

/**
 * Halaman #9 `facilities.index` dan #10 `facilities.show` (05 hal. 9-10, F4B-R14, F4B-R29): hanya fasilitas aktif,
 * dikelompokkan per kategori dalam urutan enum; kategori tanpa isi disembunyikan; 404 untuk fasilitas nonaktif
 * dan slug tidak dikenal bagi semua aktor termasuk staf; detail memakai ulang pola modal dan carousel galeri
 * poliklinik. Mencakup EMP-01, Review Focus 3, dan mode ketat.
 */
class PublicFacilitiesTest extends TestCase
{
    use InteractsWithContent;
    use InteractsWithFacilities;
    use InteractsWithStaff;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    /** Foto sungguhan lewat MediaService (berkas dan thumbnail ada di disk palsu). */
    private function attachImage(Model $owner, string $collection, string $alt, string $name = 'foto.jpg'): Media
    {
        return $this->app->make(MediaService::class)->store(
            ImageFixtures::jpeg(1200, 800, $name),
            $collection,
            $owner,
            ['alt_text' => $alt],
        );
    }

    private function fakeRoute(string $name, string $uri): void
    {
        if (! Route::has($name)) {
            Route::middleware('web')->get($uri, fn () => 'x')->name($name);
            Route::getRoutes()->refreshNameLookups();
        }
    }

    private function noindexTag(): string
    {
        return '<meta name="robots" content="noindex,nofollow">';
    }

    private function showUrl(Facility $facility): string
    {
        return route('facilities.show', $facility->slug);
    }

    // --- #9: header, SEO, breadcrumb --------------------------------------------------------------------

    public function test_index_renders_the_default_header_seo_and_breadcrumb(): void
    {
        $this->createFacility(['name' => 'Ruang Operasi', 'category' => FacilityCategory::Medis]);

        $response = $this->get(route('facilities.index'))->assertOk();

        $response->assertSee('<title>Fasilitas — '.config('app.name').'</title>', false)
            ->assertSee('<h1 class="mb-1">Fasilitas</h1>', false)
            ->assertSee('Fasilitas medis, penunjang, dan umum')
            ->assertSee('<meta name="description" content="Daftar fasilitas UNPAM HOSPITAL.">', false)
            ->assertSee('<meta property="og:title" content="Fasilitas">', false)
            ->assertSee('<link rel="canonical" href="'.route('facilities.index').'">', false)
            ->assertSeeInOrder(['aria-label="Breadcrumb"', 'Beranda', 'Fasilitas'], false)
            ->assertSee('<li class="breadcrumb-item active" aria-current="page">Fasilitas</li>', false)
            ->assertDontSee($this->noindexTag(), false);
        $this->assertSame(1, substr_count($response->getContent(), '<h1'));
    }

    public function test_index_header_and_seo_follow_the_facilities_index_page_row(): void
    {
        $this->createPage('facilities-index', [
            'title' => 'Sarana Kami',
            'subtitle' => 'Sarana untuk pasien',
            'meta_title' => 'Sarana Kami Meta',
            'meta_description' => 'Deskripsi sarana kami.',
        ]);
        $this->createFacility(['category' => FacilityCategory::Umum]);

        $this->get(route('facilities.index'))
            ->assertOk()
            ->assertSee('<title>Sarana Kami Meta — '.config('app.name').'</title>', false)
            ->assertSee('<h1 class="mb-1">Sarana Kami</h1>', false)
            ->assertSee('Sarana untuk pasien')
            ->assertSee('<meta name="description" content="Deskripsi sarana kami.">', false)
            ->assertSee('<meta property="og:title" content="Sarana Kami Meta">', false);
    }

    // --- #9: pengelompokan (F4B-R29) ----------------------------------------------------------------------

    public function test_facilities_are_grouped_by_category_in_the_enum_order_with_icons_and_labels(): void
    {
        $this->createFacility(['name' => 'Kursi Roda Umum', 'category' => FacilityCategory::Umum, 'sort_order' => 1]);
        $this->createFacility(['name' => 'Laboratorium Klinik', 'category' => FacilityCategory::Penunjang, 'sort_order' => 1]);
        $this->createFacility(['name' => 'IGD Dua Puluh Empat Jam', 'category' => FacilityCategory::Medis, 'sort_order' => 2]);
        $this->createFacility(['name' => 'Ruang Operasi', 'category' => FacilityCategory::Medis, 'sort_order' => 1]);

        $response = $this->get(route('facilities.index'))->assertOk();

        $response->assertSeeInOrder([
            'id="fasilitas-medis"',
            'Ruang Operasi',
            'IGD Dua Puluh Empat Jam',
            'id="fasilitas-penunjang"',
            'Laboratorium Klinik',
            'id="fasilitas-umum"',
            'Kursi Roda Umum',
        ], false)
            ->assertSee('<i class="bi bi-heart-pulse" aria-hidden="true"></i> Fasilitas medis</h2>', false)
            ->assertSee('<i class="bi bi-tools" aria-hidden="true"></i> Penunjang</h2>', false)
            ->assertSee('<i class="bi bi-building" aria-hidden="true"></i> Fasilitas umum</h2>', false);
        $this->assertSame(3, substr_count($response->getContent(), '<h2 class="h4 mb-3" id="fasilitas-'));
    }

    public function test_inside_a_category_facilities_follow_sort_order_then_name(): void
    {
        $this->createFacility(['name' => 'Beta Ruang', 'category' => FacilityCategory::Medis, 'sort_order' => 2]);
        $this->createFacility(['name' => 'Alfa Ruang', 'category' => FacilityCategory::Medis, 'sort_order' => 2]);
        $this->createFacility(['name' => 'Zeta Ruang', 'category' => FacilityCategory::Medis, 'sort_order' => 1]);

        $this->get(route('facilities.index'))
            ->assertOk()
            ->assertSeeInOrder(['Zeta Ruang', 'Alfa Ruang', 'Beta Ruang']);
    }

    public function test_a_category_without_active_facilities_is_skipped(): void
    {
        $this->createFacility(['name' => 'Ruang Tindakan', 'category' => FacilityCategory::Medis]);
        $this->createFacility(['name' => 'Lab Nonaktif', 'category' => FacilityCategory::Penunjang, 'is_active' => false]);

        $this->get(route('facilities.index'))
            ->assertOk()
            ->assertSee('id="fasilitas-medis"', false)
            ->assertDontSee('id="fasilitas-penunjang"', false)
            ->assertDontSee('id="fasilitas-umum"', false)
            ->assertDontSee('Lab Nonaktif');
    }

    public function test_inactive_facilities_are_not_listed(): void
    {
        $this->createFacility(['name' => 'Fasilitas Aktif Uji', 'category' => FacilityCategory::Umum]);
        $this->createFacility(['name' => 'Fasilitas Mati Uji', 'category' => FacilityCategory::Umum, 'is_active' => false]);

        $this->get(route('facilities.index'))
            ->assertOk()
            ->assertSee('Fasilitas Aktif Uji')
            ->assertDontSee('Fasilitas Mati Uji');
    }

    // --- #9: kosong (EMP-01) ---------------------------------------------------------------------------------

    public function test_an_empty_database_shows_the_empty_state(): void
    {
        $response = $this->get(route('facilities.index'))->assertOk();

        $response->assertSee('Belum ada fasilitas')
            ->assertSee('Informasi fasilitas akan tampil di sini setelah ditambahkan.')
            ->assertSee('bi-building', false)
            ->assertSee('role="status"', false)
            ->assertDontSee('id="fasilitas-', false)
            ->assertDontSee($this->noindexTag(), false);
        $this->assertSame(1, substr_count($response->getContent(), '<h1'));
    }

    public function test_only_inactive_facilities_also_show_the_empty_state(): void
    {
        $this->createFacility(['name' => 'Hanya Nonaktif', 'is_active' => false]);

        $this->get(route('facilities.index'))
            ->assertOk()
            ->assertSee('Belum ada fasilitas')
            ->assertDontSee('Hanya Nonaktif');
    }

    // --- #9: kartu ------------------------------------------------------------------------------------------------

    public function test_a_card_links_to_the_detail_by_slug_with_one_stretched_link_and_the_placeholder_image(): void
    {
        $facility = $this->createFacility([
            'name' => 'Ruang Hemodialisis',
            'short_description' => 'Layanan cuci darah contoh.',
            'category' => FacilityCategory::Medis,
        ]);

        $response = $this->get(route('facilities.index'))->assertOk();
        $html = $response->getContent();

        $response->assertSee('href="'.$this->showUrl($facility).'"', false)
            ->assertSee('<h3 class="card-title h5 mb-1">', false)
            ->assertSee('Layanan cuci darah contoh.')
            ->assertSee('images/placeholders/facility.svg', false)
            ->assertSee('alt="Ruang Hemodialisis"', false)
            ->assertDontSee('onerror', false);
        $this->assertSame(1, substr_count($html, 'stretched-link'));
        $this->assertSame(0, preg_match('/<a\b[^>]*>(?:(?!<\/a>).)*<a\b/s', $html));
    }

    public function test_a_card_uses_the_cover_thumbnail_and_its_alt_text(): void
    {
        $facility = $this->createFacility(['name' => 'Ruang Radiologi', 'category' => FacilityCategory::Penunjang]);
        $this->attachImage($facility, 'cover', 'Foto contoh ruang radiologi');

        $this->get(route('facilities.index'))
            ->assertOk()
            ->assertSee('alt="Foto contoh ruang radiologi"', false)
            ->assertSee('/thumbs/', false)
            ->assertDontSee('images/placeholders/facility.svg" alt', false);
    }

    public function test_many_facilities_with_covers_render_in_strict_mode(): void
    {
        $this->assertTrue(Model::preventsLazyLoading());

        foreach ([FacilityCategory::Medis, FacilityCategory::Penunjang, FacilityCategory::Umum] as $index => $category) {
            foreach ([1, 2] as $number) {
                $facility = $this->createFacility([
                    'name' => "Fasilitas {$category->value} {$number}",
                    'category' => $category,
                    'sort_order' => $number,
                ]);
                $this->attachImage($facility, 'cover', "Alt {$category->value} {$number}", "c{$index}{$number}.jpg");
            }
        }

        $html = (string) $this->get(route('facilities.index'))->assertOk()->getContent();

        $this->assertSame(6, substr_count($html, 'class="card h-100 facility-card"'));
        $this->assertSame(3, substr_count($html, '<h2 class="h4 mb-3" id="fasilitas-'));
        $this->assertStringContainsString('alt="Alt umum 2"', $html);
        $this->assertSame(0, preg_match('/\son[a-z]+\s*=/i', $html));
    }

    // --- #9: tautan bawah (bersyarat) ------------------------------------------------------------------------------

    public function test_the_bottom_links_appear_only_for_registered_routes(): void
    {
        $this->createFacility();

        if (! Route::has('rooms.index')) {
            $this->get(route('facilities.index'))->assertOk()->assertDontSee('>Lihat Kamar Rawat Inap</a>', false);
        }

        $this->fakeRoute('rooms.index', '/_uji/kamar');
        $this->fakeRoute('contact', '/_uji/kontak');

        $this->get(route('facilities.index'))
            ->assertOk()
            ->assertSee('<a class="btn btn-outline-primary" href="'.route('rooms.index').'">Lihat Kamar Rawat Inap</a>', false)
            ->assertSee('<a class="btn btn-outline-primary" href="'.route('contact').'">Kontak</a>', false);
    }

    // --- menu publik ---------------------------------------------------------------------------------------------------

    public function test_the_facilities_menu_item_is_visible_and_marked_active_on_the_pages(): void
    {
        $facility = $this->createFacility();

        $href = preg_quote(route('facilities.index'), '#');

        // Layout menulis atribut dengan spasi di sekitar @if, sehingga jarak antaratribut dicocokkan longgar.
        $home = (string) $this->get(route('home'))->assertOk()->getContent();
        $this->assertSame(1, preg_match('#<a class="dropdown-item" href="'.$href.'"\s*>Fasilitas</a>#', $home));

        $detail = (string) $this->get($this->showUrl($facility))->assertOk()->getContent();
        $this->assertSame(1, preg_match('#<a class="dropdown-item active" href="'.$href.'"\s+aria-current="page"\s*>Fasilitas</a>#', $detail));
    }

    // --- detail: 404 (Review Focus 3) --------------------------------------------------------------------------------

    public function test_an_inactive_facility_is_404_for_everyone_including_staff(): void
    {
        $facility = $this->createFacility(['name' => 'Fasilitas Mati', 'is_active' => false]);
        $url = $this->showUrl($facility);

        $this->get($url)->assertNotFound();

        foreach (['content-admin', 'registration-officer', 'super-admin'] as $role) {
            $this->actingAs($this->createStaff($role))->get($url)->assertNotFound();
        }
    }

    public function test_an_unknown_slug_is_404(): void
    {
        $this->get(route('facilities.show', 'tidak-ada'))->assertNotFound();
        $this->get('/fasilitas/Tidak%20Ada')->assertNotFound();
        $this->get('/fasilitas/123')->assertNotFound();
    }

    public function test_activating_a_facility_makes_its_detail_reachable(): void
    {
        $facility = $this->createFacility(['name' => 'Segera Dibuka', 'is_active' => false]);

        $this->get($this->showUrl($facility))->assertNotFound();

        $facility->forceFill(['is_active' => true])->save();

        $this->get($this->showUrl($facility))->assertOk()->assertSee('Segera Dibuka');
    }

    // --- detail: isi ------------------------------------------------------------------------------------------------------

    public function test_detail_renders_the_header_description_and_back_links(): void
    {
        $facility = $this->createFacility([
            'name' => 'Ruang Bersalin',
            'category' => FacilityCategory::Medis,
            'short_description' => 'Ruang bersalin contoh.',
            'description' => '<p>Isi deskripsi ruang bersalin.</p>',
        ]);
        $this->fakeRoute('rooms.index', '/_uji/kamar');

        $response = $this->get($this->showUrl($facility))->assertOk();

        $response->assertSee('<h1 class="mb-1">Ruang Bersalin</h1>', false)
            ->assertSee('Fasilitas medis')
            ->assertSee('Ruang bersalin contoh.')
            ->assertSee('<div class="rich-text facility-body"><p>Isi deskripsi ruang bersalin.</p></div>', false)
            ->assertSee('Kembali ke daftar fasilitas')
            ->assertSee('href="'.route('facilities.index').'"', false)
            ->assertSee('>Lihat Kamar Rawat Inap</a>', false)
            ->assertSee('images/placeholders/facility.svg', false)
            ->assertSee('alt="Ruang Bersalin"', false);
        $this->assertSame(1, substr_count($response->getContent(), '<h1'));
    }

    public function test_detail_breadcrumb_links_back_to_the_index(): void
    {
        $facility = $this->createFacility(['name' => 'Jejak Fasilitas']);

        $this->get($this->showUrl($facility))
            ->assertOk()
            ->assertSeeInOrder(['aria-label="Breadcrumb"', 'Beranda', 'Fasilitas', 'Jejak Fasilitas'], false)
            ->assertSee('<li class="breadcrumb-item"><a href="'.route('facilities.index').'">Fasilitas</a></li>', false)
            ->assertSee('<li class="breadcrumb-item active" aria-current="page">Jejak Fasilitas</li>', false);
    }

    public function test_a_dangerous_description_is_sanitized_and_a_blank_one_renders_no_block(): void
    {
        $dangerous = $this->createFacility([
            'name' => 'Fasilitas Berbahaya',
            'description' => '<p>Aman</p><script>alert(1)</script><img src="x" onerror="alert(2)">',
        ]);
        $blank = $this->createFacility(['name' => 'Fasilitas Tanpa Deskripsi', 'description' => '<p><br></p>']);

        $this->get($this->showUrl($dangerous))
            ->assertOk()
            ->assertSee('<p>Aman</p>', false)
            ->assertDontSee('<script>alert(1)', false)
            ->assertDontSee('onerror', false);

        $this->get($this->showUrl($blank))
            ->assertOk()
            ->assertDontSee('facility-body', false);
    }

    public function test_detail_uses_the_cover_with_its_alt_text(): void
    {
        $facility = $this->createFacility(['name' => 'Ruang Berfoto']);
        $media = $this->attachImage($facility, 'cover', 'Foto contoh ruang berfoto');

        $this->get($this->showUrl($facility))
            ->assertOk()
            ->assertSee('alt="Foto contoh ruang berfoto"', false)
            ->assertSee('src="'.$media->url.'"', false)
            ->assertDontSee('images/placeholders/facility.svg" alt', false);
    }

    // --- detail: galeri (pola klinik) -------------------------------------------------------------------------------------

    public function test_detail_renders_thumbnails_and_one_modal_carousel(): void
    {
        $facility = $this->createFacility(['name' => 'Ruang Galeri']);

        foreach ([1, 2, 3] as $number) {
            $this->attachImage($facility, 'gallery', "Foto galeri {$number}", "g{$number}.jpg");
        }

        $response = $this->get($this->showUrl($facility))->assertOk();
        $html = $response->getContent();

        $response->assertSee('<h2 id="galeri-judul" class="h4 mb-3">Galeri</h2>', false)
            ->assertSee('id="galeri-modal"', false)
            ->assertSee('data-gallery-modal', false)
            ->assertSee('role="dialog"', false)
            ->assertSee('aria-labelledby="galeri-modal-judul"', false)
            ->assertSee('Galeri Ruang Galeri')
            ->assertSee('id="galeri-carousel"', false)
            ->assertSee('Perbesar foto 1 dari 3: Foto galeri 1')
            ->assertSee('Perbesar foto 3 dari 3: Foto galeri 3')
            ->assertSee('Foto sebelumnya')
            ->assertSee('Foto berikutnya');
        $this->assertSame(3, substr_count($html, 'data-gallery-index='));
        $this->assertSame(3, substr_count($html, 'class="carousel-item'));
        $this->assertSame(1, substr_count($html, 'class="carousel-item active"'));
        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertStringContainsString('/thumbs/', $html);
    }

    public function test_the_cover_is_not_part_of_the_gallery(): void
    {
        $facility = $this->createFacility(['name' => 'Ruang Sampul']);
        $this->attachImage($facility, 'cover', 'Sampul ruang');
        $this->attachImage($facility, 'gallery', 'Galeri satu', 'g1.jpg');

        $html = (string) $this->get($this->showUrl($facility))->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'data-gallery-index='));
        $this->assertSame(1, substr_count($html, 'class="carousel-item'));
        $this->assertStringNotContainsString('Foto sebelumnya', $html);
    }

    public function test_without_a_gallery_there_is_no_modal(): void
    {
        $facility = $this->createFacility(['name' => 'Ruang Tanpa Galeri']);
        $this->attachImage($facility, 'cover', 'Hanya sampul');

        $this->get($this->showUrl($facility))
            ->assertOk()
            ->assertDontSee('galeri-modal', false)
            ->assertDontSee('data-gallery-index', false)
            ->assertDontSee('id="galeri-judul"', false);
    }

    // --- detail: SEO (F4B-R29) ----------------------------------------------------------------------------------------------------

    public function test_detail_title_and_description_follow_meta_fields_then_name_and_short_description(): void
    {
        $plain = $this->createFacility(['name' => 'Fasilitas Polos', 'short_description' => 'Ringkasan polos.', 'meta_title' => null, 'meta_description' => null]);
        $meta = $this->createFacility(['name' => 'Fasilitas Meta', 'short_description' => 'Ringkasan meta.', 'meta_title' => 'Judul Meta Fasilitas', 'meta_description' => 'Deskripsi meta fasilitas.']);

        $this->get($this->showUrl($plain))
            ->assertOk()
            ->assertSee('<title>Fasilitas Polos — '.config('app.name').'</title>', false)
            ->assertSee('<meta name="description" content="Ringkasan polos.">', false)
            ->assertSee('<meta property="og:type" content="website">', false)
            ->assertSee('<meta property="og:title" content="Fasilitas Polos">', false)
            ->assertSee('<meta property="og:description" content="Ringkasan polos.">', false)
            ->assertSee('<link rel="canonical" href="'.$this->showUrl($plain).'">', false)
            ->assertDontSee('og:image', false)
            ->assertDontSee($this->noindexTag(), false);

        $this->get($this->showUrl($meta))
            ->assertOk()
            ->assertSee('<title>Judul Meta Fasilitas — '.config('app.name').'</title>', false)
            ->assertSee('<meta name="description" content="Deskripsi meta fasilitas.">', false)
            ->assertSee('<meta property="og:title" content="Judul Meta Fasilitas">', false);
    }

    public function test_detail_og_image_is_the_absolute_cover_url(): void
    {
        $facility = $this->createFacility(['name' => 'Fasilitas Berbingkai']);
        $media = $this->attachImage($facility, 'cover', 'Sampul fasilitas');

        $this->get($this->showUrl($facility))
            ->assertOk()
            ->assertSee('<meta property="og:image" content="'.url($media->url).'">', false);
    }

    public function test_detail_description_is_cut_at_160_characters(): void
    {
        $long = str_repeat('Deskripsi fasilitas panjang. ', 10);
        $facility = $this->createFacility(['name' => 'Fasilitas Panjang', 'short_description' => $long, 'meta_description' => null]);

        $html = (string) $this->get($this->showUrl($facility))->assertOk()->getContent();

        $this->assertSame(1, preg_match('/<meta name="description" content="([^"]*)">/', $html, $match));
        $this->assertSame(Str::limit(trim($long), 160, ''), html_entity_decode($match[1]));
        $this->assertLessThanOrEqual(160, mb_strlen(html_entity_decode($match[1])));
    }

    // --- akses dan keamanan markup -------------------------------------------------------------------------------------------------

    public function test_pages_are_identical_for_guests_patients_and_staff(): void
    {
        $facility = $this->createFacility(['name' => 'Terbuka Untuk Semua']);

        foreach ([null, 'patient', 'content-admin', 'registration-officer', 'super-admin'] as $actor) {
            if ($actor === 'patient') {
                $this->actingAs(User::factory()->patient()->create());
            } elseif ($actor !== null) {
                $this->actingAs($this->createStaff($actor));
            }

            $this->get(route('facilities.index'))->assertOk()->assertSee('Terbuka Untuk Semua');
            $this->get($this->showUrl($facility))->assertOk()->assertSee('Terbuka Untuk Semua');
        }
    }

    public function test_the_views_use_escaped_output_and_no_inline_event_handlers(): void
    {
        $facility = $this->createFacility(['name' => 'Nama <b>Tebal</b> Uji', 'short_description' => '<i>Miring</i> ringkas']);

        $index = (string) $this->get(route('facilities.index'))->assertOk()->getContent();
        $show = (string) $this->get($this->showUrl($facility))->assertOk()->getContent();

        foreach ([$index, $show] as $html) {
            $this->assertStringContainsString('Nama &lt;b&gt;Tebal&lt;/b&gt; Uji', $html);
            $this->assertStringNotContainsString('<b>Tebal</b>', $html);
            $this->assertStringNotContainsString('<i>Miring</i>', $html);
            $this->assertSame(0, preg_match('/\son[a-z]+\s*=/i', $html));
            $this->assertStringNotContainsString('javascript:', $html);
        }

        foreach (['index', '_card', 'show'] as $view) {
            $source = (string) file_get_contents(resource_path("views/public/facilities/{$view}.blade.php"));

            $this->assertNotSame('', $source, $view);
            $this->assertSame(0, preg_match('/\son[a-z]{3,}\s*=\s*["\']/i', $source), $view);
        }
    }
}
