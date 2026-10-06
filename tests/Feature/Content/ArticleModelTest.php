<?php

namespace Tests\Feature\Content;

use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Models\Media;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\LazyLoadingViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InteractsWithContent;
use Tests\TestCase;

/**
 * Model Article (09 §6.3, F4A-R5, F4A-R7, F4A-R10). Review Focus F.1: batas waktu tayang.
 */
class ArticleModelTest extends TestCase
{
    use InteractsWithContent;
    use RefreshDatabase;

    private function freeze(): Carbon
    {
        $this->travelTo(Carbon::parse('2026-10-05 10:00:00'));

        return now();
    }

    public function test_fillable_follows_the_blueprint(): void
    {
        $this->assertSame([
            'article_category_id', 'author_user_id', 'title', 'slug', 'excerpt', 'body', 'status',
            'published_at', 'meta_title', 'meta_description',
        ], (new Article)->getFillable());
    }

    public function test_unknown_attributes_are_rejected_in_strict_mode(): void
    {
        $this->expectException(MassAssignmentException::class);

        (new Article)->fill(['bukan_kolom' => 'x']);
    }

    public function test_casts_and_database_defaults(): void
    {
        $created = Article::query()->create([
            'title' => 'Artikel Minimal', 'excerpt' => 'Ringkasan.', 'body' => '<p>Isi artikel yang cukup panjang untuk uji.</p>',
        ]);
        $article = Article::query()->findOrFail($created->id);

        $this->assertSame(ArticleStatus::Draft, $article->status);
        $this->assertNull($article->published_at);
        $this->assertNull($article->article_category_id);
        $this->assertNull($article->author_user_id);
        $this->assertSame('artikel-minimal', $article->slug);

        $published = $this->createArticle();
        $this->assertSame(ArticleStatus::Published, $published->status);
        $this->assertInstanceOf(Carbon::class, $published->published_at);
    }

    public function test_factory_states(): void
    {
        $this->freeze();

        $published = Article::factory()->published()->create();
        $draft = Article::factory()->draft()->create();
        $scheduled = Article::factory()->scheduled()->create();
        $trashed = Article::factory()->trashed()->create();

        $this->assertSame(ArticleStatus::Published, $published->status);
        $this->assertTrue($published->published_at->isSameDay(now()->subDay()));
        $this->assertSame(ArticleStatus::Draft, $draft->status);
        $this->assertNull($draft->published_at);
        $this->assertSame(ArticleStatus::Published, $scheduled->status);
        $this->assertTrue($scheduled->published_at->greaterThan(now()));
        $this->assertTrue($trashed->trashed());
        $this->assertSame(1, Article::onlyTrashed()->count());
        $this->assertSame(3, Article::query()->count());
    }

    // --- Review Focus F.1: batas waktu tayang ---

    public function test_published_scope_honours_the_publication_boundaries(): void
    {
        $now = $this->freeze();

        $this->createArticle(['title' => 'Tepat sekarang', 'published_at' => $now]);
        $this->createArticle(['title' => 'Satu menit lalu', 'published_at' => $now->copy()->subMinute()]);
        $this->createArticle(['title' => 'Satu menit lagi', 'published_at' => $now->copy()->addMinute()]);
        $this->createArticle(['title' => 'Satu detik lagi', 'published_at' => $now->copy()->addSecond()]);
        Article::factory()->draft()->create(['title' => 'Draf ber-tanggal', 'published_at' => $now->copy()->subDay()]);
        Article::factory()->trashed()->create(['title' => 'Terhapus']);
        Article::factory()->create(['title' => 'Terbit tanpa tanggal', 'status' => 'published', 'published_at' => null]);

        $this->assertEqualsCanonicalizing(
            ['Tepat sekarang', 'Satu menit lalu'],
            Article::query()->published()->pluck('title')->all(),
        );
    }

