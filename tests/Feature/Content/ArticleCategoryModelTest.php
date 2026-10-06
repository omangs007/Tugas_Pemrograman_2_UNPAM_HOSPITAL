<?php

namespace Tests\Feature\Content;

use App\Models\ArticleCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InteractsWithContent;
use Tests\TestCase;

class ArticleCategoryModelTest extends TestCase
{
    use InteractsWithContent;
    use RefreshDatabase;

    public function test_fillable_follows_the_blueprint(): void
    {
        $this->assertSame(['name', 'slug', 'description', 'sort_order'], (new ArticleCategory)->getFillable());
    }

    public function test_slug_is_derived_from_the_name_and_numbered_on_conflict(): void
    {
        $first = $this->createArticleCategory(['name' => 'Gaya Hidup Sehat']);
        $second = $this->createArticleCategory(['name' => 'Gaya Hidup Sehat']);

        $this->assertSame('gaya-hidup-sehat', $first->slug);
        $this->assertSame('gaya-hidup-sehat-2', $second->slug);
    }

    public function test_an_explicit_slug_is_kept(): void
    {
        $category = $this->createArticleCategory(['name' => 'Keluarga dan Anak', 'slug' => 'keluarga-dan-anak']);

        $this->assertSame('keluarga-dan-anak', $category->slug);
    }

    public function test_slug_is_truncated_to_the_column_length_without_a_trailing_hyphen(): void
    {
        $slug = ArticleCategory::uniqueSlug(str_repeat('abcdefghi ', 30));

        $this->assertLessThanOrEqual(120, strlen($slug));
        $this->assertStringEndsNotWith('-', $slug);
        $this->assertSame(120, strlen(ArticleCategory::uniqueSlug(str_repeat('a', 300))));
    }

    public function test_the_slug_is_not_changed_when_the_name_is_edited(): void
    {
        $category = $this->createArticleCategory(['name' => 'Gizi']);

        $category->update(['name' => 'Gizi dan Nutrisi']);

        $this->assertSame('gizi', $category->fresh()->slug);
    }

    public function test_ordered_scope_sorts_by_sort_order_then_name_then_id(): void
    {
        $b = $this->createArticleCategory(['name' => 'B Kategori', 'sort_order' => 1]);
        $a = $this->createArticleCategory(['name' => 'A Kategori', 'sort_order' => 1]);
        $c = $this->createArticleCategory(['name' => 'C Kategori', 'sort_order' => 0]);
        $dup1 = $this->createArticleCategory(['name' => 'D Kategori', 'sort_order' => 2]);
        $dup2 = $this->createArticleCategory(['name' => 'D Kategori', 'sort_order' => 2]);

        $this->assertSame(
            [$c->id, $a->id, $b->id, $dup1->id, $dup2->id],
            ArticleCategory::query()->ordered()->pluck('id')->all(),
        );
    }

    public function test_articles_relation_counts_only_non_deleted_articles(): void
    {
        $category = $this->createArticleCategory();
        $this->createArticle([], $category);
        $this->createArticle([], $category);
        $this->createArticle([], $this->createArticleCategory());
        $trashed = $this->createArticle([], $category);
        $trashed->delete();

        $this->assertCount(2, $category->articles);
        $this->assertSame(2, ArticleCategory::query()->withCount('articles')->findOrFail($category->id)->articles_count);
    }

    public function test_casts_and_defaults(): void
    {
        $category = ArticleCategory::query()->create(['name' => 'Info Layanan']);
        $fresh = ArticleCategory::query()->findOrFail($category->id);

        $this->assertSame(0, $fresh->sort_order);
        $this->assertNull($fresh->description);
        $this->assertSame('info-layanan', $fresh->slug);
    }
}
