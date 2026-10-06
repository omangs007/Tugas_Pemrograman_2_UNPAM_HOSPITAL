<?php

namespace Tests\Feature\Media;

use App\Models\Article;
use App\Models\Media;
use App\Services\Media\MediaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\ImageFixtures;
use Tests\Support\InteractsWithContent;
use Tests\TestCase;

/**
 * F4A-R32: soft delete tidak menghapus media pemilik; hapus permanen atau model tanpa soft delete menghapusnya.
 */
class SoftDeleteMediaTest extends TestCase
{
    use InteractsWithContent;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    private function coverOf(Article $article): Media
    {
        return Media::query()->where('model_type', Article::class)->where('model_id', $article->id)->where('collection', 'cover')->firstOrFail();
    }

    public function test_soft_deleting_an_article_keeps_the_media_row_and_files(): void
    {
        $article = Article::factory()->withCover()->create();
        $media = $this->coverOf($article);
        $path = $media->directory.'/'.$media->file_name;

        $article->delete();

        $this->assertSoftDeleted('articles', ['id' => $article->id]);
        $this->assertDatabaseHas('media', ['id' => $media->id, 'model_id' => $article->id]);
        Storage::disk('public')->assertExists($path);
        Storage::disk('public')->assertExists($media->thumb_path);
    }

    public function test_force_deleting_an_article_removes_the_media_row_and_files(): void
    {
        $article = Article::factory()->withCover()->create();
        $media = $this->coverOf($article);
        $path = $media->directory.'/'.$media->file_name;

        $article->delete();
        $article->forceDelete();

        $this->assertDatabaseMissing('articles', ['id' => $article->id]);
        $this->assertDatabaseMissing('media', ['id' => $media->id]);
        Storage::disk('public')->assertMissing($path);
        Storage::disk('public')->assertMissing($media->thumb_path);
    }

    public function test_restoring_an_article_keeps_its_cover(): void
    {
        $article = Article::factory()->withCover()->create();
        $article->delete();
        $article->restore();

        $this->assertSame(1, Article::query()->with('media')->findOrFail($article->id)->mediaIn('cover')->count());
    }

    public function test_models_without_soft_deletes_still_lose_their_media_on_delete(): void
    {
        $banner = $this->createBanner();
        $media = app(MediaService::class)->store(ImageFixtures::jpeg(1600, 600), 'image', $banner, ['alt_text' => 'Banner']);
        $path = $media->directory.'/'.$media->file_name;

        $banner->delete();

        $this->assertDatabaseMissing('media', ['id' => $media->id]);
        Storage::disk('public')->assertMissing($path);
    }
}