    public function test_an_article_becomes_visible_when_the_clock_reaches_published_at(): void
    {
        $now = $this->freeze();
        $this->createArticle(['title' => 'Terjadwal', 'published_at' => $now->copy()->addMinutes(5)]);

        $this->assertSame(0, Article::query()->published()->count());

        $this->travelTo($now->copy()->addMinutes(5)->subSecond());
        $this->assertSame(0, Article::query()->published()->count());

        $this->travelTo($now->copy()->addMinutes(5));
        $this->assertSame(1, Article::query()->published()->count());
    }

    public function test_is_publicly_visible_and_is_scheduled_follow_the_same_rules(): void
    {
        $now = $this->freeze();

        $past = $this->createArticle(['published_at' => $now->copy()->subMinute()]);
        $exact = $this->createArticle(['published_at' => $now]);
        $future = $this->createArticle(['published_at' => $now->copy()->addMinute()]);
        $draft = Article::factory()->draft()->create(['published_at' => $now->copy()->subDay()]);
        $draftFuture = Article::factory()->draft()->create(['published_at' => $now->copy()->addDay()]);
        $trashed = Article::factory()->trashed()->create();
        $noDate = Article::factory()->create(['status' => 'published', 'published_at' => null]);

        $this->assertTrue($past->isPubliclyVisible());
        $this->assertTrue($exact->isPubliclyVisible());
        $this->assertFalse($future->isPubliclyVisible());
        $this->assertFalse($draft->isPubliclyVisible());
        $this->assertFalse($draftFuture->isPubliclyVisible());
        $this->assertFalse($trashed->isPubliclyVisible());
        $this->assertFalse($noDate->isPubliclyVisible());

        $this->assertFalse($past->isScheduled());
        $this->assertFalse($exact->isScheduled());
        $this->assertTrue($future->isScheduled());
        $this->assertFalse($draft->isScheduled());
        $this->assertFalse($draftFuture->isScheduled(), 'Draf bukan terjadwal, walau published_at di masa depan.');
        $this->assertFalse($noDate->isScheduled());
    }

    public function test_going_back_to_draft_keeps_published_at_but_hides_the_article(): void
    {
        $this->freeze();
        $article = $this->createArticle();
        $publishedAt = $article->published_at->toDateTimeString();

        $article->update(['status' => 'draft']);

        $this->assertSame($publishedAt, $article->fresh()->published_at->toDateTimeString());
        $this->assertFalse($article->fresh()->isPubliclyVisible());
        $this->assertSame(0, Article::query()->published()->count());
    }

    public function test_newest_first_orders_by_published_at_then_id_descending(): void
    {
        $now = $this->freeze();

        $old = $this->createArticle(['title' => 'Lama', 'published_at' => $now->copy()->subDays(5)]);
        $sameA = $this->createArticle(['title' => 'Sama A', 'published_at' => $now->copy()->subDays(2)]);
        $sameB = $this->createArticle(['title' => 'Sama B', 'published_at' => $now->copy()->subDays(2)]);
        $new = $this->createArticle(['title' => 'Baru', 'published_at' => $now->copy()->subDay()]);

        $this->assertSame(
            [$new->id, $sameB->id, $sameA->id, $old->id],
            Article::query()->newestFirst()->pluck('id')->all(),
        );
    }

    // --- search dan inCategory ---

    public function test_search_matches_title_and_excerpt_case_insensitively_and_trims(): void
    {
        $byTitle = $this->createArticle(['title' => 'Menjaga Jantung Sehat', 'excerpt' => 'Ringkasan satu.']);
        $byExcerpt = $this->createArticle(['title' => 'Tidur Nyenyak', 'excerpt' => 'Membantu jantung beristirahat.']);
        $other = $this->createArticle(['title' => 'Cuci Tangan', 'excerpt' => 'Kebersihan.']);

        $this->assertEqualsCanonicalizing([$byTitle->id, $byExcerpt->id], Article::query()->search('  JANTUNG  ')->pluck('id')->all());
        $this->assertSame([$other->id], Article::query()->search('kebersihan')->pluck('id')->all());
        $this->assertSame([], Article::query()->search('tidak ada')->pluck('id')->all());
    }

