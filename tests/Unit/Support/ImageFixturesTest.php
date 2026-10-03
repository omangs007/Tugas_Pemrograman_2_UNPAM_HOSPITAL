<?php

namespace Tests\Unit\Support;

use finfo;
use PHPUnit\Framework\TestCase;
use Tests\Support\ImageFixtures;

class ImageFixturesTest extends TestCase
{
    private function mime(string $path): string
    {
        return (string) (new finfo(FILEINFO_MIME_TYPE))->file($path);
    }

    public function test_jpeg_has_the_requested_size_and_a_real_jpeg_mime(): void
    {
        $file = ImageFixtures::jpeg(640, 480, 'a.jpg');
        $path = $file->getRealPath();

        $this->assertSame('image/jpeg', $this->mime($path));
        $this->assertSame([640, 480], array_slice(getimagesize($path), 0, 2));
        $this->assertSame('a.jpg', $file->getClientOriginalName());
        $this->assertFalse(ImageFixtures::hasExifMarker($path));
    }

    public function test_gps_jpeg_carries_exif_and_gps_before_upload_and_is_still_a_valid_jpeg(): void
    {
        $file = ImageFixtures::jpegWithGps(3000, 2000);
        $path = $file->getRealPath();

        $this->assertTrue(ImageFixtures::hasExifMarker($path));
        $this->assertTrue(ImageFixtures::hasGpsTag($path));
        $this->assertSame([3000, 2000], array_slice(getimagesize($path), 0, 2));
        $this->assertSame("\xFF\xD8\xFF\xE1", substr((string) file_get_contents($path), 0, 4));
    }

    public function test_oversized_jpeg_reaches_the_requested_size_and_stays_a_valid_jpeg(): void
    {
        $file = ImageFixtures::oversizedJpeg(2100);

        $this->assertGreaterThan(2048 * 1024, filesize($file->getRealPath()));
        $this->assertSame('image/jpeg', $this->mime($file->getRealPath()));
        $this->assertSame(800, getimagesize($file->getRealPath())[0]);
    }

    public function test_transparent_png_has_a_transparent_corner_and_an_opaque_centre(): void
    {
        $file = ImageFixtures::transparentPng(600, 400);
        $bytes = (string) file_get_contents($file->getRealPath());

        $this->assertSame('image/png', $this->mime($file->getRealPath()));
        $this->assertSame(127, ImageFixtures::alphaAt($bytes, 2, 2));
        $this->assertSame(0, ImageFixtures::alphaAt($bytes, 300, 200));
    }

    public function test_png_and_webp_have_their_real_mime_types(): void
    {
        $this->assertSame('image/png', $this->mime(ImageFixtures::png(300, 200)->getRealPath()));
        $this->assertSame('image/webp', $this->mime(ImageFixtures::webp(300, 200)->getRealPath()));
    }

    public function test_png_named_jpg_keeps_a_jpg_client_name_but_png_content(): void
    {
        $file = ImageFixtures::pngNamedJpg(300, 200, 'foto.jpg');

        $this->assertSame('foto.jpg', $file->getClientOriginalName());
        $this->assertSame('image/png', $this->mime($file->getRealPath()));
    }

    public function test_text_named_jpg_is_plain_text(): void
    {
        $file = ImageFixtures::textNamedJpg();

        $this->assertSame('bukan-gambar.jpg', $file->getClientOriginalName());
        $this->assertSame('text/plain', $this->mime($file->getRealPath()));
    }

    public function test_svg_fixture_is_svg_and_contains_a_script(): void
    {
        $file = ImageFixtures::svgWithScript();

        $this->assertSame('image/svg+xml', $this->mime($file->getRealPath()));
        $this->assertStringContainsString('<script>', (string) file_get_contents($file->getRealPath()));
    }

    public function test_php_with_jpeg_header_passes_finfo_but_is_not_a_decodable_image(): void
    {
        $file = ImageFixtures::phpWithJpegHeader();
        $path = $file->getRealPath();

        $this->assertSame('image/jpeg', $this->mime($path));
        $this->assertFalse(@getimagesize($path));
        $this->assertStringContainsString('<?php', (string) file_get_contents($path));
    }

    public function test_php_script_fixture_is_not_an_image_mime(): void
    {
        $this->assertStringStartsNotWith('image/', $this->mime(ImageFixtures::phpScript()->getRealPath()));
    }

    public function test_corrupt_png_has_a_valid_header_but_cannot_be_decoded(): void
    {
        $path = ImageFixtures::corruptPng(600, 400)->getRealPath();

        $this->assertSame('image/png', $this->mime($path));
        $this->assertSame([600, 400], array_slice(getimagesize($path), 0, 2));

        set_error_handler(static fn (): bool => true);

        try {
            $decoded = imagecreatefromstring((string) file_get_contents($path));
        } finally {
            restore_error_handler();
        }

        $this->assertFalse($decoded);
    }
}
