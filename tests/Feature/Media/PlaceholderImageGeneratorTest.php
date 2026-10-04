<?php

namespace Tests\Feature\Media;

use App\Services\Media\MediaService;
use App\Services\Media\PlaceholderImageGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Tests\Support\InteractsWithMasterData;
use Tests\TestCase;

class PlaceholderImageGeneratorTest extends TestCase
{
    use InteractsWithMasterData;
    use RefreshDatabase;

    /** @var list<string> */
    private array $temporaryFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $path) {
            @unlink($path);
        }

        parent::tearDown();
    }

    private function make(string $type, string $label, int $width, int $height, ?string $font = null): UploadedFile
    {
        $file = (new PlaceholderImageGenerator($font))->make($type, $label, $width, $height);
        $this->temporaryFiles[] = $file->getPathname();

        return $file;
    }

    /** @return array{int, int, int} */
    private function pixel(UploadedFile $file, int $x, int $y): array
    {
        $image = imagecreatefromstring((string) file_get_contents($file->getPathname()));
        $color = imagecolorat($image, $x, $y);

        return [($color >> 16) & 0xFF, ($color >> 8) & 0xFF, $color & 0xFF];
    }

    private function assertNearHex(string $hex, array $rgb, int $tolerance = 8): void
    {
        $hex = ltrim($hex, '#');
        $expected = [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];

        foreach ($expected as $channel => $value) {
            $this->assertEqualsWithDelta($value, $rgb[$channel], $tolerance, "Kanal {$channel} jauh dari #{$hex}");
        }
    }

    /** Luminans terendah dan tertinggi di area tengah (tempat inisial digambar). */
    private function centerExtremes(UploadedFile $file): array
    {
        $image = imagecreatefromstring((string) file_get_contents($file->getPathname()));
        $width = imagesx($image);
        $height = imagesy($image);
        $min = 255.0;
        $max = 0.0;

        for ($y = (int) ($height * 0.28); $y < (int) ($height * 0.58); $y += 2) {
            for ($x = (int) ($width * 0.25); $x < (int) ($width * 0.75); $x += 2) {
                $color = imagecolorat($image, $x, $y);
                $luma = 0.299 * (($color >> 16) & 0xFF) + 0.587 * (($color >> 8) & 0xFF) + 0.114 * ($color & 0xFF);
                $min = min($min, $luma);
                $max = max($max, $luma);
            }
        }

        return [$min, $max];
    }

    public function test_photo_is_a_valid_600_square_jpeg_in_test_mode(): void
    {
        $file = $this->make('photo', 'RP', 600, 600);

        $this->assertTrue($file->isValid());
        $this->assertSame('rp.jpg', $file->getClientOriginalName());
        $info = getimagesize($file->getPathname());
        $this->assertSame([600, 600, IMAGETYPE_JPEG], [$info[0], $info[1], $info[2]]);
        $this->assertLessThan(2 * 1024 * 1024, filesize($file->getPathname()));
    }

    public function test_cover_is_a_1200_by_800_jpeg_and_logo_is_a_png(): void
    {
        $cover = getimagesize($this->make('cover', 'URO', 1200, 800)->getPathname());
        $logo = getimagesize($this->make('logo', 'UH', 400, 200)->getPathname());

        $this->assertSame([1200, 800, IMAGETYPE_JPEG], [$cover[0], $cover[1], $cover[2]]);
        $this->assertSame([400, 200, IMAGETYPE_PNG], [$logo[0], $logo[1], $logo[2]]);
    }

    public function test_background_comes_from_the_token_palette_and_is_deterministic(): void
    {
        $palette = array_map(
            fn (string $hex): array => [hexdec(substr($hex, 1, 2)), hexdec(substr($hex, 3, 2)), hexdec(substr($hex, 5, 2))],
            PlaceholderImageGenerator::BACKGROUNDS,
        );
        $seen = [];

        foreach (['URO', 'PD', 'ANK', 'OBG', 'BDH', 'JTG', 'SRF', 'MTA', 'THT', 'KK', 'ORT', 'UMM', 'GG'] as $label) {
            $rgb = $this->pixel($this->make('cover', $label, 1200, 800), 2, 2);
            $matched = array_filter($palette, fn (array $color): bool => abs($color[0] - $rgb[0]) <= 8 && abs($color[1] - $rgb[1]) <= 8 && abs($color[2] - $rgb[2]) <= 8);
            $this->assertNotEmpty($matched, "Latar {$label} di luar palet token.");
            $seen[array_key_first($matched)] = true;
        }

        $this->assertGreaterThanOrEqual(2, count($seen), 'Latar harus bergilir, bukan satu warna.');
        $this->assertSame(
            $this->pixel($this->make('photo', 'RP', 600, 600), 2, 2),
            $this->pixel($this->make('photo', 'RP', 600, 600), 2, 2),
        );
    }

    public function test_text_is_dark_on_the_light_teal_background_and_white_elsewhere(): void
    {
        $teal = null;
        $other = null;

        foreach (range('A', 'Z') as $letter) {
            $isTeal = crc32($letter) % count(PlaceholderImageGenerator::BACKGROUNDS) === 3;
            $teal ??= $isTeal ? $letter : null;
            $other ??= $isTeal ? null : $letter;
        }

        [$tealMin] = $this->centerExtremes($this->make('cover', (string) $teal, 1200, 800));
        [, $otherMax] = $this->centerExtremes($this->make('cover', (string) $other, 1200, 800));

        $this->assertLessThan(70, $tealMin, 'Teks pada latar #13A89E harus gelap (#08233B).');
        $this->assertGreaterThan(220, $otherMax, 'Teks pada latar tua harus putih.');
    }

    public function test_ribbon_color_is_drawn_across_the_image(): void
    {
        $file = $this->make('cover', 'URO', 1200, 800);

        $this->assertNearHex(PlaceholderImageGenerator::RIBBON, $this->pixel($file, 10, 580));
    }

    public function test_it_works_without_a_font_file(): void
    {
        $file = $this->make('photo', 'RP', 600, 600, '/tidak/ada/font.ttf');

        $this->assertSame(600, getimagesize($file->getPathname())[0]);
    }

    public function test_it_uses_a_truetype_font_when_one_is_available(): void
    {
        $font = collect([
            'C:\\Windows\\Fonts\\arialbd.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
            '/Library/Fonts/Arial Bold.ttf',
        ])->first(fn (string $path): bool => is_file($path));

        if ($font === null) {
            $this->markTestSkipped('Tidak ada berkas TTF sistem untuk menguji jalur TrueType.');
        }

        $file = $this->make('photo', 'RP', 600, 600, $font);

        $this->assertSame(600, getimagesize($file->getPathname())[0]);
    }

    public function test_invalid_arguments_are_rejected(): void
    {
        $generator = new PlaceholderImageGenerator;

        foreach ([['video', 'RP', 600, 600], ['photo', '  ', 600, 600], ['photo', 'RP', 8, 600], ['photo', 'RP', 600, 7000]] as $arguments) {
            try {
                $generator->make(...$arguments);
                $this->fail('InvalidArgumentException diharapkan untuk '.json_encode($arguments));
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_output_is_accepted_by_the_media_service_with_alt_text(): void
    {
        Storage::fake('public');
        $doctor = $this->createDoctor();
        $file = $this->make('photo', 'RP', 600, 600);

        $media = $this->app->make(MediaService::class)->store($file, 'photo', $doctor, ['alt_text' => 'Foto contoh dr. Uji']);

        $this->assertSame('photo', $media->collection);
        $this->assertSame([600, 600], [$media->width, $media->height]);
        $this->assertSame('Foto contoh dr. Uji', $media->alt_text);
        Storage::disk('public')->assertExists($media->directory.'/'.$media->file_name);
        Storage::disk('public')->assertExists((string) $media->thumb_path);
    }
}
