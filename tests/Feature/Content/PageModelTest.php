<?php

namespace Tests\Feature\Content;

use App\Models\Page;
use App\Models\User;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InteractsWithContent;
use Tests\TestCase;

/**
 * Model Page (09 §6.1, F4A-R4, F4A-R5): 15 key tetap, fallback SEO, dan sanitasi `body`.
 */
class PageModelTest extends TestCase
{
    use InteractsWithContent;
    use RefreshDatabase;

    /** @return array<string, array{0: string, 1: string, 2: string, 3: string}> key, judul, subjudul, meta description (13 §8.8) */
    public static function defaultRows(): array
    {
        $rows = [
            ['home', 'UNPAM HOSPITAL', 'Layanan kesehatan yang ramah dan terpercaya (contoh)', 'Informasi layanan, dokter, jadwal praktik, dan pendaftaran kunjungan UNPAM HOSPITAL.'],
            ['about', 'Profil Rumah Sakit', 'Mengenal UNPAM HOSPITAL', 'Profil UNPAM HOSPITAL, rumah sakit prototipe di Universitas Pamulang Kampus 2 Viktor.'],
            ['vision-mission', 'Visi dan Misi', 'Arah dan komitmen pelayanan', 'Visi dan misi contoh UNPAM HOSPITAL.'],
            ['clinics-index', 'Layanan dan Poliklinik', 'Pilih layanan sesuai kebutuhan Anda', 'Daftar poliklinik dan layanan penunjang UNPAM HOSPITAL.'],
            ['doctors-index', 'Cari Dokter', 'Cari berdasarkan nama, spesialisasi, poliklinik, atau tanggal praktik', 'Cari dokter UNPAM HOSPITAL berdasarkan nama, spesialisasi, poliklinik, dan tanggal.'],
            ['schedules-index', 'Jadwal Dokter', 'Jadwal praktik per minggu', 'Jadwal praktik dokter UNPAM HOSPITAL per minggu dengan sisa kuota.'],
            ['facilities-index', 'Fasilitas', 'Fasilitas medis, penunjang, dan umum', 'Daftar fasilitas UNPAM HOSPITAL.'],
            ['rooms-index', 'Kamar Rawat Inap', 'Ringkasan kategori kamar dan ketersediaan', 'Kategori kamar rawat inap, kapasitas, dan ketersediaan yang diperbarui manual.'],
            ['insurance-info', 'Informasi BPJS dan Asuransi', 'Persyaratan dan pembayaran', 'Informasi contoh tentang BPJS Kesehatan dan asuransi di UNPAM HOSPITAL.'],
            ['registration-guide', 'Panduan Pendaftaran Pasien', 'Langkah mendaftar kunjungan rawat jalan', 'Panduan mendaftar kunjungan rawat jalan online maupun di loket.'],
            ['articles-index', 'Artikel Kesehatan', 'Informasi kesehatan umum', 'Artikel kesehatan umum dari UNPAM HOSPITAL.'],
            ['galleries-index', 'Galeri', 'Foto-foto contoh', 'Galeri foto UNPAM HOSPITAL.'],
            ['faq', 'Pertanyaan yang Sering Diajukan', 'Jawaban atas pertanyaan umum', 'Pertanyaan umum tentang pendaftaran, BPJS, layanan, dan data pribadi.'],
            ['contact', 'Kontak dan Lokasi', 'Hubungi kami atau kunjungi kami', 'Alamat, telepon, WhatsApp, e-mail, peta, dan formulir kontak UNPAM HOSPITAL.'],
            ['privacy-policy', 'Kebijakan Privasi', 'Cara kami memperlakukan data Anda', 'Kebijakan privasi dan persetujuan penggunaan data pasien UNPAM HOSPITAL.'],
        ];

        $cases = [];

        foreach ($rows as $row) {
            $cases[$row[0]] = $row;
        }

        return $cases;
    }

    /** @return array<string, array{0: string}> */
    public static function allKeys(): array
    {
        return array_combine(Page::KEYS, array_map(fn (string $key): array => [$key], Page::KEYS));
    }

    public function test_the_fifteen_keys_defaults_and_routes_line_up(): void
    {
        $this->assertCount(15, Page::KEYS);
        $this->assertSame(Page::KEYS, array_keys(Page::DEFAULTS));
        $this->assertSame(Page::KEYS, array_keys(Page::ROUTES));
        $this->assertSame(
            ['about', 'vision-mission', 'insurance-info', 'registration-guide', 'privacy-policy'],
            Page::KEYS_WITH_BODY,
        );
        $this->assertSame([], array_diff(Page::KEYS_WITH_BODY, Page::KEYS));
        $this->assertSame(Page::KEYS, array_values(array_unique(Page::KEYS)));
    }

