<?php

namespace Tests\Feature\Media;

use App\Models\AuditLog;
use App\Models\Media;
use App\Models\User;
use App\Rules\ImageRules;
use App\Services\Media\MediaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ImageFixtures;
use Tests\TestCase;

class MediaServiceDefenseTest extends TestCase
{
    use RefreshDatabase;

    private MediaService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->service = app(MediaService::class);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function assertRejected(UploadedFile $file, string $collection, string $key, string $message, array $meta = ['alt_text' => 'Teks alternatif']): void
    {
        try {
            $this->service->store($file, $collection, null, $meta);
            $this->fail('ValidationException tidak dilempar.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($key, $e->errors());
            $this->assertContains($message, $e->errors()[$key]);
        }

        $this->assertSame(0, Media::query()->count());
        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertSame(0, AuditLog::query()->count());
    }

    public function test_file_over_the_size_limit_is_rejected_per_collection(): void
    {
        $this->assertRejected(ImageFixtures::oversizedJpeg(2100), 'photo', 'file', 'Ukuran gambar maksimal 2 MB.');
        $this->assertRejected(ImageFixtures::oversizedJpeg(4200, 1600, 600), 'image', 'file', 'Ukuran banner maksimal 4 MB.');
    }

    public function test_banner_between_2_and_4_mb_is_accepted(): void
    {
        $media = $this->service->store(ImageFixtures::oversizedJpeg(3000, 1600, 600), 'image', null, ['alt_text' => 'Banner']);

        $this->assertSame('image', $media->collection);
        $this->assertSame(1600, $media->width);
    }

    /**
     * @return array<string, array{0: string, 1: int, 2: int, 3: string}>
     */
    public static function undersizedUploads(): array
    {
        return [
            'photo 299x299' => ['photo', 299, 299, 'Dimensi gambar minimal 300 x 300 piksel dan maksimal 6000 x 6000 piksel.'],
            'cover 799x450' => ['cover', 799, 450, 'Dimensi gambar minimal 800 x 450 piksel dan maksimal 6000 x 6000 piksel.'],
            'gallery 599x400' => ['gallery', 599, 400, 'Dimensi gambar minimal 600 x 400 piksel dan maksimal 6000 x 6000 piksel.'],
            'photos 600x399' => ['photos', 600, 399, 'Dimensi gambar minimal 600 x 400 piksel dan maksimal 6000 x 6000 piksel.'],
            'image 1199x400' => ['image', 1199, 400, 'Dimensi banner minimal 1200 x 400 piksel dan maksimal 6000 x 6000 piksel.'],
            'logo 119x40' => ['logo', 119, 40, 'Dimensi logo minimal 120 x 40 piksel dan maksimal 6000 x 6000 piksel.'],
            'editor 199x100' => ['editor', 199, 100, 'Dimensi gambar minimal 200 x 100 piksel dan maksimal 6000 x 6000 piksel.'],
        ];
    }

    #[DataProvider('undersizedUploads')]
    public function test_image_below_the_collection_minimum_is_rejected(string $collection, int $width, int $height, string $message): void
    {
        $this->assertRejected(ImageFixtures::jpeg($width, $height), $collection, 'file', $message);
    }

    /**
     * @return array<string, array{0: string, 1: int, 2: int}>
     */
    public static function minimumUploads(): array
    {
        return [
            'photo 300x300' => ['photo', 300, 300],
            'cover 800x450' => ['cover', 800, 450],
            'gallery 600x400' => ['gallery', 600, 400],
            'photos 600x400' => ['photos', 600, 400],
            'image 1200x400' => ['image', 1200, 400],
            'logo 120x40' => ['logo', 120, 40],
            'editor 200x100' => ['editor', 200, 100],
        ];
    }

    #[DataProvider('minimumUploads')]
    public function test_image_exactly_at_the_collection_minimum_is_accepted(string $collection, int $width, int $height): void
    {
        $media = $this->service->store(ImageFixtures::jpeg($width, $height), $collection, null, ['alt_text' => 'Teks alternatif']);

        $this->assertSame($width, $media->width);
        $this->assertSame($height, $media->height);
    }

    public function test_image_above_6000_px_is_rejected_and_6000_px_is_accepted(): void
    {
        $this->assertRejected(
            ImageFixtures::jpeg(6001, 100),
            'editor',
            'file',
            'Dimensi gambar minimal 200 x 100 piksel dan maksimal 6000 x 6000 piksel.',
        );

        $media = $this->service->store(ImageFixtures::jpeg(6000, 100), 'editor', null, ['alt_text' => 'Panorama']);

        $this->assertSame(1600, $media->width);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function svgCollections(): array
    {
        return [
            'foto dokter' => ['photo'],
            'banner' => ['image'],
            'galeri' => ['gallery'],
            'logo' => ['logo'],
            'editor' => ['editor'],
        ];
    }

    #[DataProvider('svgCollections')]
    public function test_svg_is_rejected_for_every_public_collection(string $collection): void
    {
        $this->assertRejected(ImageFixtures::svgWithScript(), $collection, 'file', ImageRules::WRONG_TYPE);
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function unsafeFiles(): array
    {
        return [
            'PHP berkepala JPEG (shell.php.jpg)' => ['phpWithJpegHeader', 'photo', ImageRules::INVALID_IMAGE],
            'skrip x.phtml' => ['phpScript', 'photo', ImageRules::WRONG_TYPE],
            'teks bernama .jpg' => ['textNamedJpg', 'photo', ImageRules::WRONG_TYPE],
            'PNG dengan data rusak' => ['corruptPng', 'gallery', ImageRules::INVALID_IMAGE],
        ];
    }

    #[DataProvider('unsafeFiles')]
    public function test_disguised_and_corrupt_files_are_rejected_without_leaving_anything(string $fixture, string $collection, string $message): void
    {
        $this->assertRejected(ImageFixtures::{$fixture}(), $collection, 'file', $message);
    }

    public function test_failed_php_upload_is_rejected_with_the_server_limit_message(): void
    {
        $valid = ImageFixtures::jpeg(800, 800);
        $failed = new UploadedFile($valid->getRealPath(), 'foto.jpg', 'image/jpeg', UPLOAD_ERR_INI_SIZE, true);

        $this->assertRejected($failed, 'photo', 'file', ImageRules::UPLOAD_FAILED);
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function invalidAltTexts(): array
    {
        return [
            'kosong' => [['alt_text' => ''], 'Teks alternatif gambar wajib diisi.'],
            'hanya spasi' => [['alt_text' => '   '], 'Teks alternatif gambar wajib diisi.'],
            'tidak dikirim' => [[], 'Teks alternatif gambar wajib diisi.'],
            '256 karakter' => [['alt_text' => str_repeat('a', 256)], 'Teks alternatif gambar maksimal 255 karakter.'],
        ];
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    #[DataProvider('invalidAltTexts')]
    public function test_alt_text_is_required_and_limited_to_255_characters(array $meta, string $message): void
    {
        $this->assertRejected(ImageFixtures::jpeg(800, 800), 'photo', 'alt_text', $message, $meta);
    }

    public function test_caption_longer_than_255_characters_is_rejected(): void
    {
        $this->assertRejected(
            ImageFixtures::jpeg(800, 800),
            'photo',
            'caption',
            'Keterangan gambar maksimal 255 karakter.',
            ['alt_text' => 'Foto', 'caption' => str_repeat('k', 256)],
        );
    }

    public function test_alt_text_of_255_characters_is_accepted_and_the_caption_is_trimmed(): void
    {
        $media = $this->service->store(
            ImageFixtures::jpeg(800, 800),
            'photo',
            null,
            ['alt_text' => str_repeat('a', 255), 'caption' => '  Keterangan  '],
        );

        $this->assertSame(255, mb_strlen($media->alt_text));
        $this->assertSame('Keterangan', $media->caption);

        $blank = $this->service->store(ImageFixtures::jpeg(800, 800), 'photo', null, ['alt_text' => 'Foto', 'caption' => '   ']);

        $this->assertNull($blank->caption);
    }

    public function test_file_and_alt_text_errors_are_reported_together(): void
    {
        try {
            $this->service->store(ImageFixtures::svgWithScript(), 'photo', null, ['alt_text' => '']);
            $this->fail('ValidationException tidak dilempar.');
        } catch (ValidationException $e) {
            $this->assertSame(['file', 'alt_text'], array_keys($e->errors()));
        }
    }

    public function test_owner_must_already_be_persisted(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Pemilik media harus sudah tersimpan.');

        try {
            $this->service->store(ImageFixtures::jpeg(800, 800), 'photo', new User, ['alt_text' => 'Foto']);
        } finally {
            $this->assertSame([], Storage::disk('public')->allFiles());
        }
    }
}
