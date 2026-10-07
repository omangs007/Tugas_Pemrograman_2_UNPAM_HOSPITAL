<?php

namespace Tests\Feature\Media;

use App\Models\Article;
use App\Models\Media;
use App\Models\Page;
use App\Services\Media\MediaService;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\ImageFixtures;
use Tests\Support\InteractsWithContent;
use Tests\TestCase;

/**
 * D-55 / F4A-R9 (08 §5.2): gambar editor `pages.body` dan `articles.body` terikat ke pemilik saat disimpan,
 * dilepas bila tidak dirujuk lagi, dan `media:prune` tidak menghapus yang masih dipakai.
 */
class ContentEditorImagesTest extends TestCase
{
    use InteractsWithContent;
    use RefreshDatabase;

    private MediaService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->seed(SettingsSeeder::class);

        $this->service = app(MediaService::class);
    }

    private function editorImage(): Media
    {
        return $this->service->store(ImageFixtures::jpeg(400, 300), 'editor', null, ['alt_text' => 'Gambar editor']);
    }

    private function html(Media ...$images): string
    {
        $imgs = array_map(fn (Media $media): string => '<img src="/storage/'.$media->directory.'/'.$media->file_name.'" alt="x">', $images);

        return '<p>Isi halaman yang cukup panjang '.implode(' ', $imgs).'</p>';
    }

    private function age(Media $media, int $hours): void
    {
        $at = now()->subHours($hours);
        DB::table('media')->where('id', $media->id)->update(['created_at' => $at, 'updated_at' => $at]);
    }

    private function assertFilesExist(Media $media): void
    {
        Storage::disk('public')->assertExists($media->directory.'/'.$media->file_name);
    }

    private function assertFilesMissing(Media $media): void
    {
        Storage::disk('public')->assertMissing($media->directory.'/'.$media->file_name);
    }

    public function test_the_reference_columns_include_every_rich_text_table(): void
    {
        $this->assertSame(
            [
                'clinics' => ['description'],
                'pages' => ['body'],
                'articles' => ['body'],
                'facilities' => ['description'],
                'room_categories' => ['description'],
            ],
            MediaService::EDITOR_REFERENCE_COLUMNS,
        );
    }

    public function test_saving_a_page_binds_the_referenced_editor_image_to_it(): void
    {
        $image = $this->editorImage();
        $other = $this->editorImage();

        $page = $this->createPage('about', ['body' => $this->html($image)]);

        $image->refresh();
        $this->assertSame(Page::class, $image->model_type);
        $this->assertSame($page->id, $image->model_id);
        $this->assertNull($other->fresh()->model_id);
        $this->assertCount(1, Page::query()->with('media')->findOrFail($page->id)->mediaIn('editor'));
    }

    public function test_saving_an_article_binds_the_referenced_editor_image_to_it(): void
    {
        $image = $this->editorImage();

        $article = $this->createArticle(['body' => $this->html($image)]);

        $image->refresh();
        $this->assertSame(Article::class, $image->model_type);
        $this->assertSame($article->id, $image->model_id);
    }

    public function test_an_image_owned_by_a_page_is_not_taken_over_by_an_article(): void
    {
        $image = $this->editorImage();
        $page = $this->createPage('about', ['body' => $this->html($image)]);

        $this->createArticle(['body' => $this->html($image)]);

        $this->assertSame(Page::class, $image->fresh()->model_type);
        $this->assertSame($page->id, $image->fresh()->model_id);
    }

    public function test_removing_the_image_from_the_page_body_releases_it(): void
    {
        $image = $this->editorImage();
        $page = $this->createPage('vision-mission', ['body' => $this->html($image)]);
        $this->age($image, 48);

        $page->update(['body' => '<p>Tanpa gambar lagi.</p>']);

        $image->refresh();
        $this->assertNull($image->model_type);
        $this->assertNull($image->model_id);
        $this->assertTrue($image->updated_at->greaterThan(now()->subMinute()));
    }

    public function test_removing_the_image_from_the_article_body_releases_it(): void
    {
        $image = $this->editorImage();
        $article = $this->createArticle(['body' => $this->html($image)]);

        $article->update(['body' => '<p>Tanpa gambar lagi dan cukup panjang untuk uji.</p>']);

        $this->assertNull($image->fresh()->model_id);
    }

    public function test_updating_other_attributes_does_not_release_the_image(): void
    {
        $image = $this->editorImage();
        $page = $this->createPage('about', ['body' => $this->html($image)]);

        $page->update(['title' => 'Judul Baru']);

        $this->assertSame($page->id, $image->fresh()->model_id);
    }

    public function test_prune_keeps_bound_images_and_removes_released_and_never_saved_ones(): void
    {
        $bound = $this->editorImage();
        $released = $this->editorImage();
        $neverSaved = $this->editorImage();
        $page = $this->createPage('about', ['body' => $this->html($bound, $released)]);
        $page->update(['body' => $this->html($bound)]);

        $this->travel(25)->hours();
        $this->age($neverSaved, 25);
        $this->age($released, 25);

        $report = $this->service->prune();

        $this->assertSame(2, $report->orphanRows);
        $this->assertNotNull($bound->fresh());
        $this->assertFilesExist($bound);
        $this->assertNull($released->fresh());
        $this->assertFilesMissing($released);
        $this->assertNull($neverSaved->fresh());
        $this->assertFilesMissing($neverSaved);
    }

    public function test_prune_skips_ownerless_images_still_referenced_by_a_page_or_an_article_body(): void
    {
        $inPage = $this->editorImage();
        $inArticle = $this->editorImage();
        $unreferenced = $this->editorImage();

        // Baris lama sebelum pengikatan diterapkan: pemilik NULL walau dirujuk (jalur yang melewati event model).
        $this->createPage('about')->forceFill(['body' => $this->html($inPage)])->saveQuietly();
        $this->createArticle()->forceFill(['body' => $this->html($inArticle)])->saveQuietly();

        foreach ([$inPage, $inArticle, $unreferenced] as $media) {
            $this->age($media, 30);
        }

        DB::table('media')->whereIn('id', [$inPage->id, $inArticle->id])->update(['model_type' => null, 'model_id' => null]);

        $report = $this->service->prune();

        $this->assertSame(1, $report->orphanRows);
        $this->assertNotNull($inPage->fresh());
        $this->assertNotNull($inArticle->fresh());
        $this->assertNull($unreferenced->fresh());
        $this->assertFilesExist($inPage);
        $this->assertFilesExist($inArticle);
        $this->assertFilesMissing($unreferenced);
    }

    public function test_prune_also_counts_references_inside_soft_deleted_articles(): void
    {
        $image = $this->editorImage();
        $article = $this->createArticle()->forceFill(['body' => $this->html($image)]);
        $article->saveQuietly();
        $article->delete();
        $this->age($image, 30);

        $this->assertSame(0, $this->service->prune()->orphanRows);
        $this->assertNotNull($image->fresh());
    }

    public function test_soft_deleting_an_article_keeps_its_editor_images_and_cover_and_prune_leaves_them(): void
    {
        $editor = $this->editorImage();
        $article = Article::factory()->withCover()->create(['body' => $this->html($editor)]);
        $cover = Media::query()->where('model_type', Article::class)->where('collection', 'cover')->firstOrFail();

        $article->delete();
        $this->travel(48)->hours();

        $report = $this->service->prune();

        $this->assertSame(0, $report->orphanRows);
        $this->assertSame($article->id, $editor->fresh()->model_id);
        $this->assertNotNull($cover->fresh());
        $this->assertFilesExist($editor);
        $this->assertFilesExist($cover);
    }

    public function test_force_deleting_an_article_deletes_its_editor_images_after_commit(): void
    {
        $image = $this->editorImage();
        $article = $this->createArticle(['body' => $this->html($image)]);

        $this->assertFilesExist($image);

        $article->forceDelete();

        $this->assertNull($image->fresh());
        $this->assertFilesMissing($image);
    }
}
