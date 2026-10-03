<?php

namespace Tests\Feature\Content;

use App\Services\Content\HtmlSanitizer;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class HtmlSanitizerDomTest extends TestCase
{
    private HtmlSanitizer $sanitizer;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.url' => 'http://unpam-hospital.test']);
        $this->useProfile([]);

        $this->sanitizer = new HtmlSanitizer;
    }

    /**
     * Ganti profil rich_text untuk tes ini (kunci berisi titik tidak bisa di-set lewat notasi titik config()).
     *
     * @param  array<string, mixed>  $overrides
     */
    private function useProfile(array $overrides): void
    {
        config(['purifier.settings.rich_text' => array_replace(
            config('purifier.settings.rich_text'),
            ['URI.Host' => 'unpam-hospital.test'],
            $overrides,
        )]);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function foreignImages(): array
    {
        return [
            'path bukan /storage' => ['<img src="/images/x.jpg" alt="x">'],
            'path relatif tanpa garis miring' => ['<img src="images/x.jpg" alt="x">'],
            'host lain' => ['<img src="https://evil.test/storage/a.jpg" alt="x">'],
            'protocol-relative' => ['<img src="//unpam-hospital.test/storage/a.jpg" alt="x">'],
            'traversal' => ['<img src="/storage/../../etc/passwd" alt="x">'],
            'host sendiri tetapi bukan /storage' => ['<img src="http://unpam-hospital.test/uploads/a.jpg" alt="x">'],
        ];
    }

    #[DataProvider('foreignImages')]
    public function test_images_outside_storage_are_removed(string $html): void
    {
        $this->assertSame('', $this->sanitizer->clean($html));
    }

    public function test_removing_an_image_keeps_the_surrounding_text(): void
    {
        $clean = $this->sanitizer->clean('<p>Teks <img src="/images/x.jpg" alt="x"> lagi</p>');

        $this->assertStringContainsString('Teks', $clean);
        $this->assertStringContainsString('lagi', $clean);
        $this->assertStringNotContainsString('<img', $clean);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function absoluteStorageImages(): array
    {
        return [
            'http' => ['http://unpam-hospital.test/storage/photo/a.jpg', '/storage/photo/a.jpg'],
            'https dengan query' => ['https://unpam-hospital.test/storage/photo/a.jpg?v=2', '/storage/photo/a.jpg?v=2'],
            'dengan port' => ['http://unpam-hospital.test:8000/storage/photo/a.jpg', '/storage/photo/a.jpg'],
            'sudah relatif' => ['/storage/photo/a.jpg', '/storage/photo/a.jpg'],
        ];
    }

    #[DataProvider('absoluteStorageImages')]
    public function test_absolute_urls_to_the_app_host_are_normalized_to_a_relative_path(string $source, string $expected): void
    {
        $clean = $this->sanitizer->clean('<img src="'.$source.'" alt="Foto">');

        $this->assertSame('<img src="'.$expected.'" alt="Foto">', $clean);
    }

    public function test_second_layer_removes_external_images_even_when_purifier_allows_them(): void
    {
        $this->useProfile(['URI.DisableExternalResources' => false]);

        $clean = $this->sanitizer->clean('<img src="https://evil.test/a.jpg" alt="x"><img src="http://unpam-hospital.test/storage/a.jpg" alt="ok">');

        $this->assertSame('<img src="/storage/a.jpg" alt="ok">', $clean);
    }

    public function test_external_links_get_target_blank_and_noopener_noreferrer(): void
    {
        $clean = $this->sanitizer->clean('<a href="https://example.org/halaman" title="Judul">ext</a>');

        $this->assertStringContainsString('href="https://example.org/halaman"', $clean);
        $this->assertStringContainsString('target="_blank"', $clean);
        $this->assertStringContainsString('rel="noopener noreferrer"', $clean);
    }

    public function test_protocol_relative_links_are_treated_as_external(): void
    {
        $clean = $this->sanitizer->clean('<a href="//evil.test/x">x</a>');

        $this->assertStringContainsString('target="_blank"', $clean);
        $this->assertStringContainsString('rel="noopener noreferrer"', $clean);
    }

    public function test_rel_is_forced_to_exactly_noopener_noreferrer(): void
    {
        $clean = $this->sanitizer->clean('<a href="https://example.org/x" target="_blank" rel="noopener">x</a>');

        $this->assertSame('<a href="https://example.org/x" target="_blank" rel="noopener noreferrer">x</a>', $clean);
    }

    public function test_internal_links_are_left_alone(): void
    {
        $this->assertSame('<a href="/dokter">d</a>', $this->sanitizer->clean('<a href="/dokter">d</a>'));

        $absolute = $this->sanitizer->clean('<a href="http://unpam-hospital.test/dokter">d</a>');
        $this->assertStringNotContainsString('target=', $absolute);
        $this->assertStringNotContainsString('rel=', $absolute);

        $shouty = $this->sanitizer->clean('<a href="HTTPS://UNPAM-HOSPITAL.TEST/dokter">d</a>');
        $this->assertStringNotContainsString('target=', $shouty);
    }

    public function test_mailto_and_tel_links_are_kept(): void
    {
        $html = '<a href="mailto:a@b.test">m</a> <a href="tel:+62211234">t</a>';

        $this->assertSame($html, $this->sanitizer->clean($html));
    }

    public function test_second_layer_removes_disallowed_schemes_even_when_purifier_allows_them(): void
    {
        $this->useProfile(['URI.AllowedSchemes' => ['http' => true, 'https' => true, 'mailto' => true, 'tel' => true, 'ftp' => true]]);

        $this->assertSame('<a>f</a>', $this->sanitizer->clean('<a href="ftp://x.test/f">f</a>'));
    }

    public function test_utf8_text_survives_the_dom_round_trip(): void
    {
        $html = '<p>Dokter spesialis — anak “ya” é ü</p>';

        $this->assertSame($html, $this->sanitizer->clean($html));
    }
}