    #[DataProvider('defaultRows')]
    public function test_defaults_follow_blueprint_13_section_8_8(string $key, string $title, string $subtitle, string $metaDescription): void
    {
        $this->assertSame($title, Page::DEFAULTS[$key]['title']);
        $this->assertSame($subtitle, Page::DEFAULTS[$key]['subtitle']);
        $this->assertSame($metaDescription, Page::DEFAULTS[$key]['meta_description']);
    }

    #[DataProvider('allKeys')]
    public function test_defaults_respect_the_column_limits(string $key): void
    {
        $this->assertLessThanOrEqual(150, mb_strlen(Page::DEFAULTS[$key]['title']));
        $this->assertLessThanOrEqual(255, mb_strlen(Page::DEFAULTS[$key]['subtitle']));
        $this->assertLessThanOrEqual(160, mb_strlen(Page::DEFAULTS[$key]['meta_description']));
        $this->assertGreaterThanOrEqual(3, mb_strlen(Page::DEFAULTS[$key]['title']));
    }

    public function test_the_route_map_follows_the_canonical_route_names(): void
    {
        $this->assertSame([
            'home' => 'home',
            'about' => 'about',
            'vision-mission' => 'vision-mission',
            'clinics-index' => 'clinics.index',
            'doctors-index' => 'doctors.index',
            'schedules-index' => 'schedules.index',
            'facilities-index' => 'facilities.index',
            'rooms-index' => 'rooms.index',
            'insurance-info' => 'insurance',
            'registration-guide' => 'registration-guide',
            'articles-index' => 'articles.index',
            'galleries-index' => 'galleries.index',
            'faq' => 'faq',
            'contact' => 'contact',
            'privacy-policy' => 'privacy',
        ], Page::ROUTES);
    }

    #[DataProvider('allKeys')]
    public function test_public_route_name_and_has_body_follow_the_maps(string $key): void
    {
        $page = $this->createPage($key);

        $this->assertSame(Page::ROUTES[$key], $page->publicRouteName());
        $this->assertSame(in_array($key, Page::KEYS_WITH_BODY, true), $page->hasBody());
    }

    public function test_for_key_returns_the_stored_row(): void
    {
        $stored = $this->createPage('about', ['title' => 'Tentang Kami', 'body' => '<p>Isi.</p>']);
        $this->createPage('faq');

        $page = Page::forKey('about');

        $this->assertTrue($page->exists);
        $this->assertSame($stored->id, $page->id);
        $this->assertSame('Tentang Kami', $page->title);
        $this->assertSame('<p>Isi.</p>', $page->body);
    }

    public function test_for_key_returns_an_unsaved_instance_from_the_defaults_when_the_row_is_missing(): void
    {
        $this->createPage('faq');

        $page = Page::forKey('clinics-index');

        $this->assertFalse($page->exists);
        $this->assertNull($page->id);
        $this->assertSame('clinics-index', $page->key);
        $this->assertSame('Layanan dan Poliklinik', $page->title);
        $this->assertSame('Pilih layanan sesuai kebutuhan Anda', $page->subtitle);
        $this->assertSame('Daftar poliklinik dan layanan penunjang UNPAM HOSPITAL.', $page->meta_description);
        $this->assertNull($page->body);
        $this->assertNull($page->meta_title);
        $this->assertTrue($page->is_system);
        $this->assertSame('Layanan dan Poliklinik', $page->seo_title);
        $this->assertSame('Daftar poliklinik dan layanan penunjang UNPAM HOSPITAL.', $page->seo_description);
        $this->assertSame('clinics.index', $page->publicRouteName());
        $this->assertSame(1, Page::query()->count(), 'forKey tidak menyimpan apa pun.');
    }

    public function test_for_key_on_an_empty_table_works_for_every_key_without_touching_the_database_rows(): void
    {
        foreach (Page::KEYS as $key) {
            $page = Page::forKey($key);

            $this->assertFalse($page->exists, $key);
            $this->assertSame(Page::DEFAULTS[$key]['title'], $page->title, $key);
            $this->assertNull($page->updater, $key);
        }

        $this->assertSame(0, Page::query()->count());
    }

    public function test_for_key_rejects_keys_outside_the_fifteen(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Page::forKey('tidak-ada');
    }

    public function test_seo_title_falls_back_from_meta_title_to_title(): void
    {
        $withMeta = $this->createPage('about', ['title' => 'Profil', 'meta_title' => 'Profil UNPAM HOSPITAL']);
        $this->assertSame('Profil UNPAM HOSPITAL', $withMeta->seo_title);

        $emptyMeta = $this->createPage('faq', ['title' => 'Tanya Jawab', 'meta_title' => '']);
        $this->assertSame('Tanya Jawab', $emptyMeta->seo_title);

        $noMeta = $this->createPage('contact', ['title' => 'Kontak', 'meta_title' => null]);
        $this->assertSame('Kontak', $noMeta->seo_title);
    }

