<?php

namespace Tests\Feature\Public;

use App\Models\InsurancePartner;
use App\Models\Page;
use App\Models\User;
use App\Services\Media\MediaService;
use App\Services\Settings\SettingsService;
use Database\Seeders\SettingsSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ImageFixtures;
use Tests\Support\InteractsWithContent;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

/**
 * Halaman berbasis `pages` (F4A-R4/R5/R18): #2 Profil, #3 Visi dan Misi, #16 BPJS dan Asuransi, #17 Panduan
 * Pendaftaran, dan Kebijakan Privasi. DB kosong tetap 200 dengan teks bawaan (EMP-01); isi kosong tampil empty-state
 * (EMP-03); isi rich text tersanitasi (Review Focus 2).
 */
class PublicContentPagesTest extends TestCase
{
    use InteractsWithContent;
    use InteractsWithStaff;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    /** @return array<string, array{string, string, string, string, string, string}> */
    public static function pageCases(): array
    {
        return [
            'about' => ['about', 'about', '/profil', 'Profil Rumah Sakit', 'Mengenal UNPAM HOSPITAL', 'Profil UNPAM HOSPITAL, rumah sakit prototipe di Universitas Pamulang Kampus 2 Viktor.'],
            'vision-mission' => ['vision-mission', 'vision-mission', '/visi-misi', 'Visi dan Misi', 'Arah dan komitmen pelayanan', 'Visi dan misi contoh UNPAM HOSPITAL.'],
            'insurance-info' => ['insurance-info', 'insurance', '/bpjs-asuransi', 'Informasi BPJS dan Asuransi', 'Persyaratan dan pembayaran', 'Informasi contoh tentang BPJS Kesehatan dan asuransi di UNPAM HOSPITAL.'],
            'registration-guide' => ['registration-guide', 'registration-guide', '/panduan-pendaftaran', 'Panduan Pendaftaran Pasien', 'Langkah mendaftar kunjungan rawat jalan', 'Panduan mendaftar kunjungan rawat jalan online maupun di loket.'],
            'privacy-policy' => ['privacy-policy', 'privacy', '/kebijakan-privasi', 'Kebijakan Privasi', 'Cara kami memperlakukan data Anda', 'Kebijakan privasi dan persetujuan penggunaan data pasien UNPAM HOSPITAL.'],
        ];
    }

    private function fakeRoute(string $name, string $uri): void
    {
        if (! Route::has($name)) {
            Route::middleware('web')->get($uri, fn () => 'x')->name($name);
            Route::getRoutes()->refreshNameLookups();
        }
    }

    /** Blok tautan terkait di akhir halaman (bukan menu atau footer). */
    private function relatedLinks(string $html): string
    {
        $this->assertSame(1, preg_match('/<nav[^>]*aria-label="Tautan terkait".*?<\/nav>/s', $html, $match), 'Blok tautan terkait tidak ditemukan.');

        return $match[0];
    }

    /** Label breadcrumb statis pendek (F4A-R31); judul <h1> tetap `pages.title`. */
    private function crumb(string $key): string
    {
        return [
            'about' => 'Profil',
            'vision-mission' => 'Visi dan Misi',
            'insurance-info' => 'BPJS dan Asuransi',
            'registration-guide' => 'Panduan Pendaftaran',
            'privacy-policy' => 'Kebijakan Privasi',
        ][$key];
    }

    private function defaultMetaDescription(): string
    {
        return 'Informasi layanan, dokter, jadwal, dan pendaftaran kunjungan rawat jalan di '.config('app.name').'.';
    }

    // --- DB kosong: teks bawaan dan empty-state (F4A-R4, EMP-01) --------------------------------

