<?php

namespace Tests\Feature\Content;

use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\Clinic;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InteractsWithContent;
use Tests\TestCase;

/**
 * F4A-R7 / Review Focus F.5: slug artikel yang sudah dihapus (soft delete) tidak dipakai ulang.
 */
class ContentSlugTest extends TestCase
{
    use InteractsWithContent;
    use RefreshDatabase;

    public function test_a_new_article_with_the_same_title_as_a_deleted_one_gets_a_numbered_slug(): void
    {
        $old = $this->createArticle(['title' => 'Pola Tidur yang Baik']);
        $old->delete();

        $this->assertSoftDeleted('articles', ['id' => $old->id]);

        $new = $this->createArticle(['title' => 'Pola Tidur yang Baik']);

        $this->assertSame('pola-tidur-yang-baik', $old->slug);
        $this->assertSame('pola-tidur-yang-baik-2', $new->slug);
    }

    public function test_numbering_continues_across_deleted_and_live_articles(): void
    {
        $first = $this->createArticle(['title' => 'Judul Sama']);
        $second = $this->createArticle(['title' => 'Judul Sama']);
        $first->delete();
        $third = $this->createArticle(['title' => 'Judul Sama']);
        $second->delete();
        $fourth = $this->createArticle(['title' => 'Judul Sama']);

        $this->assertSame(
            ['judul-sama', 'judul-sama-2', 'judul-sama-3', 'judul-sama-4'],
            [$first->slug, $second->slug, $third->slug, $fourth->slug],
        );
        $this->assertSame(4, Article::withTrashed()->count());
    }

    public function test_a_force_deleted_slug_can_be_reused(): void
    {
        $old = $this->createArticle(['title' => 'Dihapus Permanen']);
        $old->forceDelete();

        $new = $this->createArticle(['title' => 'Dihapus Permanen']);

        $this->assertSame('dihapus-permanen', $new->slug);
        $this->assertSame(1, Article::withTrashed()->count());
    }

    public function test_unique_slug_with_ignore_id_ignores_the_row_itself_even_when_deleted(): void
    {
        $article = $this->createArticle(['title' => 'Sendiri']);
        $article->delete();

        $this->assertSame('sendiri-2', Article::uniqueSlug('Sendiri'));
        $this->assertSame('sendiri', Article::uniqueSlug('Sendiri', $article->id));
    }

    public function test_the_slug_of_a_deleted_article_does_not_change_when_restored_or_when_the_title_changes(): void
    {
        $article = $this->createArticle(['title' => 'Awal']);
        $article->delete();
        $article->restore();
        $article->update(['title' => 'Akhir']);

        $this->assertSame('awal', $article->fresh()->slug);
    }

    public function test_an_explicit_slug_equal_to_a_deleted_one_is_left_to_the_database(): void
    {
        $this->createArticle(['title' => 'Satu', 'slug' => 'slug-tetap'])->delete();

        $this->expectException(QueryException::class);

        $this->createArticle(['title' => 'Dua', 'slug' => 'slug-tetap']);
    }

    public function test_models_without_soft_deletes_reuse_slugs_after_a_hard_delete(): void
    {
        $category = $this->createArticleCategory(['name' => 'Gizi']);
        $category->delete();

        $this->assertSame('gizi', ArticleCategory::uniqueSlug('Gizi'));
        $this->assertSame('gizi', $this->createArticleCategory(['name' => 'Gizi'])->slug);

        $clinic = Clinic::factory()->create(['name' => 'Poliklinik Anak']);
        $this->assertSame('poliklinik-anak-2', Clinic::uniqueSlug('Poliklinik Anak'));
        $this->assertSame('poliklinik-anak', Clinic::uniqueSlug('Poliklinik Anak', $clinic->id));
    }
}
