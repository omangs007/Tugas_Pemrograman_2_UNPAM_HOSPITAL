<?php

namespace Tests\Feature\Public;

use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Models\Media;
use App\Services\Media\MediaService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ImageFixtures;
use Tests\Support\InteractsWithContent;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

/**
 * Halaman #18 `articles.index` dan #19 `articles.show` (05 hal. 18-19, F4A-R5/R10/R12/R13): hanya artikel yang tayang
 * (terbit dan `published_at` <= sekarang, belum dihapus), filter `kategori` dan `q` yang longgar, paginasi sembilan,
 * dan SEO artikel. Mencakup CRD-08, VAL-07, EMP-02, serta Review Focus 1, 2, dan 3.
 */
class PublicArticlesTest extends TestCase
{
    use InteractsWithContent;
    use InteractsWithStaff;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->travelTo('2026-10-05 10:00:00');
    }

    private function cover(Article $article, ?string $alt = null): Media
    {
        return $this->app->make(MediaService::class)->store(
            ImageFixtures::jpeg(1200, 675, 'cover.jpg'),
            'cover',
            $article,
            ['alt_text' => $alt ?? 'Cover '.$article->title],
        );
    }

    private function withoutCategory(Article $article): Article
    {
        Article::query()->whereKey($article->id)->update(['article_category_id' => null]);

        return $article->refresh();
    }

    private function withoutAuthor(Article $article): Article
    {
        Article::query()->whereKey($article->id)->update(['author_user_id' => null]);

        return $article->refresh();
    }

    private function showUrl(Article $article): string
    {
        return route('articles.show', $article->slug);
    }

    private function noindexTag(): string
    {
        return '<meta name="robots" content="noindex,nofollow">';
    }

    // --- #18: header, SEO, formulir (F4A-R5) ----------------------------------------------------------

    public function test_index_renders_the_default_header_seo_breadcrumb_and_search_form(): void
    {
        $this->createArticle(['title' => 'Satu Artikel']);

        $response = $this->get(route('articles.index'))->assertOk();

        $response->assertSee('<title>Artikel Kesehatan — '.config('app.name').'</title>', false)
            ->assertSee('<h1 class="mb-1">Artikel Kesehatan</h1>', false)
            ->assertSee('Informasi kesehatan umum')
            ->assertSee('<meta name="description" content="Artikel kesehatan umum dari UNPAM HOSPITAL.">', false)
            ->assertSee('<meta property="og:title" content="Artikel Kesehatan">', false)
            ->assertSee('<link rel="canonical" href="'.route('articles.index').'">', false)
            ->assertSeeInOrder(['aria-label="Breadcrumb"', 'Beranda', 'Artikel'], false)
            ->assertSee('<li class="breadcrumb-item active" aria-current="page">Artikel</li>', false)
            ->assertSee('role="search"', false)
            ->assertSee('name="q"', false)
            ->assertSee('name="kategori"', false)
            ->assertSee('Semua kategori')
            ->assertSee('Reset')
            ->assertDontSee($this->noindexTag(), false);
        $this->assertSame(1, substr_count($response->getContent(), '<h1'));
    }

    public function test_index_header_and_seo_follow_the_articles_index_page_row(): void
    {
        $this->createPage('articles-index', [
            'title' => 'Warta Sehat',
            'subtitle' => 'Kabar kesehatan terbaru',
            'meta_title' => 'Warta Sehat Meta',
            'meta_description' => 'Deskripsi warta sehat.',
        ]);
        $this->createArticle();

        $this->get(route('articles.index'))
            ->assertOk()
            ->assertSee('<title>Warta Sehat Meta — '.config('app.name').'</title>', false)
            ->assertSee('<h1 class="mb-1">Warta Sehat</h1>', false)
            ->assertSee('Kabar kesehatan terbaru')
            ->assertSee('<meta name="description" content="Deskripsi warta sehat.">', false)
            ->assertSee('<li class="breadcrumb-item active" aria-current="page">Artikel</li>', false);
    }

    // --- tayang: hanya terbit dan sudah waktunya (CRD-08, Review Focus 1) --------------------------------

    public function test_index_lists_only_live_articles_newest_first_with_inclusive_time_bounds(): void
    {
        $this->createArticle(['title' => 'Artikel Lama', 'published_at' => now()->subDays(3)]);
        $this->createArticle(['title' => 'Artikel Baru', 'published_at' => now()->subDay()]);
        $this->createArticle(['title' => 'Judul Sekarang', 'published_at' => now()]);
        $this->createArticle(['title' => 'Judul Semenit Lalu', 'published_at' => now()->subMinute()]);
        $this->createArticle(['title' => 'Judul Semenit Lagi', 'published_at' => now()->addMinute()]);
        $this->createArticle(['title' => 'Draf Rahasia', 'status' => ArticleStatus::Draft, 'published_at' => now()->subDay()]);
        $this->createArticle(['title' => 'Terhapus Rahasia'])->delete();

        $this->get(route('articles.index'))
            ->assertOk()
            ->assertSeeInOrder(['Judul Sekarang', 'Judul Semenit Lalu', 'Artikel Baru', 'Artikel Lama'])
            ->assertSee('Menampilkan 4 dari 4 artikel')
            ->assertDontSee('Judul Semenit Lagi')
            ->assertDontSee('Draf Rahasia')
            ->assertDontSee('Terhapus Rahasia');
    }

    public function test_a_scheduled_article_goes_live_when_its_time_arrives(): void
    {
        $article = $this->createArticle(['title' => 'Segera Tayang', 'published_at' => now()->addMinutes(30)]);

        $this->get(route('articles.index'))->assertOk()->assertDontSee('Segera Tayang');
        $this->get($this->showUrl($article))->assertNotFound();

        $this->travelTo('2026-10-05 10:30:00');

        $this->get(route('articles.index'))->assertOk()->assertSee('Segera Tayang');
        $this->get($this->showUrl($article))->assertOk()->assertSee('Segera Tayang');
    }

    public function test_detail_boundaries_now_and_one_minute_ago_are_live_and_one_minute_ahead_is_not(): void
    {
        $now = $this->createArticle(['title' => 'Tepat Sekarang', 'published_at' => now()]);
        $ago = $this->createArticle(['title' => 'Semenit Lalu', 'published_at' => now()->subMinute()]);
        $ahead = $this->createArticle(['title' => 'Semenit Lagi', 'published_at' => now()->addMinute()]);

        $this->get($this->showUrl($now))->assertOk();
        $this->get($this->showUrl($ago))->assertOk();
        $this->get($this->showUrl($ahead))->assertNotFound();
    }

    public function test_draft_scheduled_and_trashed_articles_are_404_for_everyone_including_staff(): void
    {
        $draft = $this->createArticle(['title' => 'Draf', 'status' => ArticleStatus::Draft, 'published_at' => now()->subDay()]);
        $scheduled = $this->createArticle(['title' => 'Terjadwal', 'published_at' => now()->addDay()]);
        $trashed = $this->createArticle(['title' => 'Terhapus']);
        $trashed->delete();

        foreach ([$draft, $scheduled, $trashed] as $article) {
            $this->get($this->showUrl($article))->assertNotFound();
        }

        foreach (['content-admin', 'super-admin'] as $role) {
            $this->actingAs($this->createStaff($role));

            foreach ([$draft, $scheduled, $trashed] as $article) {
                $this->get($this->showUrl($article))->assertNotFound();
            }
        }
    }

    public function test_an_unknown_slug_is_404(): void
    {
        $this->get(route('articles.show', 'tidak-ada'))->assertNotFound();
        $this->get('/artikel/Tidak%20Ada')->assertNotFound();
    }

    // --- filter kategori dan pencarian (CRD-09, F4A-R12) --------------------------------------------------

    public function test_filtering_by_category_lists_only_its_articles_and_adds_the_category_to_the_title(): void
    {
        $gizi = $this->createArticleCategory(['name' => 'Gizi']);
        $jantung = $this->createArticleCategory(['name' => 'Jantung']);
        $this->createArticle(['title' => 'Pola Makan Seimbang'], $gizi);
        $this->createArticle(['title' => 'Menjaga Jantung'], $jantung);

        $this->get(route('articles.index', ['kategori' => 'gizi']))
            ->assertOk()
            ->assertSee('<title>Artikel Kesehatan — Gizi — '.config('app.name').'</title>', false)
            ->assertSee('Pola Makan Seimbang')
            ->assertDontSee('Menjaga Jantung')
            ->assertSee('<link rel="canonical" href="'.route('articles.index').'">', false)
            ->assertSee('<option value="gizi" selected>Gizi</option>', false);
    }

    public function test_the_category_select_lists_every_category_in_order(): void
    {
        $this->createArticleCategory(['name' => 'Jantung', 'sort_order' => 2]);
        $this->createArticleCategory(['name' => 'Gizi', 'sort_order' => 1]);

        $this->get(route('articles.index'))
            ->assertOk()
            ->assertSeeInOrder(['>Semua kategori</option>', '>Gizi</option>', '>Jantung</option>'], false);
    }

    public function test_searching_matches_title_and_excerpt_case_insensitively_and_trims_the_term(): void
    {
        $this->createArticle(['title' => 'Pola Makan Seimbang', 'excerpt' => 'Tips gizi harian.']);
        $this->createArticle(['title' => 'Olahraga Ringan', 'excerpt' => 'Gerak tiap hari.']);
        $this->createArticle(['title' => 'Tidur Cukup', 'excerpt' => 'Istirahat yang baik.']);

        $this->get(route('articles.index', ['q' => 'MAKAN']))->assertOk()->assertSee('Pola Makan Seimbang')->assertDontSee('Olahraga Ringan')->assertSee('Menampilkan 1 dari 1 artikel');
        $this->get(route('articles.index', ['q' => '  gerak  ']))->assertOk()->assertSee('Olahraga Ringan')->assertDontSee('Tidur Cukup');
        $this->get(route('articles.index', ['q' => 'gerak']))->assertOk()->assertSee('value="gerak"', false);
    }

    public function test_like_wildcards_in_the_search_term_are_matched_literally(): void
    {
        $this->createArticle(['title' => 'Kolesterol 100% Terkontrol']);
        $this->createArticle(['title' => 'Tips_Sehat Harian']);
        $this->createArticle(['title' => 'Artikel Biasa']);

        $this->get(route('articles.index', ['q' => '%']))->assertOk()->assertSee('Kolesterol 100% Terkontrol')->assertDontSee('Artikel Biasa')->assertDontSee('Tips_Sehat Harian');
        $this->get(route('articles.index', ['q' => '_']))->assertOk()->assertSee('Tips_Sehat Harian')->assertDontSee('Artikel Biasa')->assertDontSee('Kolesterol 100%');
    }

    public function test_category_and_search_combine_and_a_search_never_reveals_hidden_articles(): void
    {
        $gizi = $this->createArticleCategory(['name' => 'Gizi']);
        $this->createArticle(['title' => 'Sayur Hijau Segar'], $gizi);
        $this->createArticle(['title' => 'Sayur Draf Rahasia', 'status' => ArticleStatus::Draft], $gizi);
        $this->createArticle(['title' => 'Sayur Lain Kategori']);

        $this->get(route('articles.index', ['kategori' => 'gizi', 'q' => 'sayur']))
            ->assertOk()
            ->assertSee('Sayur Hijau Segar')
            ->assertDontSee('Sayur Draf Rahasia')
            ->assertDontSee('Sayur Lain Kategori');
    }

    // --- parameter publik tidak valid (Review Focus 3, VAL-07) ----------------------------------------------

    /** @return array<string, array{string}> */
    public static function invalidQueries(): array
    {
        return [
            'kategori null byte' => ['kategori=%00'],
            'kategori null byte ganda' => ['kategori=%00%00'],
            'kategori array' => ['kategori[]=x'],
            'kategori array asosiatif' => ['kategori[a]=b'],
            'kategori huruf besar' => ['kategori=GIZI'],
            'kategori tidak ada' => ['kategori=tidak-ada'],
            'kategori terlalu panjang' => ['kategori='.str_repeat('a', 200)],
            'q array' => ['q[]=x'],
            'q array asosiatif' => ['q[a]=1'],
            'q 101 karakter' => ['q='.str_repeat('a', 101)],
            'q spasi saja' => ['q=%20%20'],
            'page bukan angka' => ['page=abc'],
            'page nol' => ['page=0'],
            'page negatif' => ['page=-5'],
            'page array' => ['page[]=1'],
            'page array asosiatif' => ['page[a]=b'],
            'per_page tidak dibuka' => ['per_page=1000'],
        ];
    }

    #[DataProvider('invalidQueries')]
    public function test_invalid_public_parameters_are_ignored_without_a_filter_or_an_error(string $query): void
    {
        $gizi = $this->createArticleCategory(['name' => 'Gizi']);
        $this->createArticle(['title' => 'Artikel Satu'], $gizi);
        $this->createArticle(['title' => 'Artikel Dua']);
        $this->createArticle(['title' => 'Artikel Tiga']);

        $response = $this->get('/artikel?'.$query)->assertOk();

        $response->assertSee('Artikel Satu')
            ->assertSee('Artikel Dua')
            ->assertSee('Artikel Tiga')
            ->assertSee('Menampilkan 3 dari 3 artikel')
            ->assertDontSee('Artikel tidak ditemukan')
            ->assertDontSee($this->noindexTag(), false);
        $this->assertSame(1, substr_count($response->getContent(), '<h1'));
    }

    public function test_a_valid_category_is_still_applied_when_the_search_term_is_invalid(): void
    {
        $gizi = $this->createArticleCategory(['name' => 'Gizi']);
        $this->createArticle(['title' => 'Dalam Gizi'], $gizi);
        $this->createArticle(['title' => 'Luar Gizi']);

        $this->get('/artikel?kategori=gizi&q[]=x')
            ->assertOk()
            ->assertSee('Dalam Gizi')
            ->assertDontSee('Luar Gizi');
    }

    // --- paginasi sembilan (F4A-R13) ----------------------------------------------------------------------------

    public function test_the_list_is_paginated_by_nine_and_page_two_adds_the_page_to_the_title(): void
    {
        foreach (range(1, 11) as $number) {
            $this->createArticle(['title' => sprintf('Artikel Nomor %02d', $number), 'published_at' => now()->subHours($number)]);
        }

        $first = (string) $this->get(route('articles.index'))->assertOk()->getContent();
        $second = (string) $this->get(route('articles.index', ['page' => 2]))->assertOk()->getContent();

        $this->assertSame(9, substr_count($first, 'article-card'));
        $this->assertStringContainsString('Artikel Nomor 01', $first);
        $this->assertStringContainsString('Artikel Nomor 09', $first);
        $this->assertStringNotContainsString('Artikel Nomor 10', $first);
        $this->assertStringContainsString('Menampilkan 9 dari 11 artikel', $first);
        $this->assertStringContainsString('<title>Artikel Kesehatan — '.config('app.name').'</title>', $first);

        $this->assertSame(2, substr_count($second, 'article-card'));
        $this->assertStringContainsString('Artikel Nomor 10', $second);
        $this->assertStringContainsString('Artikel Nomor 11', $second);
        $this->assertStringContainsString('<title>Artikel Kesehatan — Halaman 2 — '.config('app.name').'</title>', $second);
        $this->assertStringContainsString('<link rel="canonical" href="'.route('articles.index').'">', $second);
    }

    public function test_pagination_links_keep_valid_filters_only(): void
    {
        $gizi = $this->createArticleCategory(['name' => 'Gizi']);

        foreach (range(1, 10) as $number) {
            $this->createArticle(['title' => "Gizi Nomor {$number}", 'published_at' => now()->subHours($number)], $gizi);
        }

        $html = (string) $this->get('/artikel?kategori=gizi&q='.str_repeat('a', 101))->assertOk()->getContent();

        $this->assertStringContainsString('kategori=gizi&amp;page=2', $html);
        $this->assertStringNotContainsString('q=aaaa', $html);
        $this->assertSame(9, substr_count($html, 'article-card'));

        $this->get('/artikel?kategori=gizi&page=2')
            ->assertOk()
            ->assertSee('<title>Artikel Kesehatan — Gizi — Halaman 2 — '.config('app.name').'</title>', false);
    }

    public function test_a_page_beyond_the_last_one_shows_a_notice_and_is_noindex(): void
    {
        $this->createArticle(['title' => 'Satu-satunya']);

        $this->get(route('articles.index', ['page' => 9]))
            ->assertOk()
            ->assertSee('Halaman yang diminta tidak berisi data.')
            ->assertSee('Kembali ke halaman 1')
            ->assertSee($this->noindexTag(), false)
            ->assertDontSee('Artikel tidak ditemukan');
    }

    // --- kosong: EMP-02 -----------------------------------------------------------------------------------------

    public function test_an_empty_list_shows_the_empty_state_with_a_reset_link_and_noindex(): void
    {
        $response = $this->get(route('articles.index'))->assertOk();

        $response->assertSee('Artikel tidak ditemukan')
            ->assertSee('Coba ubah kata kunci atau pilih kategori lain.')
            ->assertSee('Reset filter')
            ->assertSee('href="'.route('articles.index').'"', false)
            ->assertSee($this->noindexTag(), false)
            ->assertDontSee('Menampilkan');
        $this->assertSame(1, substr_count($response->getContent(), '<h1'));
    }

    public function test_a_search_without_results_shows_the_empty_state_and_noindex(): void
    {
        $this->createArticle(['title' => 'Pola Makan']);

        $this->get(route('articles.index', ['q' => 'tidakadakatakunci']))
            ->assertOk()
            ->assertSee('Artikel tidak ditemukan')
            ->assertSee($this->noindexTag(), false)
            ->assertDontSee('Pola Makan');
    }

    public function test_a_category_without_live_articles_shows_the_empty_state(): void
    {
        $kosong = $this->createArticleCategory(['name' => 'Kosong']);
        $this->createArticle(['title' => 'Draf Saja', 'status' => ArticleStatus::Draft], $kosong);
        $this->createArticle(['title' => 'Di Kategori Lain']);

        $this->get(route('articles.index', ['kategori' => 'kosong']))
            ->assertOk()
            ->assertSee('Artikel tidak ditemukan')
            ->assertDontSee('Draf Saja')
            ->assertDontSee('Di Kategori Lain');
    }

    // --- kartu: cover dan placeholder -----------------------------------------------------------------------------

    public function test_a_card_without_cover_uses_the_local_article_placeholder(): void
    {
        $this->createArticle(['title' => 'Tanpa Cover']);

        $this->get(route('articles.index'))
            ->assertOk()
            ->assertSee('images/placeholders/article.svg', false)
            ->assertDontSee('onerror', false);
    }

    /**
     * `x-image` selalu menulis `data-fallback`; berkas yang hilang di disk ditangani JS (`recheckBrokenImages` dan
     * listener `error`) dan diuji manual, bukan di tes Feature ini.
     */
    public function test_a_card_shows_the_cover_thumbnail_and_keeps_the_alt_and_exposes_the_placeholder_fallback_attribute(): void
    {
        $article = $this->createArticle(['title' => 'Dengan Cover']);
        $this->cover($article, 'Cover contoh artikel');

        $this->get(route('articles.index'))
            ->assertOk()
            ->assertSee('alt="Cover contoh artikel"', false)
            ->assertSee('/thumbs/', false)
            ->assertSee('data-fallback="'.asset('images/placeholders/article.svg').'"', false);
    }

    public function test_a_card_shows_category_date_title_excerpt_and_a_single_stretched_link(): void
    {
        $gizi = $this->createArticleCategory(['name' => 'Gizi']);
        $article = $this->createArticle([
            'title' => 'Sarapan Sehat',
            'excerpt' => 'Ringkasan sarapan sehat.',
            'published_at' => '2026-10-01 08:00:00',
        ], $gizi);

        $response = $this->get(route('articles.index'))->assertOk();

        $response->assertSee('<h2 class="card-title h5 mb-2">', false)
            ->assertSee('href="'.$this->showUrl($article).'"', false)
            ->assertSee('Ringkasan sarapan sehat.')
            ->assertSee('Gizi')
            ->assertSee('<time datetime="2026-10-01">1 Okt 2026</time>', false)
            ->assertSee('Baca selengkapnya');
        $this->assertSame(1, substr_count($response->getContent(), 'stretched-link'));
        $this->assertSame(0, preg_match('/<a\b[^>]*>(?:(?!<\/a>).)*<a\b/s', $response->getContent()));
    }

    // --- #19: detail ----------------------------------------------------------------------------------------------------

    public function test_detail_renders_a_sanitized_body_inside_the_article_body_wrapper(): void
    {
        $article = $this->createArticle([
            'title' => 'Isi Artikel',
            'body' => '<h2>Subbagian</h2><p>Paragraf aman.</p><script>alert(1)</script><p onclick="x()" style="color:red">Gaya</p><img src="x" onerror="alert(2)">',
        ]);

        $response = $this->get($this->showUrl($article))->assertOk();

        $response->assertSee('<div class="rich-text article-body">', false)
            ->assertSee('<h2>Subbagian</h2>', false)
            ->assertSee('Paragraf aman.')
            ->assertDontSee('<script>alert', false)
            ->assertDontSee('onclick', false)
            ->assertDontSee('onerror', false)
            ->assertDontSee('color:red', false);
        $this->assertSame(1, substr_count($response->getContent(), '<h1'));
        $this->assertSame(1, substr_count($response->getContent(), 'class="rich-text article-body"'));
    }

    public function test_detail_header_shows_category_date_and_reading_time_and_the_author(): void
    {
        $gizi = $this->createArticleCategory(['name' => 'Gizi']);
        $article = $this->createArticle([
            'title' => 'Artikel Panjang',
            'body' => '<p>'.trim(str_repeat('kata ', 450)).'</p>',
            'published_at' => '2026-10-01 08:00:00',
            'author_user_id' => $this->createStaff('content-admin', ['name' => 'Dewi Penulis'])->id,
        ], $gizi);

        $this->get($this->showUrl($article))
            ->assertOk()
            ->assertSee('<h1 class="mb-1">Artikel Panjang</h1>', false)
            ->assertSee('Gizi · 1 Oktober 2026 · ± 3 menit baca')
            ->assertSee('Ditulis oleh Dewi Penulis');
    }

    public function test_detail_without_category_and_author_has_no_category_text_author_or_related_section(): void
    {
        $article = $this->withoutAuthor($this->withoutCategory($this->createArticle([
            'title' => 'Tanpa Kategori',
            'body' => '<p>Singkat.</p>',
            'published_at' => '2026-10-01 08:00:00',
        ])));
        $this->createArticle(['title' => 'Artikel Lain']);

        $this->get($this->showUrl($article))
            ->assertOk()
            ->assertSee('1 Oktober 2026 · ± 1 menit baca')
            ->assertDontSee('Ditulis oleh')
            ->assertDontSee('Artikel Terkait')
            ->assertDontSee('Artikel Lain');
    }

    public function test_detail_shows_at_most_three_related_articles_from_the_same_category_without_itself(): void
    {
        $gizi = $this->createArticleCategory(['name' => 'Gizi']);
        $jantung = $this->createArticleCategory(['name' => 'Jantung']);
        $utama = $this->createArticle(['title' => 'Artikel Utama', 'published_at' => now()->subDays(10)], $gizi);

        foreach (range(1, 5) as $number) {
            $this->createArticle(['title' => "Terkait Nomor {$number}", 'published_at' => now()->subDays($number)], $gizi);
        }

        $this->createArticle(['title' => 'Kategori Lain', 'published_at' => now()->subHours(1)], $jantung);
        $this->createArticle(['title' => 'Draf Terkait', 'status' => ArticleStatus::Draft, 'published_at' => now()->subHours(2)], $gizi);
        $this->createArticle(['title' => 'Terjadwal Terkait', 'published_at' => now()->addDay()], $gizi);

        $response = $this->get($this->showUrl($utama))->assertOk();

        $response->assertSee('<h2 id="terkait-judul" class="h4 mb-3">Artikel Terkait</h2>', false)
            ->assertSeeInOrder(['Artikel Terkait', 'Terkait Nomor 1', 'Terkait Nomor 2', 'Terkait Nomor 3'])
            ->assertDontSee('Terkait Nomor 4')
            ->assertDontSee('Terkait Nomor 5')
            ->assertDontSee('Kategori Lain')
            ->assertDontSee('Draf Terkait')
            ->assertDontSee('Terjadwal Terkait');
        $this->assertSame(3, substr_count($response->getContent(), 'article-card'));
        $this->assertSame(1, substr_count($response->getContent(), 'Artikel Utama</h1>'));
    }

    public function test_detail_has_a_back_link_and_the_breadcrumb_ends_with_the_title(): void
    {
        $article = $this->createArticle(['title' => 'Jejak Artikel']);

        $this->get($this->showUrl($article))
            ->assertOk()
            ->assertSeeInOrder(['aria-label="Breadcrumb"', 'Beranda', 'Artikel', 'Jejak Artikel'], false)
            ->assertSee('<li class="breadcrumb-item"><a href="'.route('articles.index').'">Artikel</a></li>', false)
            ->assertSee('<li class="breadcrumb-item active" aria-current="page">Jejak Artikel</li>', false)
            ->assertSee('Kembali ke daftar artikel')
            ->assertSee('href="'.route('articles.index').'"', false);
    }

    public function test_detail_seo_uses_the_article_fields_cover_and_canonical(): void
    {
        $article = $this->createArticle([
            'title' => 'Judul Artikel SEO',
            'excerpt' => 'Ringkasan lain.',
            'meta_title' => 'Judul SEO Khusus',
            'meta_description' => 'Deskripsi SEO khusus.',
        ]);
        $cover = $this->cover($article);

        $this->get($this->showUrl($article))
            ->assertOk()
            ->assertSee('<title>Judul SEO Khusus — '.config('app.name').'</title>', false)
            ->assertSee('<meta name="description" content="Deskripsi SEO khusus.">', false)
            ->assertSee('<meta property="og:type" content="article">', false)
            ->assertSee('<meta property="og:title" content="Judul SEO Khusus">', false)
            ->assertSee('<meta property="og:description" content="Deskripsi SEO khusus.">', false)
            ->assertSee('<meta property="og:image" content="'.url($cover->url).'">', false)
            ->assertSee('<link rel="canonical" href="'.$this->showUrl($article).'">', false)
            ->assertSee('alt="Cover Judul Artikel SEO"', false)
            ->assertDontSee($this->noindexTag(), false);
    }

    public function test_detail_seo_falls_back_to_the_title_and_the_excerpt_without_a_cover(): void
    {
        $article = $this->createArticle([
            'title' => 'Judul Tanpa Meta',
            'excerpt' => 'Ringkasan sebagai deskripsi.',
            'meta_title' => null,
            'meta_description' => null,
        ]);

        $this->get($this->showUrl($article))
            ->assertOk()
            ->assertSee('<title>Judul Tanpa Meta — '.config('app.name').'</title>', false)
            ->assertSee('<meta name="description" content="Ringkasan sebagai deskripsi.">', false)
            ->assertDontSee('og:image', false)
            ->assertDontSee('images/placeholders/article.svg', false);
    }

    public function test_the_detail_page_is_identical_for_guests_patients_and_staff(): void
    {
        $article = $this->createArticle(['title' => 'Terbuka Untuk Semua']);

        $this->get($this->showUrl($article))->assertOk()->assertSee('Terbuka Untuk Semua');

        foreach (['content-admin', 'registration-officer', 'super-admin'] as $role) {
            $this->actingAs($this->createStaff($role))->get($this->showUrl($article))->assertOk()->assertSee('Terbuka Untuk Semua');
        }
    }

    // --- mode ketat dan keamanan markup -----------------------------------------------------------------------------------

    public function test_the_list_and_the_detail_render_many_rows_with_covers_in_strict_mode(): void
    {
        $this->assertTrue(Model::preventsLazyLoading());

        $categories = [
            $this->createArticleCategory(['name' => 'Gizi']),
            $this->createArticleCategory(['name' => 'Jantung']),
            $this->createArticleCategory(['name' => 'Anak']),
        ];
        $author = $this->createStaff('content-admin', ['name' => 'Penulis Uji']);
        $articles = [];

        foreach (range(1, 12) as $number) {
            $article = $this->createArticle([
                'title' => "Artikel Mode Ketat {$number}",
                'published_at' => now()->subHours($number),
                'author_user_id' => $author->id,
            ], $categories[$number % 3]);
            $this->cover($article, "Cover mode ketat {$number}");
            $articles[] = $article;
        }

        $list = (string) $this->get(route('articles.index'))->assertOk()->getContent();

        $this->assertSame(9, substr_count($list, 'article-card'));
        $this->assertStringContainsString('alt="Cover mode ketat 3"', $list);
        $this->assertStringContainsString('/thumbs/', $list);

        $detail = (string) $this->get($this->showUrl($articles[0]))->assertOk()->getContent();

        $this->assertStringContainsString('Ditulis oleh Penulis Uji', $detail);
        $this->assertSame(3, substr_count($detail, 'article-card'));
    }

    public function test_pages_have_no_inline_handlers_javascript_urls_or_nested_links(): void
    {
        $article = $this->createArticle(['title' => 'Aman Markup']);
        $this->cover($article);
        $this->createArticle(['title' => 'Satu Lagi']);

        foreach ([route('articles.index'), $this->showUrl($article)] as $uri) {
            $html = (string) $this->get($uri)->assertOk()->getContent();

            $this->assertSame(0, preg_match('/\son[a-z]+\s*=/i', $html), "Atribut event inline ditemukan di {$uri}.");
            $this->assertStringNotContainsString('javascript:', $html);
            $this->assertStringNotContainsString('onerror', $html);
            $this->assertSame(0, preg_match('/<a\b[^>]*>(?:(?!<\/a>).)*<a\b/s', $html), "Tautan bersarang di {$uri}.");
        }
    }
}
