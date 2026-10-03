<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PlaceholderSvgTest extends TestCase
{
    private const NAMES = ['article', 'banner', 'clinic', 'doctor', 'facility', 'gallery', 'logo', 'room'];

    private const COLOR_TOKENS = ['#F3F7FB', '#0A4D8C', '#0E7C74', '#13A89E', '#1E7D45', '#FFFFFF'];

    /**
     * @return array<string, array{0: string, 1: float, 2: string}>
     */
    public static function placeholders(): array
    {
        return [
            'doctor 1:1' => ['doctor', 1.0, 'Foto contoh'],
            'clinic 3:2' => ['clinic', 3 / 2, 'Foto contoh'],
            'facility 3:2' => ['facility', 3 / 2, 'Foto contoh'],
            'room 3:2' => ['room', 3 / 2, 'Foto contoh'],
            'gallery 3:2' => ['gallery', 3 / 2, 'Foto contoh'],
            'article 16:9' => ['article', 16 / 9, 'Foto contoh'],
            'banner 8:3' => ['banner', 8 / 3, 'Foto contoh'],
            'logo 4:1' => ['logo', 4.0, 'UNPAM HOSPITAL'],
        ];
    }

    private function read(string $name): string
    {
        $path = dirname(__DIR__, 2).'/public/images/placeholders/'.$name.'.svg';

        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }

    public function test_exactly_the_eight_placeholders_exist(): void
    {
        $found = array_map(
            static fn (string $file): string => basename($file, '.svg'),
            glob(dirname(__DIR__, 2).'/public/images/placeholders/*.svg') ?: [],
        );
        sort($found);

        $this->assertSame(self::NAMES, $found);
    }

    #[DataProvider('placeholders')]
    public function test_svg_is_well_formed_with_title_role_and_the_expected_ratio(string $name, float $ratio, string $text): void
    {
        $content = $this->read($name);

        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($content);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $this->assertNotFalse($xml, "{$name}.svg bukan XML yang valid");

        $xml->registerXPathNamespace('s', 'http://www.w3.org/2000/svg');
        $titles = $xml->xpath('//s:title');

        $this->assertNotEmpty($titles);
        $this->assertNotSame('', trim((string) $titles[0]));
        $this->assertStringContainsString('role="img"', $content);
        $this->assertStringContainsString('aria-labelledby="t"', $content);
        $this->assertStringContainsString('<title id="t">', $content);

        $this->assertSame(1, preg_match('/viewBox="0 0 (\d+) (\d+)"/', $content, $box));
        $this->assertEqualsWithDelta($ratio, (int) $box[1] / (int) $box[2], 0.01);
    }

    #[DataProvider('placeholders')]
    public function test_svg_has_the_label_text_and_the_system_font(string $name, float $ratio, string $text): void
    {
        $content = $this->read($name);

        $this->assertStringContainsString('>'.$text.'<', $content);
        $this->assertStringContainsString('font-family="system-ui, sans-serif"', $content);

        if ($name === 'logo') {
            $this->assertStringContainsString('>UH<', $content);
        }
    }

    #[DataProvider('placeholders')]
    public function test_svg_has_no_active_content_or_external_references(string $name): void
    {
        $content = $this->read($name);
        $withoutNamespace = str_replace('http://www.w3.org/2000/svg', '', $content);

        $this->assertStringNotContainsString('http', $withoutNamespace);
        $this->assertStringNotContainsString('href', $content);
        $this->assertStringNotContainsString('@import', $content);
        $this->assertStringNotContainsString('url(', $content);
        $this->assertStringNotContainsString('data:', $content);
        $this->assertDoesNotMatchRegularExpression('/<\s*(script|image|style|foreignObject|use|a|iframe|object|embed)\b/i', $content);
        $this->assertDoesNotMatchRegularExpression('/\son[a-z]+\s*=/i', $content);
    }

    #[DataProvider('placeholders')]
    public function test_svg_uses_only_the_brand_colour_tokens(string $name): void
    {
        $content = $this->read($name);

        preg_match_all('/(?:fill|stroke)="([^"]+)"/', $content, $matches);

        $this->assertNotEmpty($matches[1]);

        foreach ($matches[1] as $color) {
            $this->assertContains(strtoupper($color), self::COLOR_TOKENS, "{$name}.svg memakai warna di luar token: {$color}");
        }
    }
}
