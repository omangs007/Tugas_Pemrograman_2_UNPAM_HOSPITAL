<?php

namespace Tests\Feature\Content;

use App\Models\Gallery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InteractsWithContent;
use Tests\TestCase;

class GalleryModelTest extends TestCase
{
    use InteractsWithContent;
    use RefreshDatabase;

    public function test_constants_and_fillable_follow_the_blueprint(): void
    {
        $this->assertSame(100, Gallery::MAX_PHOTOS);
        $this->assertSame(['title', 'slug', 'description', 'is_published', 'sort_order'], (new Gallery)->getFillable());
    }

    public function test_defaults_and_factory_states(): void
    {
        $created = Gallery::query()->create(['title' => 'Album Minimal']);
        $fresh = Gallery::query()->findOrFail($created->id);

        $this->assertFalse($fresh->is_published);
        $this->assertSame(0, $fresh->sort_order);

        $this->assertTrue($this->createGallery()->is_published);
        $this->assertFalse(Gallery::factory()->unpublished()->create()->is_published);
    }

    public function test_published_and_ordered_scopes(): void
    {
        $c = $this->createGallery(['title' => 'C', 'sort_order' => 3]);
        $a1 = $this->createGallery(['title' => 'A1', 'sort_order' => 1]);
        $a2 = $this->createGallery(['title' => 'A2', 'sort_order' => 1]);
        $draft = $this->createGallery(['title' => 'Draf', 'is_published' => false, 'sort_order' => 0]);

        $this->assertSame([$a1->id, $a2->id, $c->id], Gallery::query()->published()->ordered()->pluck('id')->all());
        $this->assertSame([$draft->id, $a1->id, $a2->id, $c->id], Gallery::query()->ordered()->pluck('id')->all());
    }

    public function test_cover_media_is_the_first_photo_by_sort_order_then_id(): void
    {
        $gallery = $this->createGallery();
        $third = $this->addGalleryPhoto($gallery, ['sort_order' => 2]);
        $first = $this->addGalleryPhoto($gallery, ['sort_order' => 1]);
        $tied = $this->addGalleryPhoto($gallery, ['sort_order' => 1]);
        $this->addGalleryPhoto($gallery, ['collection' => 'editor', 'sort_order' => 0]);

        $loaded = Gallery::query()->with('media')->findOrFail($gallery->id);

        $this->assertSame($first->id, $loaded->coverMedia()?->id);
        $this->assertNotSame($tied->id, $loaded->coverMedia()?->id);
        $this->assertCount(3, $loaded->mediaIn('photos'));
        $this->assertSame([$first->id, $tied->id, $third->id], $loaded->mediaIn('photos')->pluck('id')->all());
    }

    public function test_cover_media_is_null_without_photos(): void
    {
        $gallery = Gallery::query()->with('media')->findOrFail($this->createGallery()->id);

        $this->assertNull($gallery->coverMedia());
    }

    public function test_add_gallery_photo_helper_numbers_photos_sequentially(): void
    {
        $gallery = $this->createGallery();
        $other = $this->createGallery();

        $one = $this->addGalleryPhoto($gallery);
        $two = $this->addGalleryPhoto($gallery);
        $otherOne = $this->addGalleryPhoto($other);

        $this->assertSame([1, 2, 1], [$one->sort_order, $two->sort_order, $otherOne->sort_order]);
        $this->assertSame(Gallery::class, $one->model_type);
        $this->assertSame('photos', $one->collection);
    }

    public function test_slug_is_derived_from_the_title_and_stable(): void
    {
        $first = $this->createGallery(['title' => 'Lobi dan Area Pendaftaran (Foto Contoh)']);
        $second = $this->createGallery(['title' => 'Lobi dan Area Pendaftaran (Foto Contoh)']);
        $explicit = $this->createGallery(['title' => 'Fasilitas Pelayanan (Foto Contoh)', 'slug' => 'fasilitas-pelayanan']);

        $this->assertSame('lobi-dan-area-pendaftaran-foto-contoh', $first->slug);
        $this->assertSame('lobi-dan-area-pendaftaran-foto-contoh-2', $second->slug);
        $this->assertSame('fasilitas-pelayanan', $explicit->slug);

        $first->update(['title' => 'Judul Baru']);
        $this->assertSame('lobi-dan-area-pendaftaran-foto-contoh', $first->fresh()->slug);
    }

    public function test_slug_never_exceeds_the_column_length(): void
    {
        $this->assertSame(170, strlen(Gallery::uniqueSlug(str_repeat('a', 400))));
    }
}
