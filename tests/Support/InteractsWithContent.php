<?php

namespace Tests\Support;

use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\Banner;
use App\Models\Faq;
use App\Models\Gallery;
use App\Models\InsurancePartner;
use App\Models\Media;
use App\Models\Page;
use Illuminate\Support\Str;

/**
 * Pembantu tes untuk konten website fase 4a (halaman, artikel, banner, galeri, FAQ, mitra asuransi).
 * Dipakai bersama RefreshDatabase. Semua pembantu memakai factory/model sehingga sanitasi, slug, dan
 * observer audit ikut berjalan.
 */
trait InteractsWithContent
{
    /**
     * Membuat atau memperbarui baris halaman sistem `$key` dengan judul/subjudul/meta dari Page::DEFAULTS
     * (13 §8.8), lalu menimpa dengan $attributes. Bila barisnya sudah ada (mis. setelah PageSeeder), baris itu
     * diperbarui, bukan digandakan. `key` dan `is_system` diisi lewat forceFill (bukan $fillable).
     *
     * Perhatian: pada baris yang sudah ada, nilai Page::DEFAULTS digabung dengan $attributes lalu ditimpakan,
     * sehingga suntingan sebelumnya pada `title`, `subtitle`, dan `meta_description` kembali ke default
     * kecuali diberikan lewat $attributes.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function createPage(string $key, array $attributes = []): Page
    {
        $defaults = Page::DEFAULTS[$key];
        $page = Page::query()->where('key', $key)->first() ?? new Page;

        $page->forceFill(array_merge([
            'key' => $key,
            'title' => $defaults['title'],
            'subtitle' => $defaults['subtitle'],
            'meta_description' => $defaults['meta_description'],
            'is_system' => true,
        ], $attributes))->save();

        return $page;
    }

    /** @param  array<string, mixed>  $attributes */
    protected function createArticleCategory(array $attributes = []): ArticleCategory
    {
        return ArticleCategory::factory()->create($attributes);
    }

    /**
     * Artikel terbit kemarin (tayang). Kategori: argumen $category, bila tidak ada `article_category_id` pada
     * $attributes atau NULL. Statusnya dapat diubah lewat $attributes atau state factory.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function createArticle(array $attributes = [], ?ArticleCategory $category = null): Article
    {
        return Article::factory()->published()->create(array_merge([
            'article_category_id' => $category?->id,
        ], $attributes));
    }

    /** @param  array<string, mixed>  $attributes */
    protected function createBanner(array $attributes = []): Banner
    {
        return Banner::factory()->create($attributes);
    }

    /** @param  array<string, mixed>  $attributes */
    protected function createGallery(array $attributes = []): Gallery
    {
        return Gallery::factory()->create($attributes);
    }

    /**
     * Menambah satu baris `media` koleksi `photos` pada album tanpa berkas fisik (urutan = terakhir + 1,
     * kecuali ditimpa). Untuk tes yang butuh berkas nyata, unggah lewat MediaService.
     *
     * @param  array<string, mixed>  $overrides
     */
    protected function addGalleryPhoto(Gallery $gallery, array $overrides = []): Media
    {
        $next = (int) Media::query()
            ->where('model_type', Gallery::class)
            ->where('model_id', $gallery->id)
            ->where('collection', 'photos')
            ->max('sort_order') + 1;
        $uuid = (string) Str::uuid();

        return Media::query()->create(array_merge([
            'model_type' => Gallery::class,
            'model_id' => $gallery->id,
            'collection' => 'photos',
            'disk' => 'public',
            'directory' => 'photos/2026/10',
            'file_name' => $uuid.'.jpg',
            'original_name' => 'foto-contoh.jpg',
            'mime_type' => 'image/jpeg',
            'extension' => 'jpg',
            'size_bytes' => 250880,
            'width' => 1200,
            'height' => 800,
            'thumb_path' => 'photos/2026/10/thumbs/'.$uuid.'.jpg',
            'alt_text' => 'Foto contoh album',
            'caption' => null,
            'sort_order' => $next,
            'uploaded_by' => null,
        ], $overrides));
    }

    /** @param  array<string, mixed>  $attributes */
    protected function createFaq(array $attributes = []): Faq
    {
        return Faq::factory()->create($attributes);
    }

    /** @param  array<string, mixed>  $attributes */
    protected function createInsurancePartner(array $attributes = []): InsurancePartner
    {
        return InsurancePartner::factory()->create($attributes);
    }
}
