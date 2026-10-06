<?php

namespace Tests\Feature\Content;

use App\Enums\ArticleStatus;
use App\Models\Page;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InteractsWithContent;
use Tests\TestCase;

/**
 * Menjaga pembantu tes konten, karena dipakai hampir semua tes fase 4a.
 */
class InteractsWithContentTest extends TestCase
{
    use InteractsWithContent;
    use RefreshDatabase;

    public function test_create_page_uses_the_defaults_and_marks_the_page_as_system(): void
    {
        $page = $this->createPage('doctors-index');

        $this->assertTrue($page->exists);
        $this->assertSame('doctors-index', $page->key);
        $this->assertSame('Cari Dokter', $page->title);
        $this->assertSame(Page::DEFAULTS['doctors-index']['subtitle'], $page->subtitle);
        $this->assertSame(Page::DEFAULTS['doctors-index']['meta_description'], $page->meta_description);
        $this->assertTrue($page->is_system);
    }

    public function test_create_page_applies_overrides_and_reuses_an_existing_row(): void
    {
        $first = $this->createPage('about', ['title' => 'Satu']);
        $second = $this->createPage('about', ['title' => 'Dua', 'body' => '<p>Isi.</p>']);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Page::query()->count());
        $this->assertSame('Dua', Page::forKey('about')->title);
        $this->assertSame('<p>Isi.</p>', Page::forKey('about')->body);
    }

    public function test_create_article_is_published_yesterday_and_accepts_a_category(): void
    {
        $this->travelTo(Carbon::parse('2026-10-05 10:00:00'));
        $category = $this->createArticleCategory(['name' => 'Gizi']);

        $article = $this->createArticle([], $category);
        $plain = $this->createArticle();
        $draft = $this->createArticle(['status' => 'draft']);

        $this->assertSame(ArticleStatus::Published, $article->status);
        $this->assertSame('2026-10-04 10:00:00', $article->published_at->toDateTimeString());
        $this->assertSame($category->id, $article->article_category_id);
        $this->assertNull($plain->article_category_id);
        $this->assertSame(ArticleStatus::Draft, $draft->status);
    }

    public function test_the_other_helpers_create_default_rows(): void
    {
        $this->assertTrue($this->createBanner()->is_active);
        $this->assertTrue($this->createGallery()->is_published);
        $this->assertTrue($this->createFaq()->is_published);
        $this->assertTrue($this->createInsurancePartner()->is_active);
        $this->assertGreaterThan(0, $this->createArticleCategory()->id);
    }
}