    #[DataProvider('pageCases')]
    public function test_it_renders_the_default_texts_and_an_empty_state_on_an_empty_database(string $key, string $route, string $uri, string $title, string $subtitle, string $meta): void
    {
        $this->assertSame($uri, route($route, [], false));
        $this->assertSame(0, Page::query()->count());

        $response = $this->get(route($route))->assertOk();

        $response->assertSee('<title>'.$title.' — '.config('app.name').'</title>', false)
            ->assertSee('<meta name="description" content="'.$meta.'">', false)
            ->assertSee($subtitle)
            ->assertSee('Konten sedang disiapkan')
            ->assertSee('Isi halaman ini sedang disiapkan oleh admin.')
            ->assertSee('<meta property="og:title" content="'.$title.'">', false)
            ->assertSee('<meta property="og:description" content="'.$meta.'">', false)
            ->assertSee('<link rel="canonical" href="'.route($route).'">', false)
            ->assertSee('aria-label="Breadcrumb"', false)
            ->assertSee('<li class="breadcrumb-item active" aria-current="page">'.$this->crumb($key).'</li>', false)
            ->assertDontSee('page-body')
            ->assertDontSee('<meta name="robots"', false);
        $this->assertSame(1, substr_count($response->getContent(), '<h1'));
        $this->assertSame(0, Page::query()->count(), 'Halaman publik tidak boleh membuat baris pages.');
    }

    // --- baris pages: judul, subjudul, SEO, isi (F4A-R5) -----------------------------------------

    #[DataProvider('pageCases')]
    public function test_it_renders_the_page_row_with_its_own_seo_fields_and_body(string $key, string $route): void
    {
        $this->createPage($key, [
            'title' => 'Judul Kustom',
            'subtitle' => 'Subjudul kustom',
            'body' => '<h2>Bagian Satu</h2><p>Isi kustom halaman.</p>',
            'meta_title' => 'Meta Judul Kustom',
            'meta_description' => 'Meta deskripsi kustom.',
        ]);

        $response = $this->get(route($route))->assertOk();

        $response->assertSee('<title>Meta Judul Kustom — '.config('app.name').'</title>', false)
            ->assertSee('<meta name="description" content="Meta deskripsi kustom.">', false)
            ->assertSee('<h1 class="mb-1">Judul Kustom</h1>', false)
            ->assertSee('Subjudul kustom')
            ->assertSee('<div class="rich-text page-body">', false)
            ->assertSee('<h2>Bagian Satu</h2>', false)
            ->assertSee('Isi kustom halaman.')
            ->assertSee('<meta property="og:title" content="Meta Judul Kustom">', false)
            ->assertSee('<meta property="og:description" content="Meta deskripsi kustom.">', false)
            ->assertSee('<li class="breadcrumb-item active" aria-current="page">'.$this->crumb($key).'</li>', false)
            ->assertDontSee('Konten sedang disiapkan');
        $this->assertSame(1, substr_count($response->getContent(), '<h1'));
    }

    #[DataProvider('pageCases')]
    public function test_title_falls_back_to_the_page_title_and_description_to_the_subtitle(string $key, string $route): void
    {
        $this->createPage($key, [
            'title' => 'Judul Kustom',
            'subtitle' => 'Subjudul sebagai deskripsi',
            'body' => '<p>Isi.</p>',
            'meta_title' => null,
            'meta_description' => null,
        ]);

        $this->get(route($route))
            ->assertOk()
            ->assertSee('<title>Judul Kustom — '.config('app.name').'</title>', false)
            ->assertSee('<meta name="description" content="Subjudul sebagai deskripsi">', false)
            ->assertSee('<meta property="og:title" content="Judul Kustom">', false);
    }

    #[DataProvider('pageCases')]
    public function test_description_falls_back_to_the_site_default_and_og_description_is_omitted(string $key, string $route): void
    {
        $this->createPage($key, [
            'subtitle' => null,
            'body' => '<p>Isi.</p>',
            'meta_title' => null,
            'meta_description' => null,
        ]);

        $this->get(route($route))
            ->assertOk()
            ->assertSee('<meta name="description" content="'.$this->defaultMetaDescription().'">', false)
            ->assertDontSee('og:description', false);
    }

    // --- rich text berbahaya atau kosong (Review Focus 2, F4A-R18) -------------------------------

