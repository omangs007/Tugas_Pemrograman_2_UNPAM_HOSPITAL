<?php

namespace Tests\Feature\View;

use App\Models\Media;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * F4B-R27: prop `placeholder` pada <x-form.file> menimpa peta koleksi → tipe placeholder pratinjau.
 */
class FormFilePlaceholderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    private function makeMedia(string $collection): Media
    {
        return Media::create([
            'model_type' => null,
            'model_id' => null,
            'collection' => $collection,
            'disk' => 'public',
            'directory' => $collection.'/2026/10',
            'file_name' => Str::uuid()->toString().'.jpg',
            'original_name' => 'foto.jpg',
            'mime_type' => 'image/jpeg',
            'extension' => 'jpg',
            'size_bytes' => 120000,
            'width' => 1200,
            'height' => 800,
            'thumb_path' => $collection.'/2026/10/thumbs/foto.jpg',
            'alt_text' => 'Foto contoh ruang tunggu',
            'caption' => null,
            'sort_order' => 1,
            'uploaded_by' => null,
        ])->fresh();
    }

    public function test_placeholder_facility_overrides_the_cover_mapping(): void
    {
        $media = $this->makeMedia('cover');

        $this->blade('<x-form.file name="cover" label="Foto sampul" placeholder="facility" :current-media="$media" />', ['media' => $media])
            ->assertSee('data-current-media', false)
            ->assertSee('data-fallback="'.asset('images/placeholders/facility.svg').'"', false)
            ->assertDontSee('placeholders/clinic.svg', false);
    }

    public function test_placeholder_room_overrides_the_gallery_mapping(): void
    {
        $media = $this->makeMedia('gallery');

        $this->blade('<x-form.file name="cover" label="Foto sampul" placeholder="room" :current-media="$media" />', ['media' => $media])
            ->assertSee('data-fallback="'.asset('images/placeholders/room.svg').'"', false)
            ->assertDontSee('placeholders/gallery.svg', false);
    }

    public function test_without_the_prop_the_collection_map_is_unchanged(): void
    {
        $cover = $this->makeMedia('cover');
        $gallery = $this->makeMedia('gallery');
        $photo = $this->makeMedia('photo');
        $unknown = $this->makeMedia('koleksi-lain');

        $this->blade('<x-form.file name="cover" label="Foto" :current-media="$media" />', ['media' => $cover])
            ->assertSee('data-fallback="'.asset('images/placeholders/clinic.svg').'"', false);
        $this->blade('<x-form.file name="gallery" label="Foto" :current-media="$media" />', ['media' => $gallery])
            ->assertSee('data-fallback="'.asset('images/placeholders/gallery.svg').'"', false);
        $this->blade('<x-form.file name="photo" label="Foto" :current-media="$media" />', ['media' => $photo])
            ->assertSee('data-fallback="'.asset('images/placeholders/doctor.svg').'"', false);
        $this->blade('<x-form.file name="lain" label="Foto" :current-media="$media" />', ['media' => $unknown])
            ->assertSee('data-fallback="'.asset('images/placeholders/gallery.svg').'"', false);
    }

    public function test_the_prop_is_not_rendered_as_an_html_attribute(): void
    {
        $media = $this->makeMedia('cover');

        $this->blade('<x-form.file name="cover" label="Foto sampul" placeholder="facility" :current-media="$media" />', ['media' => $media])
            ->assertDontSee('placeholder="facility"', false)
            ->assertSee('type="file"', false)
            ->assertSee('name="cover_alt"', false);
    }

    public function test_the_prop_has_no_effect_and_no_error_without_a_current_media(): void
    {
        $this->blade('<x-form.file name="cover" label="Foto sampul" placeholder="room" />')
            ->assertDontSee('data-current-media', false)
            ->assertDontSee('placeholders/room.svg', false)
            ->assertSee('name="cover"', false);
    }
}
