<?php

namespace Tests\Feature\Seeding;

use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\Banner;
use App\Models\Faq;
use App\Models\Gallery;
use App\Models\InsurancePartner;
use App\Models\Media;
use App\Models\User;
use Database\Seeders\ContentSeeder;
use Database\Seeders\DemoUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

/**
 * Seeder demo konten (13 §8.3 s.d. §8.7, F4A-R27): 4 kategori, 8 artikel, 3 banner, 3 album, 12 FAQ, 4 mitra, dan 25
 * gambar. Prasyarat hanya akun demo (tanpa data master), agar tes cepat.
 */
class ContentSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->travelTo('2026-10-05 07:00:00');
    }

    private function seedContent(): void
    {
        $this->seed(DemoUserSeeder::class);
        $this->seed(ContentSeeder::class);
    }

    public function test_counts_match_the_reference_numbers(): void
    {
        $this->seedContent();

        $this->assertSame(4, ArticleCategory::query()->count());
        $this->assertSame(8, Article::query()->count());
        $this->assertSame(3, Banner::query()->count());
        $this->assertSame(3, Gallery::query()->count());
        $this->assertSame(12, Faq::query()->count());
        $this->assertSame(4, InsurancePartner::query()->count());
        $this->assertSame(25, Media::query()->count());
    }

    public function test_categories_use_the_documented_names_slugs_and_order(): void
    {
        $this->seedContent();

        $this->assertSame(
            [
                ['Kesehatan Umum', 'kesehatan-umum', 1],
                ['Keluarga dan Anak', 'keluarga-dan-anak', 2],
                ['Gaya Hidup Sehat', 'gaya-hidup-sehat', 3],
                ['Info Layanan', 'info-layanan', 4],
            ],
            ArticleCategory::query()->ordered()->get()->map(fn (ArticleCategory $category): array => [$category->name, $category->slug, $category->sort_order])->all(),
        );
    }

    public function test_articles_keep_the_explicit_slugs_and_publication_dates(): void
    {
        $this->seedContent();

        $expected = [
            'kebiasaan-sederhana-menjaga-kesehatan-jantung' => 3,
            'pentingnya-pemeriksaan-kesehatan-berkala' => 6,
            'menyiapkan-diri-sebelum-berkunjung-ke-poliklinik' => 9,
            'mengenali-tanda-kekurangan-cairan-pada-anak' => 12,
            'pola-tidur-yang-baik-untuk-tubuh-bugar' => 15,
            'cara-mencuci-tangan-yang-benar' => 20,
            'panduan-pendaftaran-online-unpam-hospital' => 1,
        ];

        foreach ($expected as $slug => $days) {
            $article = Article::query()->with('category')->where('slug', $slug)->firstOrFail();

            $this->assertSame(ArticleStatus::Published, $article->status, $slug);
            $this->assertTrue($article->published_at->equalTo(now()->subDays($days)), $slug);
            $this->assertNotNull($article->category, $slug);
        }

        $draft = Article::query()->where('slug', 'draf-menjaga-kesehatan-di-musim-hujan')->firstOrFail();
        $this->assertSame(ArticleStatus::Draft, $draft->status);
        $this->assertNull($draft->published_at);
        $this->assertFalse($draft->isPubliclyVisible());
        $this->assertSame(7, Article::query()->published()->count());
    }

    public function test_articles_are_authored_by_the_demo_content_admin_with_the_standard_closing_sentence(): void
    {
        $this->seedContent();

        $author = User::query()->where('email', 'konten@unpamhospital.test')->firstOrFail();

        foreach (Article::query()->get() as $article) {
            $this->assertSame($author->id, $article->author_user_id, $article->slug);
            $this->assertStringEndsWith(ContentSeeder::CLOSING_SENTENCE.'</p>', trim((string) $article->body), $article->slug);
            $this->assertSame(3, substr_count((string) $article->body, '<p>'), $article->slug);
            $this->assertDoesNotMatchRegularExpression('/\d/', strip_tags((string) $article->body), 'Isi artikel tidak memuat angka medis: '.$article->slug);
            $this->assertNull($article->meta_title);
            $this->assertNull($article->meta_description);
        }
    }

    public function test_article_five_and_the_draft_have_no_cover_and_the_other_six_do(): void
    {
        $this->seedContent();

        $withoutCover = ['pola-tidur-yang-baik-untuk-tubuh-bugar', 'draf-menjaga-kesehatan-di-musim-hujan'];

        foreach (Article::query()->with('media')->get() as $article) {
            if (in_array($article->slug, $withoutCover, true)) {
                $this->assertNull($article->firstMedia('cover'), $article->slug);

                continue;
            }

            $cover = $article->firstMedia('cover');
            $this->assertNotNull($cover, $article->slug);
            $this->assertSame('Ilustrasi contoh '.$article->title, $cover->alt_text);
            $this->assertSame([1200, 675], [$cover->width, $cover->height]);
            $this->assertSame('jpg', $cover->extension);
        }

        $this->assertStringContainsString('images/placeholders/article.svg', Article::query()->with('media')->where('slug', 'pola-tidur-yang-baik-untuk-tubuh-bugar')->firstOrFail()->cover_url);
    }

    public function test_banners_are_active_from_the_start_of_the_day_with_the_documented_buttons(): void
    {
        $this->seedContent();

        $banners = Banner::query()->with('media')->ordered()->get();

        $this->assertSame(
            [
                ['Selamat Datang di UNPAM HOSPITAL', 'Cari Dokter', '/dokter'],
                ['Daftar Kunjungan Lebih Mudah', 'Daftar Kunjungan', '/pasien/pendaftaran/baru'],
                ['Lihat Jadwal Dokter Terbaru', 'Jadwal Dokter', '/jadwal-dokter'],
            ],
            $banners->map(fn (Banner $banner): array => [$banner->title, $banner->button_label, $banner->button_url])->all(),
        );
        $this->assertSame([1, 2, 3], $banners->pluck('sort_order')->all());

        foreach ($banners as $banner) {
            $this->assertTrue($banner->is_active);
            $this->assertTrue($banner->starts_at->equalTo(now()->startOfDay()));
            $this->assertNull($banner->ends_at);
            $this->assertSame('Banner contoh '.$banner->title, $banner->firstMedia('image')->alt_text);
            $this->assertSame([1600, 600], [$banner->firstMedia('image')->width, $banner->firstMedia('image')->height]);
        }

        $this->assertSame(3, Banner::query()->currentlyActive()->count());
        $this->assertTrue($banners[0]->hasUsableButton());
        $this->assertTrue($banners[2]->hasUsableButton());
        // Route pendaftaran pasien baru ada sejak fase 6 (F4A-R15, F6-R31): tombol banner #2 kini tampil.
        $this->assertTrue($banners[1]->hasUsableButton());
    }

    public function test_galleries_have_four_labelled_photos_each_in_order(): void
    {
        $this->seedContent();

        $galleries = Gallery::query()->with('media')->ordered()->get();

        $this->assertSame(
            [
                ['Lobi dan Area Pendaftaran (Foto Contoh)', 'lobi-dan-area-pendaftaran'],
                ['Fasilitas Pelayanan (Foto Contoh)', 'fasilitas-pelayanan'],
                ['Edukasi Kesehatan (Foto Contoh)', 'edukasi-kesehatan'],
            ],
            $galleries->map(fn (Gallery $gallery): array => [$gallery->title, $gallery->slug])->all(),
        );

        foreach ($galleries as $gallery) {
            $this->assertTrue($gallery->is_published);
            $photos = $gallery->mediaIn('photos');
            $this->assertCount(4, $photos, $gallery->slug);
            $this->assertSame([1, 2, 3, 4], $photos->pluck('sort_order')->all());

            foreach ($photos as $photo) {
                $this->assertStringStartsWith('Foto contoh ', $photo->alt_text);
                $this->assertStringStartsWith('Foto contoh: '.$gallery->title.', foto ', (string) $photo->caption);
                $this->assertLessThanOrEqual(255, mb_strlen((string) $photo->alt_text));
                $this->assertSame([1200, 800], [$photo->width, $photo->height]);
            }

            $this->assertSame($photos->first()->id, $gallery->coverMedia()->id);
        }

        $this->assertSame('Foto contoh Lobi dan Area Pendaftaran (Foto Contoh) 1', $galleries[0]->mediaIn('photos')->first()->alt_text);
    }

    public function test_faqs_are_twelve_published_questions_grouped_as_documented(): void
    {
        $this->seedContent();

        $faqs = Faq::query()->ordered()->get();

        $this->assertSame(range(1, 12), $faqs->pluck('sort_order')->all());
        $this->assertSame(['pendaftaran' => 6, 'bpjs' => 2, 'layanan' => 2, 'umum' => 2], $faqs->countBy('category')->all());
        $this->assertTrue($faqs->every(fn (Faq $faq): bool => $faq->is_published));
        $this->assertSame('Bagaimana cara mendaftar kunjungan rawat jalan secara online?', $faqs->first()->question);
        $this->assertSame('Bagaimana menghubungi rumah sakit?', $faqs->last()->question);
        $this->assertSame('Tidak. Sistem ini tidak meminta NIK.', $faqs->firstWhere('question', 'Apakah saya perlu mengisi NIK?')->answer);
    }

    public function test_partners_and_labels_are_marked_as_examples_without_real_cooperation_claims(): void
    {
        $this->seedContent();

        $partners = InsurancePartner::query()->with('media')->ordered()->get();

        $this->assertSame(
            [
                ['Asuransi Contoh Sehat', 'asuransi-contoh-sehat', 'asuransi'],
                ['Asuransi Contoh Sentosa', 'asuransi-contoh-sentosa', 'asuransi'],
                ['Asuransi Contoh Prima', 'asuransi-contoh-prima', 'asuransi'],
                ['PT Contoh Karya Mandiri', 'pt-contoh-karya-mandiri', 'perusahaan'],
            ],
            $partners->map(fn (InsurancePartner $partner): array => [$partner->name, $partner->slug, $partner->type])->all(),
        );

        foreach ($partners as $partner) {
            $this->assertTrue($partner->is_active);
            $this->assertNull($partner->website_url);
            $this->assertStringContainsString('Contoh', $partner->name);
            $this->assertStringEndsWith('Bukan kerja sama nyata.', (string) $partner->description);

            $logo = $partner->firstMedia('logo');
            $this->assertSame('Logo contoh '.$partner->name, $logo->alt_text);
            $this->assertSame([400, 200], [$logo->width, $logo->height]);
            $this->assertSame('png', $logo->extension);
        }
    }

    public function test_media_counts_per_collection_and_files_exist(): void
    {
        $this->seedContent();

        $this->assertSame(
            ['cover' => 6, 'image' => 3, 'logo' => 4, 'photos' => 12],
            Media::query()->selectRaw('collection, COUNT(*) as total')->groupBy('collection')->orderBy('collection')->pluck('total', 'collection')->map(fn ($total): int => (int) $total)->all(),
        );

        foreach (Media::query()->get() as $media) {
            Storage::disk('public')->assertExists($media->directory.'/'.$media->file_name);
            Storage::disk('public')->assertExists((string) $media->thumb_path);
        }
    }

    public function test_running_the_seeder_twice_does_not_duplicate_anything(): void
    {
        $this->seedContent();
        $this->seed(ContentSeeder::class);

        $this->assertSame([4, 8, 3, 3, 12, 4, 25], [
            ArticleCategory::query()->count(),
            Article::query()->count(),
            Banner::query()->count(),
            Gallery::query()->count(),
            Faq::query()->count(),
            InsurancePartner::query()->count(),
            Media::query()->count(),
        ]);
    }

    public function test_a_soft_deleted_article_is_not_recreated(): void
    {
        $this->seedContent();
        Article::query()->where('slug', 'cara-mencuci-tangan-yang-benar')->firstOrFail()->delete();

        $this->seed(ContentSeeder::class);

        $this->assertSame(7, Article::query()->count());
        $this->assertSame(8, Article::query()->withTrashed()->count());
    }

    public function test_the_seeder_throws_outside_local_and_testing_and_writes_nothing(): void
    {
        $this->seed(DemoUserSeeder::class);

        $original = $this->app['env'];
        $this->app['env'] = 'production';
        $thrown = null;

        try {
            $this->app->make(ContentSeeder::class)->run();
        } catch (RuntimeException $e) {
            $thrown = $e;
        } finally {
            $this->app['env'] = $original;
        }

        $this->assertInstanceOf(RuntimeException::class, $thrown);
        $this->assertSame('ContentSeeder DILARANG di APP_ENV=production. Seeder konten contoh hanya untuk data demo.', $thrown->getMessage());
        $this->assertSame(0, DB::table('articles')->count() + DB::table('article_categories')->count() + DB::table('media')->count());
    }

    public function test_the_seeder_requires_the_demo_content_admin_account(): void
    {
        $thrown = null;

        try {
            $this->app->make(ContentSeeder::class)->run();
        } catch (RuntimeException $e) {
            $thrown = $e;
        }

        $this->assertInstanceOf(RuntimeException::class, $thrown);
        $this->assertStringContainsString('Jalankan DemoUserSeeder lebih dulu.', $thrown->getMessage());
        $this->assertSame(0, Article::query()->count() + ArticleCategory::query()->count() + Media::query()->count());
    }

    public function test_media_prune_dry_run_reports_no_orphans_after_content_seeding(): void
    {
        $this->seedContent();

        $this->artisan('media:prune', ['--dry-run' => true])
            ->expectsOutputToContain('Baris media yatim: 0')
            ->expectsOutputToContain('Berkas yatim: 0')
            ->assertExitCode(0);
    }

    public function test_the_content_seeder_is_not_called_by_the_database_seeder_and_users_stay_untouched(): void
    {
        $source = (string) file_get_contents(database_path('seeders/DatabaseSeeder.php'));

        $this->assertStringNotContainsString('ContentSeeder', $source);
        $this->seedContent();
        $this->assertSame(3, DB::table('users')->count());
    }
}