    public function test_seo_description_falls_back_from_meta_description_to_subtitle_to_null(): void
    {
        $meta = $this->createPage('about', ['meta_description' => 'Deskripsi khusus.', 'subtitle' => 'Subjudul.']);
        $this->assertSame('Deskripsi khusus.', $meta->seo_description);

        $subtitle = $this->createPage('faq', ['meta_description' => '', 'subtitle' => 'Subjudul FAQ.']);
        $this->assertSame('Subjudul FAQ.', $subtitle->seo_description);

        $none = $this->createPage('contact', ['meta_description' => null, 'subtitle' => null]);
        $this->assertNull($none->seo_description);

        $blank = $this->createPage('galleries-index', ['meta_description' => null, 'subtitle' => '']);
        $this->assertNull($blank->seo_description);
    }

    public function test_the_updater_relation_points_to_the_user(): void
    {
        $user = User::factory()->create(['name' => 'Penyunting']);
        $page = $this->createPage('about', ['updated_by' => $user->id]);

        $this->assertTrue($page->updater->is($user));
        $this->assertNull($this->createPage('faq')->updater);
    }

    public function test_the_list_of_pages_with_updater_and_media_does_not_lazy_load(): void
    {
        $user = User::factory()->create();

        foreach (['about', 'faq', 'contact'] as $key) {
            $this->createPage($key, ['updated_by' => $user->id]);
        }

        $pages = Page::query()->with(['updater', 'media'])->orderBy('id')->get();

        $this->assertCount(3, $pages);

        foreach ($pages as $page) {
            $this->assertSame($user->id, $page->updater->id);
            $this->assertCount(0, $page->mediaIn('editor'));
        }
    }

    public function test_body_is_sanitized_on_save_and_the_database_keeps_the_clean_html(): void
    {
        $page = $this->createPage('about', [
            'body' => '<script>alert(1)</script><p onclick="x()">Halo <img src="x" onerror="alert(2)"></p><a href="javascript:alert(3)">klik</a><p style="color:red">Merah</p>',
        ]);

        $stored = (string) DB::table('pages')->where('id', $page->id)->value('body');

        $this->assertSame($stored, $page->body);

        foreach (['<script', 'alert(', 'onclick', 'onerror', 'javascript:', 'style=', '<img'] as $fragment) {
            $this->assertStringNotContainsString($fragment, $stored);
        }

        $this->assertStringContainsString('<p>Halo', $stored);
        $this->assertStringContainsString('Merah', $stored);
    }

    public function test_a_visually_blank_body_is_stored_as_null(): void
    {
        $page = $this->createPage('about', ['body' => '<p>Ada isi.</p>']);

        $page->forceFill(['body' => '<p><br></p>'])->save();

        $this->assertNull($page->fresh()->body);
        $this->assertNull(DB::table('pages')->where('id', $page->id)->value('body'));
    }

    public function test_updating_the_body_again_resanitizes(): void
    {
        $page = $this->createPage('about', ['body' => '<p>Awal</p>']);

        $page->update(['body' => '<iframe src="https://evil.test"></iframe><p>Akhir</p>']);

        $this->assertStringNotContainsString('iframe', (string) $page->fresh()->body);
        $this->assertStringContainsString('<p>Akhir</p>', (string) $page->fresh()->body);
    }

    public function test_fillable_excludes_the_key_and_system_columns(): void
    {
        $this->assertSame(
            ['title', 'subtitle', 'body', 'meta_title', 'meta_description', 'updated_by'],
            (new Page)->getFillable(),
        );

        $this->expectException(MassAssignmentException::class);

        (new Page)->fill(['key' => 'about']);
    }

    public function test_is_system_is_true_by_default_and_cast_to_boolean(): void
    {
        $page = Page::query()->findOrFail($this->createPage('about')->id);

        $this->assertTrue($page->is_system);
        $this->assertSame(1, (int) DB::table('pages')->where('id', $page->id)->value('is_system'));
    }

    public function test_factory_picks_an_unused_key_and_with_body_adds_content(): void
    {
        $this->createPage('home');

        $first = Page::factory()->create();
        $second = Page::factory()->withBody()->create();

        $this->assertNotSame('home', $first->key);
        $this->assertContains($first->key, Page::KEYS);
        $this->assertNotSame($first->key, $second->key);
        $this->assertNull($first->body);
        $this->assertNotNull($second->body);
        $this->assertSame(Page::DEFAULTS[$first->key]['title'], $first->title);
        $this->assertTrue($first->is_system);
    }
}
