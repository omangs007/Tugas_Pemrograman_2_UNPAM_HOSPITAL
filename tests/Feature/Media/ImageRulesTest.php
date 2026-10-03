<?php

namespace Tests\Feature\Media;

use App\Rules\ImageRules;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ImageFixtures;
use Tests\TestCase;

class ImageRulesTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: int, 2: int, 3: int, 4: string, 5: string}>
     */
    public static function collections(): array
    {
        return [
            'photo' => ['photo', 2048, 300, 300, 'gambar', 'gambar'],
            'cover' => ['cover', 2048, 800, 450, 'gambar', 'gambar'],
            'gallery' => ['gallery', 2048, 600, 400, 'gambar', 'gambar'],
            'image' => ['image', 4096, 1200, 400, 'banner', 'banner'],
            'photos' => ['photos', 2048, 600, 400, 'gambar', 'gambar'],
            'logo' => ['logo', 2048, 120, 40, 'gambar', 'logo'],
            'editor' => ['editor', 2048, 200, 100, 'gambar', 'gambar'],
        ];
    }

    /**
     * @param  string  $maxSubject  kata benda pada pesan ukuran ("gambar" atau "banner")
     * @param  string  $dimensionSubject  kata benda pada pesan dimensi ("gambar", "banner", atau "logo")
     */
    #[DataProvider('collections')]
    public function test_rules_follow_blueprint_section_3_2(string $collection, int $maxKb, int $minWidth, int $minHeight, string $maxSubject, string $dimensionSubject): void
    {
        $this->assertSame([
            'file',
            'image',
            'mimes:jpg,jpeg,png,webp',
            'mimetypes:image/jpeg,image/png,image/webp',
            'max:'.$maxKb,
            "dimensions:min_width={$minWidth},min_height={$minHeight},max_width=6000,max_height=6000",
        ], ImageRules::for($collection));
    }

    #[DataProvider('collections')]
    public function test_messages_are_the_indonesian_texts_from_blueprint_section_3_2(string $collection, int $maxKb, int $minWidth, int $minHeight, string $maxSubject, string $dimensionSubject): void
    {
        $messages = ImageRules::messages('foto', $collection);

        $this->assertSame([
            'foto.file' => 'File harus berupa gambar.',
            'foto.image' => 'File harus berupa gambar.',
            'foto.mimes' => 'File harus berupa gambar JPG, PNG, atau WebP.',
            'foto.mimetypes' => 'File harus berupa gambar JPG, PNG, atau WebP.',
            'foto.max' => 'Ukuran '.$maxSubject.' maksimal '.($maxKb / 1024).' MB.',
            'foto.dimensions' => "Dimensi {$dimensionSubject} minimal {$minWidth} x {$minHeight} piksel dan maksimal 6000 x 6000 piksel.",
            'foto.uploaded' => 'Gambar gagal diunggah. Ukuran file melebihi batas server.',
        ], $messages);
    }

    public function test_helper_constants_hold_the_shared_texts(): void
    {
        $this->assertSame('File tidak dapat diproses sebagai gambar yang valid.', ImageRules::INVALID_IMAGE);
        $this->assertSame('File harus berupa gambar.', ImageRules::NOT_IMAGE);
        $this->assertSame('File harus berupa gambar JPG, PNG, atau WebP.', ImageRules::WRONG_TYPE);
        $this->assertSame('Gambar gagal diunggah. Ukuran file melebihi batas server.', ImageRules::UPLOAD_FAILED);
    }

    public function test_unknown_collection_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ImageRules::for('avatar');
    }

    public function test_unknown_collection_is_rejected_for_messages(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ImageRules::messages('file', 'avatar');
    }

    /**
     * @return list<string>
     */
    private function errorsFor(UploadedFile $file, string $collection): array
    {
        $validator = Validator::make(
            ['file' => $file],
            ['file' => ImageRules::for($collection)],
            ImageRules::messages('file', $collection),
        );

        return $validator->errors()->get('file');
    }

    public function test_valid_jpeg_passes(): void
    {
        $this->assertSame([], $this->errorsFor(ImageFixtures::jpeg(800, 800), 'photo'));
    }

    public function test_png_named_jpg_passes_because_the_real_mime_is_allowed(): void
    {
        $this->assertSame([], $this->errorsFor(ImageFixtures::pngNamedJpg(800, 600), 'photo'));
    }

    public function test_webp_and_transparent_png_pass(): void
    {
        $this->assertSame([], $this->errorsFor(ImageFixtures::webp(900, 600), 'gallery'));
        $this->assertSame([], $this->errorsFor(ImageFixtures::transparentPng(600, 400), 'logo'));
    }

    public function test_svg_is_rejected_with_the_wrong_type_message(): void
    {
        $errors = $this->errorsFor(ImageFixtures::svgWithScript(), 'photo');

        $this->assertContains('File harus berupa gambar JPG, PNG, atau WebP.', $errors);
    }

    public function test_text_file_named_jpg_is_rejected_with_the_wrong_type_message(): void
    {
        $errors = $this->errorsFor(ImageFixtures::textNamedJpg(), 'photo');

        $this->assertContains('File harus berupa gambar JPG, PNG, atau WebP.', $errors);
    }

    public function test_php_files_are_rejected(): void
    {
        $this->assertContains('File harus berupa gambar JPG, PNG, atau WebP.', $this->errorsFor(ImageFixtures::phpScript('x.phtml'), 'photo'));
        $this->assertNotSame([], $this->errorsFor(ImageFixtures::phpWithJpegHeader(), 'photo'));
    }

    public function test_file_over_the_size_limit_is_rejected(): void
    {
        $this->assertContains('Ukuran gambar maksimal 2 MB.', $this->errorsFor(ImageFixtures::oversizedJpeg(2100), 'photo'));
        $this->assertContains('Ukuran banner maksimal 4 MB.', $this->errorsFor(ImageFixtures::oversizedJpeg(4200, 1600, 600), 'image'));
        $this->assertSame([], $this->errorsFor(ImageFixtures::oversizedJpeg(3000, 1600, 600), 'image'));
    }

    public function test_image_below_the_minimum_dimensions_is_rejected(): void
    {
        $this->assertContains(
            'Dimensi gambar minimal 300 x 300 piksel dan maksimal 6000 x 6000 piksel.',
            $this->errorsFor(ImageFixtures::jpeg(299, 299), 'photo'),
        );
        $this->assertContains(
            'Dimensi banner minimal 1200 x 400 piksel dan maksimal 6000 x 6000 piksel.',
            $this->errorsFor(ImageFixtures::jpeg(1199, 400), 'image'),
        );
        $this->assertContains(
            'Dimensi logo minimal 120 x 40 piksel dan maksimal 6000 x 6000 piksel.',
            $this->errorsFor(ImageFixtures::jpeg(119, 40), 'logo'),
        );
    }

    public function test_image_above_the_maximum_dimensions_is_rejected(): void
    {
        $this->assertContains(
            'Dimensi gambar minimal 200 x 100 piksel dan maksimal 6000 x 6000 piksel.',
            $this->errorsFor(ImageFixtures::jpeg(6001, 100), 'editor'),
        );
        $this->assertSame([], $this->errorsFor(ImageFixtures::jpeg(6000, 100), 'editor'));
    }

    public function test_failed_php_upload_reports_the_server_limit_message(): void
    {
        $valid = ImageFixtures::jpeg(800, 800);
        $failed = new UploadedFile($valid->getRealPath(), 'foto.jpg', 'image/jpeg', UPLOAD_ERR_INI_SIZE, true);

        $this->assertSame(
            ['Gambar gagal diunggah. Ukuran file melebihi batas server.'],
            $this->errorsFor($failed, 'photo'),
        );
    }
}