    #[DataProvider('blankSearchTerms')]
    public function test_a_blank_search_term_has_no_effect(?string $term): void
    {
        $this->createArticle();
        $this->createArticle();
        $this->createArticle();

        $this->assertSame(3, Article::query()->search($term)->count());
    }

    /** @return array<string, array{0: ?string}> */
    public static function blankSearchTerms(): array
    {
        return ['null' => [null], 'kosong' => [''], 'spasi' => ['   ']];
    }

    public function test_search_escapes_like_wildcards_and_backslashes(): void
    {
        $percent = $this->createArticle(['title' => 'Diskon 100% Sehat']);
        $plainPercent = $this->createArticle(['title' => 'Diskon 100 Sehat']);
        $underscore = $this->createArticle(['title' => 'Tidur_Nyenyak']);
        $plainUnderscore = $this->createArticle(['title' => 'Tidur Nyenyak']);
        $backslash = $this->createArticle(['title' => 'Jalur A\\B']);

        $this->assertSame([$percent->id], Article::query()->search('100%')->pluck('id')->all());
        $this->assertSame([$underscore->id], Article::query()->search('Tidur_N')->pluck('id')->all());
        $this->assertSame([$backslash->id], Article::query()->search('A\\B')->pluck('id')->all());
        $this->assertContains($plainPercent->id, Article::query()->search('100')->pluck('id')->all());
        $this->assertContains($plainUnderscore->id, Article::query()->search('Nyenyak')->pluck('id')->all());
        $this->assertSame(0, Article::query()->search('%')->whereKeyNot($percent->id)->count());
    }

    public function test_in_category_filters_by_the_category_slug(): void
    {
        $gizi = $this->createArticleCategory(['name' => 'Gizi']);
        $anak = $this->createArticleCategory(['name' => 'Anak']);
        $a = $this->createArticle([], $gizi);
        $this->createArticle([], $anak);
        $this->createArticle();

        $this->assertSame([$a->id], Article::query()->inCategory('gizi')->pluck('id')->all());
        $this->assertSame(0, Article::query()->inCategory('tidak-ada')->count());
    }

    // --- Accessor ---

    #[DataProvider('readingTimes')]
    public function test_reading_time_is_the_word_count_over_two_hundred_rounded_up(string $body, int $minutes): void
    {
        $this->assertSame($minutes, (new Article(['body' => $body]))->reading_time_minutes);
    }

    /** @return array<string, array{0: string, 1: int}> */
    public static function readingTimes(): array
    {
        return [
            'kosong' => ['', 1],
            'hanya tag' => ['<p><br></p>', 1],
            'satu kata' => ['<p>kata</p>', 1],
            '200 kata' => ['<p>'.str_repeat('kata ', 200).'</p>', 1],
            '201 kata' => ['<p>'.str_repeat('kata ', 201).'</p>', 2],
            '400 kata' => ['<p>'.str_repeat('kata ', 400).'</p>', 2],
            '401 kata' => ['<p>'.str_repeat('kata ', 401).'</p>', 3],
            'tag tidak menyatukan kata' => [str_repeat('<p>kata</p>', 201), 2],
            'entitas dan tag sisipan' => ['<p>satu&nbsp;dua <strong>tiga</strong></p>', 1],
        ];
    }

    public function test_seo_title_and_description_fall_back_to_title_and_excerpt(): void
    {
        $plain = $this->createArticle(['title' => 'Judul Artikel', 'excerpt' => 'Ringkasan artikel.']);
        $custom = $this->createArticle(['title' => 'Judul Lain', 'excerpt' => 'Ringkasan lain.', 'meta_title' => 'Meta Judul', 'meta_description' => 'Meta deskripsi.']);
        $blank = $this->createArticle(['title' => 'Judul Ketiga', 'excerpt' => 'Ringkasan ketiga.', 'meta_title' => '', 'meta_description' => '']);

        $this->assertSame('Judul Artikel', $plain->seo_title);
        $this->assertSame('Ringkasan artikel.', $plain->seo_description);
        $this->assertSame('Meta Judul', $custom->seo_title);
        $this->assertSame('Meta deskripsi.', $custom->seo_description);
        $this->assertSame('Judul Ketiga', $blank->seo_title);
        $this->assertSame('Ringkasan ketiga.', $blank->seo_description);
    }

