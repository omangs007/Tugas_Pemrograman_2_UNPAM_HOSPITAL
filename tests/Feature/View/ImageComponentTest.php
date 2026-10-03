<?php

namespace Tests\Feature\View;

use App\Models\Media;
use App\View\Components\Image;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ImageComponentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    /** @param array<string, mixed> $overrides */
    private function makeMedia(array $overrides = []): Media
    {
        return Media::create(array_merge([
            'model_type' => null,
            'model_id' => null,
            'collection' => 'photo',
            'disk' => 'public',
            'directory' => 'photo/2026/10',
            'file_name' => Str::uuid()->toString().'.jpg',
            'original_name' => 'foto-dokter.jpg',
            'mime_type' => 'image/jpeg',
            'extension' => 'jpg',
            'size_bytes' => 150000,
            'width' => 1200,
            'height' => 800,
            'thumb_path' => 'photo/2026/10/thumbs/foto.jpg',
            'alt_text' => 'Foto dr. Andi',
            'caption' => null,
            'sort_order' => 1,
            'uploaded_by' => null,
        ], $overrides))->fresh();
    }

    /** @return array<string, array{string}> */
    public static function imageTypes(): array
    {
        return array_combine(Image::TYPES, array_map(fn (string $type): array => [$type], Image::TYPES));
    }

    #[DataProvider('imageTypes')]
    public function test_missing_media_renders_the_local_placeholder_of_the_type(string $type): void
    {
        $placeholder = asset('images/placeholders/'.$type.'.svg');

        $this->blade('<x-image :type="$type" alt="Gambar contoh" />', ['type' => $type])
            ->assertSee('src="'.$placeholder.'"', false)
            ->assertSee('alt="Gambar contoh"', false)
            ->assertSee('loading="lazy"', false)
            ->assertSee('decoding="async"', false)
            ->assertDontSee('data-fallback', false)
            ->assertDontSee('onerror', false);
    }

    public function test_media_is_rendered_with_data_fallback_dimensions_and_default_alt(): void
    {
        $media = $this->makeMedia();

        $this->blade('<x-image :media="$media" type="doctor" />', ['media' => $media])
            ->assertSee('src="'.$media->url.'"', false)
            ->assertSee('data-fallback="'.asset('images/placeholders/doctor.svg').'"', false)
            ->assertSee('alt="Foto dr. Andi"', false)
            ->assertSee('width="1200"', false)
            ->assertSee('height="800"', false)
            ->assertSee('loading="lazy"', false)
            ->assertSee('decoding="async"', false)
            ->assertDontSee('onerror', false);
    }

    public function test_explicit_alt_overrides_the_media_alt_text(): void
    {
        $media = $this->makeMedia();

        $this->blade('<x-image :media="$media" type="doctor" alt="dr. Andi Wijaya, Sp.PD" />', ['media' => $media])
            ->assertSee('alt="dr. Andi Wijaya, Sp.PD"', false)
            ->assertDontSee('alt="Foto dr. Andi"', false);
    }

    public function test_thumb_variant_uses_the_thumbnail_url_and_scaled_dimensions(): void
    {
        $media = $this->makeMedia();

        $this->blade('<x-image :media="$media" type="doctor" variant="thumb" />', ['media' => $media])
            ->assertSee('src="'.$media->thumb_url.'"', false)
            ->assertSee('width="400"', false)
            ->assertSee('height="267"', false);
    }

    public function test_thumb_variant_without_thumbnail_falls_back_to_the_full_image(): void
    {
        $media = $this->makeMedia(['thumb_path' => null]);

        $this->blade('<x-image :media="$media" type="doctor" variant="thumb" />', ['media' => $media])
            ->assertSee('src="'.$media->url.'"', false)
            ->assertSee('width="1200"', false)
            ->assertSee('height="800"', false);
    }

    public function test_private_disk_media_has_no_public_url_so_the_placeholder_is_used(): void
    {
        $media = $this->makeMedia(['disk' => 'local']);

        $this->blade('<x-image :media="$media" type="clinic" alt="Poliklinik" />', ['media' => $media])
            ->assertSee('src="'.asset('images/placeholders/clinic.svg').'"', false)
            ->assertDontSee('data-fallback', false);
    }

    public function test_src_without_media_gets_the_placeholder_as_fallback(): void
    {
        $this->blade('<x-image type="banner" src="/storage/banner/lama.jpg" alt="Banner promo" />')
            ->assertSee('src="/storage/banner/lama.jpg"', false)
            ->assertSee('data-fallback="'.asset('images/placeholders/banner.svg').'"', false);
    }

    public function test_media_takes_precedence_over_src(): void
    {
        $media = $this->makeMedia();

        $this->blade('<x-image :media="$media" src="/lain.jpg" type="doctor" />', ['media' => $media])
            ->assertSee('src="'.$media->url.'"', false)
            ->assertDontSee('/lain.jpg', false);
    }

    public function test_decorative_image_has_empty_alt_and_presentation_role(): void
    {
        $this->blade('<x-image type="gallery" decorative />')
            ->assertSee('alt=""', false)
            ->assertSee('role="presentation"', false);
    }

    public function test_ratio_wraps_the_image_in_a_bootstrap_ratio_frame(): void
    {
        $this->blade('<x-image type="article" alt="Sampul artikel" ratio="16x9" />')
            ->assertSee('class="image-frame ratio ratio-16x9"', false)
            ->assertSee('object-fit-cover', false)
            ->assertDontSee('img-fluid', false);
    }

    public function test_without_ratio_the_image_is_fluid_and_unwrapped(): void
    {
        $this->blade('<x-image type="article" alt="Sampul artikel" />')
            ->assertSee('class="img-fluid"', false)
            ->assertDontSee('image-frame', false);
    }

    public function test_lazy_can_be_disabled(): void
    {
        $this->blade('<x-image type="logo" alt="Logo" :lazy="false" />')
            ->assertDontSee('loading="lazy"', false)
            ->assertSee('decoding="async"', false);
    }

    public function test_extra_attributes_are_merged_into_the_image(): void
    {
        $this->blade('<x-image type="doctor" alt="Foto" class="rounded-circle" id="foto-1" data-x="1" />')
            ->assertSee('class="img-fluid rounded-circle"', false)
            ->assertSee('id="foto-1"', false)
            ->assertSee('data-x="1"', false);
    }

    public function test_alt_and_src_are_escaped(): void
    {
        $this->blade('<x-image type="doctor" :alt="$payload" :src="$payload" />', ['payload' => '"><script>alert(1)</script>'])
            ->assertSee('alt="&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;"', false)
            ->assertSee('src="&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;"', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_unknown_type_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->component(Image::class, ['type' => 'avatar', 'alt' => 'x']);
    }

    public function test_unknown_variant_and_ratio_are_rejected(): void
    {
        try {
            $this->component(Image::class, ['type' => 'doctor', 'alt' => 'x', 'variant' => 'huge']);
            $this->fail('variant tidak dikenal harus ditolak');
        } catch (InvalidArgumentException) {
            $this->addToAssertionCount(1);
        }

        $this->expectException(InvalidArgumentException::class);

        $this->component(Image::class, ['type' => 'doctor', 'alt' => 'x', 'ratio' => '5x7']);
    }

    public function test_non_decorative_image_requires_alt_text(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('alt');

        $this->component(Image::class, ['type' => 'doctor']);
    }

    public function test_component_sources_contain_no_inline_event_handlers(): void
    {
        foreach (['resources/views/components/image.blade.php', 'app/View/Components/Image.php'] as $file) {
            $this->assertStringNotContainsString('onerror', strtolower(file_get_contents(base_path($file))), $file);
        }
    }
}
