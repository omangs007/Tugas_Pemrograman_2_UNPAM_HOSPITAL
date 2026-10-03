<?php

namespace Tests\Feature\View;

use App\Models\Media;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\InteractsWithFormState;
use Tests\TestCase;

class FormFileComponentTest extends TestCase
{
    use InteractsWithFormState;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    private function makeMedia(): Media
    {
        return Media::create([
            'model_type' => null,
            'model_id' => null,
            'collection' => 'photo',
            'disk' => 'public',
            'directory' => 'photo/2026/10',
            'file_name' => Str::uuid()->toString().'.jpg',
            'original_name' => 'dokter.jpg',
            'mime_type' => 'image/jpeg',
            'extension' => 'jpg',
            'size_bytes' => 120000,
            'width' => 800,
            'height' => 800,
            'thumb_path' => 'photo/2026/10/thumbs/dokter.jpg',
            'alt_text' => 'Foto dr. Andi tersenyum',
            'caption' => null,
            'sort_order' => 1,
            'uploaded_by' => null,
        ])->fresh();
    }

    public function test_file_input_exposes_preview_hooks_limits_and_required_alt_field(): void
    {
        $this->blade('<x-form.file name="photo" label="Foto dokter" required help="Minimal 300 × 300 piksel." />')
            ->assertSee('data-image-field', false)
            ->assertSee('type="file"', false)
            ->assertSee('id="photo"', false)
            ->assertSee('name="photo"', false)
            ->assertSee('accept="image/jpeg,image/png,image/webp"', false)
            ->assertSee('data-image-input', false)
            ->assertSee('data-max-kb="2048"', false)
            ->assertSee('aria-required="true"', false)
            ->assertSee('class="form-control"', false)
            ->assertSee('<img data-image-preview class="image-preview mt-2 d-none" alt="Pratinjau gambar yang dipilih">', false)
            ->assertSee('data-image-warning', false)
            ->assertSee('Format JPG, PNG, atau WebP (SVG ditolak). Ukuran maksimal 2 MB.')
            ->assertSee('Minimal 300 × 300 piksel.')
            ->assertSee('aria-describedby="photo-hint photo-help"', false)
            ->assertSee('name="photo_alt"', false)
            ->assertSee('id="photo_alt"', false)
            ->assertSee('Teks alternatif gambar');
    }

    public function test_file_input_formats_the_size_limit(): void
    {
        $this->blade('<x-form.file name="banner" label="Banner" :max-kb="4096" />')
            ->assertSee('data-max-kb="4096"', false)
            ->assertSee('Ukuran maksimal 4 MB.');

        $this->blade('<x-form.file name="banner" label="Banner" :max-kb="512" />')
            ->assertSee('Ukuran maksimal 512 KB.');

        $this->blade('<x-form.file name="banner" label="Banner" :max-kb="1536" />')
            ->assertSee('Ukuran maksimal 1,5 MB.');
    }

    public function test_file_alt_field_is_required_and_accepts_custom_name_and_value(): void
    {
        $this->blade('<x-form.file name="photo" label="Foto" alt-name="foto_alt" alt-value="Foto resepsionis" />')
            ->assertSee('name="foto_alt"', false)
            ->assertSee('id="foto_alt"', false)
            ->assertSee('value="Foto resepsionis"', false)
            ->assertSee('aria-required="true"', false)
            ->assertSee('maxlength="255"', false);
    }

    public function test_file_alt_field_reads_old_input(): void
    {
        $this->withOldInput(['photo_alt' => 'Alt lama']);

        $this->blade('<x-form.file name="photo" label="Foto" alt-value="Alt asli" />')
            ->assertSee('value="Alt lama"', false)
            ->assertDontSee('Alt asli');
    }

    public function test_file_shows_the_current_media_thumbnail_and_prefills_alt(): void
    {
        $media = $this->makeMedia();

        $this->blade('<x-form.file name="photo" label="Foto dokter" :current-media="$media" />', ['media' => $media])
            ->assertSee('data-current-media', false)
            ->assertSee('Gambar saat ini')
            ->assertSee('src="'.$media->thumb_url.'"', false)
            ->assertSee('data-fallback="'.asset('images/placeholders/doctor.svg').'"', false)
            ->assertSee('alt="Foto dr. Andi tersenyum"', false)
            ->assertSee('value="Foto dr. Andi tersenyum"', false);
    }

    public function test_file_without_current_media_has_no_current_block(): void
    {
        $this->blade('<x-form.file name="photo" label="Foto" />')
            ->assertDontSee('data-current-media', false)
            ->assertDontSee('Gambar saat ini');
    }

    public function test_file_multiple_uses_array_name_and_multiple_attribute(): void
    {
        $this->blade('<x-form.file name="photos" label="Foto galeri" multiple />')
            ->assertSee('name="photos[]"', false)
            ->assertSee('id="photos"', false)
            ->assertSee('multiple', false)
            ->assertSee('name="photos_alt"', false);
    }

    public function test_file_error_is_linked_and_escaped(): void
    {
        $this->withViewErrors(['photo' => ['<script>alert(1)</script>']]);

        $this->blade('<x-form.file name="photo" label="Foto" />')
            ->assertSee('class="form-control is-invalid"', false)
            ->assertSee('aria-invalid="true"', false)
            ->assertSee('aria-describedby="photo-hint photo-error"', false)
            ->assertSee('<div id="photo-error" class="invalid-feedback d-block">&lt;script&gt;alert(1)&lt;/script&gt;</div>', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_file_accept_can_be_overridden(): void
    {
        $this->blade('<x-form.file name="logo" label="Logo" accept="image/png" />')
            ->assertSee('accept="image/png"', false);
    }
}