    #[DataProvider('pageCases')]
    public function test_a_dangerous_body_is_stored_clean_and_rendered_clean(string $key, string $route): void
    {
        $this->createPage($key, [
            'body' => '<p>Teks aman</p><script>alert(1)</script><p onclick="x()" style="color:red">Gaya</p>'
                .'<img src="x" onerror="alert(2)"><a href="javascript:alert(3)">klik</a>',
        ]);

        $stored = (string) Page::query()->where('key', $key)->value('body');

        $this->assertStringContainsString('Teks aman', $stored);
        $this->assertStringNotContainsString('<script', $stored);
        $this->assertStringNotContainsString('onclick', $stored);
        $this->assertStringNotContainsString('onerror', $stored);
        $this->assertStringNotContainsString('javascript:', $stored);
        $this->assertStringNotContainsString('color:red', $stored);

        $this->get(route($route))
            ->assertOk()
            ->assertSee('Teks aman')
            ->assertDontSee('<script>alert', false)
            ->assertDontSee('onclick', false)
            ->assertDontSee('onerror', false)
            ->assertDontSee('javascript:', false)
            ->assertDontSee('color:red', false);
    }

    #[DataProvider('pageCases')]
    public function test_a_visually_blank_body_is_stored_as_null_and_shows_the_empty_state(string $key, string $route): void
    {
        foreach (['<p><br></p>', '<p>&nbsp;</p>', "  \n  "] as $blank) {
            $this->createPage($key, ['body' => $blank]);

            $this->assertNull(Page::query()->where('key', $key)->value('body'), $blank);

            $this->get(route($route))
                ->assertOk()
                ->assertSee('Konten sedang disiapkan')
                ->assertDontSee('page-body');

            Page::query()->where('key', $key)->delete();
        }
    }

    public function test_the_pages_are_identical_for_guests_patients_and_staff(): void
    {
        $this->createPage('about', ['body' => '<p>Profil terbuka untuk semua.</p>']);

        $this->get(route('about'))->assertOk()->assertSee('Profil terbuka untuk semua.');
        $this->actingAs(User::factory()->patient()->create())->get(route('about'))->assertOk()->assertSee('Profil terbuka untuk semua.');

        foreach (['content-admin', 'registration-officer', 'super-admin'] as $role) {
            $this->actingAs($this->createStaff($role))->get(route('about'))->assertOk()->assertSee('Profil terbuka untuk semua.');
        }
    }

