<?php

namespace Tests\Feature\Media;

use App\Enums\ActorType;
use App\Models\AuditLog;
use App\Models\Media;
use App\Models\User;
use App\Services\Media\MediaService;
use finfo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\ImageFixtures;
use Tests\TestCase;

class MediaServiceTest extends TestCase
{
    use RefreshDatabase;

    private MediaService $service;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->service = app(MediaService::class);
        $this->owner = User::factory()->staff()->create();
    }

    /**
     * @return array{alt_text: string}
     */
    private function meta(): array
    {
        return ['alt_text' => 'Foto contoh'];
    }

    private function path(Media $media): string
    {
        return $media->directory.'/'.$media->file_name;
    }

    private function stored(Media $media): string
    {
        return Storage::disk('public')->path($this->path($media));
    }

    private function mime(string $path): string
    {
        return (string) (new finfo(FILEINFO_MIME_TYPE))->file($path);
    }

    public function test_store_saves_a_reencoded_jpeg_with_uuid_name_thumbnail_and_row(): void
    {
        $media = $this->service->store(
            ImageFixtures::jpeg(800, 800, 'Foto Dokter.JPG'),
            'photo',
            $this->owner,
            ['alt_text' => 'Foto contoh dokter', 'caption' => 'Dokter jaga pagi'],
            $this->owner,
        );

        $this->assertSame($this->owner->getMorphClass(), $media->model_type);
        $this->assertSame($this->owner->id, $media->model_id);
        $this->assertSame('photo', $media->collection);
        $this->assertSame('public', $media->disk);
        $this->assertSame('photo/'.now()->format('Y/m'), $media->directory);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}(-[0-9a-f]{4}){3}-[0-9a-f]{12}\.jpg$/', $media->file_name);
        $this->assertSame('Foto Dokter.JPG', $media->original_name);
        $this->assertSame('image/jpeg', $media->mime_type);
        $this->assertSame('jpg', $media->extension);
        $this->assertSame(800, $media->width);
        $this->assertSame(800, $media->height);
        $this->assertSame('Foto contoh dokter', $media->alt_text);
        $this->assertSame('Dokter jaga pagi', $media->caption);
        $this->assertSame(1, $media->sort_order);
        $this->assertSame($this->owner->id, $media->uploaded_by);

        $disk = Storage::disk('public');
        $disk->assertExists($this->path($media));
        $this->assertSame($media->directory.'/thumbs/'.$media->file_name, $media->thumb_path);
        $disk->assertExists($media->thumb_path);
        $this->assertSame($disk->size($this->path($media)), $media->size_bytes);
        $this->assertSame('image/jpeg', $this->mime($this->stored($media)));
    }

    public function test_thumbnail_is_400_px_wide_and_never_upscaled(): void
    {
        $disk = Storage::disk('public');

        $large = $this->service->store(ImageFixtures::jpeg(2400, 1600), 'cover', $this->owner, $this->meta());
        $thumb = getimagesizefromstring($disk->get($large->thumb_path));

        $this->assertSame(1600, $large->width);
        $this->assertSame(400, $thumb[0]);
        $this->assertEqualsWithDelta(267, $thumb[1], 1);

        $small = $this->service->store(ImageFixtures::jpeg(300, 300), 'photo', $this->owner, $this->meta());
        $smallThumb = getimagesizefromstring($disk->get($small->thumb_path));

        $this->assertSame(300, $small->width);
        $this->assertSame(300, $smallThumb[0]);
    }

    public function test_large_jpeg_is_resized_and_exif_gps_is_removed(): void
    {
        $upload = ImageFixtures::jpegWithGps(3000, 2000);

        $this->assertTrue(ImageFixtures::hasExifMarker($upload->getRealPath()));
        $this->assertTrue(ImageFixtures::hasGpsTag($upload->getRealPath()));

        $media = $this->service->store($upload, 'cover', $this->owner, $this->meta());

        $this->assertLessThanOrEqual(1600, $media->width);
        $this->assertSame(1600, $media->width);
        $this->assertEqualsWithDelta(1067, $media->height, 1);
        $this->assertSame(1600, getimagesize($this->stored($media))[0]);
        $this->assertFalse(ImageFixtures::hasExifMarker($this->stored($media)));
        $this->assertFalse(ImageFixtures::hasGpsTag($this->stored($media)));
        $this->assertFalse(ImageFixtures::hasExifMarker(Storage::disk('public')->path($media->thumb_path)));
    }

    public function test_original_name_is_sanitized_and_file_name_is_a_uuid(): void
    {
        $media = $this->service->store(
            ImageFixtures::jpeg(800, 800, '../../x<script>.jpg'),
            'photo',
            $this->owner,
            $this->meta(),
        );

        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}(-[0-9a-f]{4}){3}-[0-9a-f]{12}\.jpg$/', $media->file_name);
        $this->assertSame('xscript.jpg', $media->original_name);
        $this->assertStringNotContainsString('..', $media->original_name);
        $this->assertStringNotContainsString('/', $media->original_name);
        $this->assertStringNotContainsString('<', $media->original_name);

        $files = Storage::disk('public')->allFiles();
        $this->assertCount(2, $files);

        foreach ($files as $file) {
            $this->assertStringStartsWith('photo/'.now()->format('Y/m').'/', $file);
            $this->assertStringNotContainsString('script', $file);
            $this->assertStringNotContainsString('..', $file);
        }
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function originalNames(): array
    {
        return [
            'nama biasa dipertahankan' => ['Foto Dokter.JPG', 'Foto Dokter.JPG'],
            'path traversal dan tag dibuang' => ['../../x<script>.jpg', 'xscript.jpg'],
            'backslash windows' => ['C:\\Users\\admin\\c.png', 'c.png'],
            'hanya simbol memakai cadangan' => ['???', 'gambar'],
            'titik berulang dirapatkan' => ['..jpg', 'jpg'],
            'dipotong 150 karakter' => [str_repeat('a', 200).'.jpg', str_repeat('a', 150)],
            'karakter kontrol dibuang' => ["foto\x07nama.jpg", 'fotonama.jpg'],
            'spasi berulang dirapatkan' => ['Foto   Dokter  (1).jpg', 'Foto Dokter (1).jpg'],
            'huruf unicode dipertahankan' => ['Dokter Ünal.jpg', 'Dokter Ünal.jpg'],
        ];
    }

    #[DataProvider('originalNames')]
    public function test_original_name_sanitizing_rules(string $clientName, string $expected): void
    {
        $media = $this->service->store(ImageFixtures::jpeg(800, 800, $clientName), 'photo', $this->owner, $this->meta());

        $this->assertSame($expected, $media->original_name);
    }

    public function test_png_named_jpg_is_stored_as_png_after_its_real_content(): void
    {
        $media = $this->service->store(ImageFixtures::pngNamedJpg(900, 600, 'foto.jpg'), 'gallery', $this->owner, $this->meta());

        $this->assertSame('png', $media->extension);
        $this->assertSame('image/png', $media->mime_type);
        $this->assertStringEndsWith('.png', $media->file_name);
        $this->assertSame('foto.jpg', $media->original_name);
        $this->assertSame('image/png', $this->mime($this->stored($media)));
        $this->assertSame('image/png', $this->mime(Storage::disk('public')->path($media->thumb_path)));
    }

    public function test_uppercase_jpg_extension_is_stored_in_lowercase(): void
    {
        $media = $this->service->store(ImageFixtures::jpeg(800, 800, 'FOTO.JPG'), 'photo', $this->owner, $this->meta());

        $this->assertSame('jpg', $media->extension);
        $this->assertStringEndsWith('.jpg', $media->file_name);
        $this->assertSame('FOTO.JPG', $media->original_name);
    }

    public function test_transparent_png_stays_png_with_its_transparency(): void
    {
        $media = $this->service->store(ImageFixtures::transparentPng(600, 400), 'logo', null, $this->meta());
        $disk = Storage::disk('public');

        $this->assertSame('png', $media->extension);
        $this->assertSame('image/png', $media->mime_type);

        $main = $disk->get($this->path($media));
        $this->assertSame(127, ImageFixtures::alphaAt($main, 2, 2));
        $this->assertSame(0, ImageFixtures::alphaAt($main, 300, 200));

        $this->assertSame(127, ImageFixtures::alphaAt($disk->get($media->thumb_path), 1, 1));
    }

    public function test_webp_is_stored_as_webp(): void
    {
        $media = $this->service->store(ImageFixtures::webp(900, 600), 'gallery', $this->owner, $this->meta());

        $this->assertSame('webp', $media->extension);
        $this->assertSame('image/webp', $media->mime_type);
        $this->assertStringEndsWith('.webp', $media->file_name);
        $this->assertSame('image/webp', $this->mime($this->stored($media)));
        $this->assertSame(900, $media->width);
    }

    public function test_double_extension_names_never_reach_the_disk(): void
    {
        $media = $this->service->store(ImageFixtures::jpeg(800, 800, 'shell.php.jpg'), 'photo', $this->owner, $this->meta());

        $this->assertSame('shell.php.jpg', $media->original_name);
        $this->assertDoesNotMatchRegularExpression('/\.php/i', $media->file_name);

        foreach (Storage::disk('public')->allFiles() as $file) {
            $this->assertSame('jpg', pathinfo($file, PATHINFO_EXTENSION));
            $this->assertDoesNotMatchRegularExpression('/php|phtml/i', $file);
        }
    }

    public function test_no_stored_file_uses_the_original_name(): void
    {
        $this->service->store(ImageFixtures::jpeg(800, 800, 'rahasia-asli.jpg'), 'photo', $this->owner, $this->meta());

        foreach (Storage::disk('public')->allFiles() as $file) {
            $this->assertStringNotContainsString('rahasia', $file);
        }
    }

    public function test_sort_order_increments_per_owner_and_collection(): void
    {
        $first = $this->service->store(ImageFixtures::jpeg(900, 600), 'gallery', $this->owner, $this->meta());
        $second = $this->service->store(ImageFixtures::jpeg(900, 600), 'gallery', $this->owner, $this->meta());
        $third = $this->service->store(ImageFixtures::jpeg(900, 600), 'gallery', $this->owner, $this->meta());

        $this->assertSame([1, 2, 3], [$first->sort_order, $second->sort_order, $third->sort_order]);

        $otherOwner = User::factory()->staff()->create();
        $this->assertSame(1, $this->service->store(ImageFixtures::jpeg(900, 600), 'gallery', $otherOwner, $this->meta())->sort_order);
        $this->assertSame(1, $this->service->store(ImageFixtures::jpeg(900, 600), 'cover', $this->owner, $this->meta())->sort_order);
    }

    public function test_store_without_owner_leaves_the_morph_columns_null(): void
    {
        $first = $this->service->store(ImageFixtures::jpeg(400, 300), 'editor', null, $this->meta());
        $second = $this->service->store(ImageFixtures::jpeg(400, 300), 'editor', null, $this->meta());

        $this->assertNull($first->model_type);
        $this->assertNull($first->model_id);
        $this->assertNull($first->uploaded_by);
        $this->assertSame([1, 2], [$first->sort_order, $second->sort_order]);
    }

    public function test_store_writes_one_uploaded_audit_row(): void
    {
        $media = $this->service->store(ImageFixtures::jpeg(800, 800), 'photo', $this->owner, $this->meta(), $this->owner);

        $logs = AuditLog::query()->where('action', 'media.uploaded')->get();

        $this->assertCount(1, $logs);
        $log = $logs->first();
        $this->assertSame(Media::class, $log->auditable_type);
        $this->assertSame($media->id, $log->auditable_id);
        $this->assertSame(ActorType::Staff, $log->actor_type);
        $this->assertSame($this->owner->id, $log->user_id);
        $this->assertNull($log->old_values);
        $this->assertSame($media->id, $log->new_values['media_id']);
        $this->assertSame($media->file_name, $log->new_values['file_name']);
        $this->assertSame('photo', $log->new_values['collection']);
    }

    public function test_store_without_uploader_audits_as_system(): void
    {
        $this->service->store(ImageFixtures::jpeg(400, 300), 'editor', null, $this->meta());

        $log = AuditLog::query()->where('action', 'media.uploaded')->firstOrFail();

        $this->assertSame(ActorType::System, $log->actor_type);
        $this->assertNull($log->user_id);
    }

    public function test_unknown_collection_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Koleksi media tidak dikenal: avatar.');

        $this->service->store(ImageFixtures::jpeg(800, 800), 'avatar', $this->owner, $this->meta());
    }

    public function test_collection_catalogue_matches_the_blueprint(): void
    {
        $this->assertSame([
            'photo' => ['single' => true, 'disk' => 'public', 'max_kb' => 2048, 'min' => [300, 300]],
            'cover' => ['single' => true, 'disk' => 'public', 'max_kb' => 2048, 'min' => [800, 450]],
            'gallery' => ['single' => false, 'disk' => 'public', 'max_kb' => 2048, 'min' => [600, 400]],
            'image' => ['single' => true, 'disk' => 'public', 'max_kb' => 4096, 'min' => [1200, 400]],
            'photos' => ['single' => false, 'disk' => 'public', 'max_kb' => 2048, 'min' => [600, 400]],
            'logo' => ['single' => true, 'disk' => 'public', 'max_kb' => 2048, 'min' => [120, 40]],
            'editor' => ['single' => false, 'disk' => 'public', 'max_kb' => 2048, 'min' => [200, 100]],
        ], MediaService::COLLECTIONS);

        $this->assertSame(6000, MediaService::MAX_DIMENSION);
        $this->assertSame(1600, MediaService::RESIZE_WIDTH);
        $this->assertSame(400, MediaService::THUMB_WIDTH);
        $this->assertSame(82, MediaService::JPEG_QUALITY);
        $this->assertSame(82, MediaService::WEBP_QUALITY);
    }

    public function test_files_are_removed_when_the_database_write_fails(): void
    {
        Media::creating(function (): void {
            throw new RuntimeException('simulasi galat tulis');
        });

        $thrown = null;

        try {
            $this->service->store(ImageFixtures::jpeg(800, 800), 'photo', $this->owner, $this->meta());
        } catch (RuntimeException $e) {
            $thrown = $e;
        }

        $this->assertNotNull($thrown);
        $this->assertSame('simulasi galat tulis', $thrown->getMessage());
        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertSame(0, Media::query()->count());
    }
}