    // --- Slug ---

    public function test_slug_is_derived_from_the_title_and_numbered_on_conflict(): void
    {
        $first = $this->createArticle(['title' => 'Pola Tidur yang Baik']);
        $second = $this->createArticle(['title' => 'Pola Tidur yang Baik']);

        $this->assertSame('pola-tidur-yang-baik', $first->slug);
        $this->assertSame('pola-tidur-yang-baik-2', $second->slug);
    }

    public function test_an_explicit_slug_is_kept_and_not_changed_by_a_title_edit(): void
    {
        $article = $this->createArticle([
            'title' => 'Mengapa Pemeriksaan Kesehatan Berkala Itu Penting',
            'slug' => 'pentingnya-pemeriksaan-kesehatan-berkala',
        ]);

        $this->assertSame('pentingnya-pemeriksaan-kesehatan-berkala', $article->slug);

        $article->update(['title' => 'Judul Berubah']);

        $this->assertSame('pentingnya-pemeriksaan-kesehatan-berkala', $article->fresh()->slug);
    }

    public function test_slug_never_exceeds_the_column_length(): void
    {
        $this->assertSame(220, strlen(Article::uniqueSlug(str_repeat('a', 400))));
        $this->assertStringEndsNotWith('-', Article::uniqueSlug(str_repeat('abcdefghi ', 40)));
    }

    // --- Media dan relasi ---

    public function test_cover_url_is_the_placeholder_without_media(): void
    {
        $article = Article::query()->with('media')->findOrFail($this->createArticle()->id);

        $this->assertSame(asset('images/placeholders/article.svg'), $article->cover_url);
    }

    public function test_cover_url_points_to_the_uploaded_cover(): void
    {
        Storage::fake('public');
        $created = Article::factory()->withCover()->create();
        $article = Article::query()->with('media')->findOrFail($created->id);

        $this->assertStringNotContainsString('images/placeholders', $article->cover_url);
        $this->assertStringContainsString('cover/', $article->cover_url);
        $this->assertSame('cover', $article->firstMedia('cover')->collection);
    }

    public function test_relations_resolve_without_lazy_loading_when_eager_loaded(): void
    {
        $category = $this->createArticleCategory(['name' => 'Gizi']);
        $author = User::factory()->create(['name' => 'Penulis Satu']);

        foreach (range(1, 4) as $i) {
            $this->createArticle(['author_user_id' => $author->id, 'title' => "Artikel nomor {$i}"], $category);
        }

        $articles = Article::query()->with(['category', 'author', 'media'])->newestFirst()->get();

        $this->assertCount(4, $articles);

        foreach ($articles as $article) {
            $this->assertSame('Gizi', $article->category->name);
            $this->assertSame('Penulis Satu', $article->author->name);
            $this->assertSame(asset('images/placeholders/article.svg'), $article->cover_url);
        }
    }

    public function test_an_article_without_category_or_author_has_null_relations(): void
    {
        $article = Article::query()->with(['category', 'author'])->findOrFail($this->createArticle()->id);

        $this->assertNull($article->category);
        $this->assertNull($article->author);
    }

    public function test_listing_without_eager_loading_is_caught_by_strict_mode(): void
    {
        $category = $this->createArticleCategory();
        $this->createArticle([], $category);
        $this->createArticle([], $category);

        $articles = Article::query()->get();

        $this->expectException(LazyLoadingViolationException::class);

        $articles->first()->category;
    }

    public function test_body_is_sanitized_on_save(): void
    {
        $article = $this->createArticle(['body' => '<script>alert(1)</script><p onclick="x()">Isi artikel yang aman.</p>']);

        $stored = (string) DB::table('articles')->where('id', $article->id)->value('body');

        $this->assertStringNotContainsString('<script', $stored);
        $this->assertStringNotContainsString('onclick', $stored);
        $this->assertStringContainsString('<p>Isi artikel yang aman.</p>', $stored);
    }
}