    public function test_the_body_is_rendered_raw_only_inside_the_shared_partial(): void
    {
        $this->createPage('vision-mission', ['body' => '<p>Visi uji.</p>']);

        $html = (string) $this->get(route('vision-mission'))->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'class="rich-text page-body"'));
        $this->assertSame(0, preg_match('/\son[a-z]+\s*=/i', $html));
    }

    // --- tautan terkait per halaman (susunan E) --------------------------------------------------

    public function test_about_links_to_vision_and_mission_and_to_contact_only_when_the_route_exists(): void
    {
        $block = $this->relatedLinks((string) $this->get(route('about'))->assertOk()->getContent());

        $this->assertStringContainsString('href="'.route('vision-mission').'"', $block);
        $this->assertStringContainsString('>Visi dan Misi</a>', $block);

        if (! Route::has('contact')) {
            $this->assertStringNotContainsString('>Kontak</a>', $block);

            $this->fakeRoute('contact', '/_uji/kontak');
        }

        $block = $this->relatedLinks((string) $this->get(route('about'))->getContent());

        $this->assertStringContainsString('href="'.route('contact').'"', $block);
        $this->assertStringContainsString('>Kontak</a>', $block);
    }

    public function test_vision_and_mission_links_back_to_the_profile(): void
    {
        $block = $this->relatedLinks((string) $this->get(route('vision-mission'))->assertOk()->getContent());

        $this->assertStringContainsString('href="'.route('about').'"', $block);
        $this->assertStringContainsString('>Profil Rumah Sakit</a>', $block);
    }

    public function test_registration_guide_shows_buttons_only_for_routes_that_exist(): void
    {
        $labels = ['patient.appointments.create' => 'Daftar Kunjungan', 'register' => 'Daftar Akun', 'faq' => 'FAQ'];
        $block = $this->relatedLinks((string) $this->get(route('registration-guide'))->assertOk()->getContent());

        foreach ($labels as $name => $label) {
            Route::has($name)
                ? $this->assertStringContainsString('>'.$label.'</a>', $block, $name)
                : $this->assertStringNotContainsString('>'.$label.'</a>', $block, $name);
        }
    }

    public function test_registration_guide_shows_all_three_buttons_once_their_routes_exist(): void
    {
        $this->fakeRoute('patient.appointments.create', '/_uji/pasien/pendaftaran/baru');
        $this->fakeRoute('register', '/_uji/daftar');
        $this->fakeRoute('faq', '/_uji/faq');

        $block = $this->relatedLinks((string) $this->get(route('registration-guide'))->assertOk()->getContent());

        $this->assertStringContainsString('href="'.route('patient.appointments.create').'"', $block);
        $this->assertStringContainsString('>Daftar Kunjungan</a>', $block);
        $this->assertStringContainsString('href="'.route('register').'"', $block);
        $this->assertStringContainsString('>Daftar Akun</a>', $block);
        $this->assertStringContainsString('href="'.route('faq').'"', $block);
        $this->assertStringContainsString('>FAQ</a>', $block);
    }

    // --- Kebijakan Privasi ------------------------------------------------------------------------

    public function test_privacy_shows_the_default_policy_version_without_settings(): void
    {
        $this->get(route('privacy'))->assertOk()->assertSee('Versi kebijakan: 1.0');
    }

    public function test_privacy_shows_the_policy_version_from_settings(): void
    {
        $this->seed(SettingsSeeder::class);
        $settings = app(SettingsService::class);
        $settings->forget();
        $settings->set('privacy.consent_version', '2.3');

        $this->get(route('privacy'))->assertOk()->assertSee('Versi kebijakan: 2.3')->assertDontSee('Versi kebijakan: 1.0');
    }

    // --- #16 BPJS dan Asuransi ---------------------------------------------------------------------

    public function test_insurance_always_shows_the_example_warning_box(): void
    {
        $this->get(route('insurance'))
            ->assertOk()
            ->assertSee('alert alert-warning', false)
            ->assertSee('Informasi ini contoh dan harus diverifikasi serta disesuaikan oleh admin dengan ketentuan resmi sebelum digunakan. Rumah sakit ini tidak menyatakan kerja sama resmi dengan pihak mana pun.');
    }

    public function test_insurance_hides_the_partner_section_when_there_are_no_active_partners(): void
    {
        $this->createInsurancePartner(['name' => 'Mitra Nonaktif', 'is_active' => false]);

        $this->get(route('insurance'))
            ->assertOk()
            ->assertDontSee('Mitra Asuransi dan Perusahaan (Contoh)')
            ->assertDontSee('Mitra Nonaktif')
            ->assertDontSee('bukan klaim kerja sama');
    }

    public function test_insurance_lists_active_partners_in_order_with_the_example_badge(): void
    {
        $this->createInsurancePartner(['name' => 'Beta Contoh', 'sort_order' => 2]);
        $this->createInsurancePartner(['name' => 'Zeta Contoh', 'sort_order' => 1]);
        $this->createInsurancePartner(['name' => 'Alfa Contoh', 'sort_order' => 1, 'type' => 'perusahaan', 'description' => 'Mitra perusahaan contoh.']);
        $this->createInsurancePartner(['name' => 'Tersembunyi Contoh', 'sort_order' => 0, 'is_active' => false]);

        $response = $this->get(route('insurance'))->assertOk();

        $response->assertSeeInOrder([
            '<h2 class="h3" id="mitra-judul">Mitra Asuransi dan Perusahaan (Contoh)</h2>',
            'Data mitra di bawah ini adalah contoh dan bukan klaim kerja sama.',
            'Alfa Contoh',
            'Zeta Contoh',
            'Beta Contoh',
        ], false)
            ->assertSee('Mitra perusahaan contoh.')
            ->assertSee('Perusahaan')
            ->assertSee('Asuransi')
            ->assertDontSee('Tersembunyi Contoh');
        $this->assertSame(3, substr_count($response->getContent(), '<span class="badge text-bg-warning">Contoh</span>'));
    }

    public function test_insurance_uses_the_local_logo_placeholder_when_a_partner_has_no_logo(): void
    {
        $this->createInsurancePartner(['name' => 'Tanpa Logo Contoh']);

        $this->get(route('insurance'))
            ->assertOk()
            ->assertSee('images/placeholders/logo.svg', false)
            ->assertSee('alt="Logo Tanpa Logo Contoh"', false)
            ->assertDontSee('onerror', false);
    }

    /**
     * `x-image` selalu menulis `data-fallback`; berkas yang hilang di disk ditangani JS (`recheckBrokenImages` dan
     * listener `error`) dan diuji manual, bukan di tes Feature ini.
     */
    public function test_insurance_shows_the_uploaded_logo_and_keeps_the_alt_and_exposes_the_placeholder_fallback_attribute(): void
    {
        $partner = $this->createInsurancePartner(['name' => 'Berlogo Contoh']);
        $this->app->make(MediaService::class)->store(ImageFixtures::jpeg(400, 200, 'logo.jpg'), 'logo', $partner, ['alt_text' => 'Logo resmi contoh']);

        $this->get(route('insurance'))
            ->assertOk()
            ->assertSee('alt="Logo resmi contoh"', false)
            ->assertSee('/thumbs/', false)
            ->assertSee('data-fallback="'.asset('images/placeholders/logo.svg').'"', false);
    }

    public function test_insurance_website_link_opens_safely_and_only_for_https(): void
    {
        $this->createInsurancePartner(['name' => 'Bertaut Contoh', 'website_url' => 'https://contoh.test/asuransi']);
        $this->createInsurancePartner(['name' => 'Berbahaya Contoh', 'website_url' => 'javascript:alert(1)']);
        $this->createInsurancePartner(['name' => 'Tanpa Situs Contoh', 'website_url' => null]);

        $response = $this->get(route('insurance'))->assertOk();

        $response->assertSee('href="https://contoh.test/asuransi" target="_blank" rel="noopener noreferrer"', false)
            ->assertDontSee('javascript:', false);
        $this->assertSame(1, substr_count($response->getContent(), 'target="_blank"'), 'Hanya satu tautan situs mitra (pengaturan kosong, jadi tanpa tautan WhatsApp di footer).');
    }

    public function test_insurance_links_to_the_registration_guide_and_to_contact_only_when_it_exists(): void
    {
        $block = $this->relatedLinks((string) $this->get(route('insurance'))->assertOk()->getContent());

        $this->assertStringContainsString('href="'.route('registration-guide').'"', $block);
        $this->assertStringContainsString('>Panduan Pendaftaran</a>', $block);

        if (! Route::has('contact')) {
            $this->assertStringNotContainsString('>Kontak</a>', $block);
        }
    }

    public function test_insurance_renders_many_partners_with_logos_in_strict_mode(): void
    {
        $this->assertTrue(Model::preventsLazyLoading());

        $media = $this->app->make(MediaService::class);

        foreach (range(1, 6) as $number) {
            $partner = $this->createInsurancePartner(['name' => "Mitra Uji {$number}", 'sort_order' => $number]);
            $media->store(ImageFixtures::jpeg(400, 200, 'logo.jpg'), 'logo', $partner, ['alt_text' => "Logo mitra uji {$number}"]);
        }

        $this->createPage('insurance-info', ['body' => '<p>Isi asuransi.</p>']);

        $response = $this->get(route('insurance'))->assertOk();

        $response->assertSee('alt="Logo mitra uji 6"', false)->assertSee('Isi asuransi.');
        $this->assertSame(6, substr_count($response->getContent(), '<span class="badge text-bg-warning">Contoh</span>'));
        $this->assertSame(6, InsurancePartner::query()->count());
    }

    public function test_insurance_shows_the_partner_section_below_the_body_and_the_warning(): void
    {
        $this->createPage('insurance-info', ['body' => '<h2>BPJS Kesehatan</h2><p>Bawa kartu peserta.</p>']);
        $this->createInsurancePartner(['name' => 'Urutan Contoh']);

        $this->get(route('insurance'))
            ->assertOk()
            ->assertSeeInOrder([
                'BPJS Kesehatan',
                'Informasi ini contoh dan harus diverifikasi',
                'Mitra Asuransi dan Perusahaan (Contoh)',
                'Urutan Contoh',
            ]);
    }

    // --- semua halaman: keamanan markup -------------------------------------------------------------

    #[DataProvider('pageCases')]
    public function test_pages_have_no_inline_handlers_and_no_javascript_urls(string $key, string $route): void
    {
        $this->createPage($key, ['body' => '<p>Isi.</p>']);

        $html = (string) $this->get(route($route))->assertOk()->getContent();

        $this->assertSame(0, preg_match('/\son[a-z]+\s*=/i', $html));
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringNotContainsString('onerror', $html);
    }
}
