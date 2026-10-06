<?php

namespace Tests\Feature\Content;

use App\Models\Article;
use App\Models\Clinic;
use App\Models\Page;
use App\Services\Content\HtmlSanitizer;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * F4A-R11 / Review Focus F.2: `SanitizesRichText::isBlankRichText()` dapat dipanggil publik (ArticleRequest,
 * Task 8) dan mengenali isi yang kosong secara visual.
 */
class BlankRichTextTest extends TestCase
{
    /** @return array<string, array{0: string, 1: bool}> */
    public static function samples(): array
    {
        return [
            'string kosong' => ['', true],
            'paragraf quill kosong' => ['<p><br></p>', true],
            'beberapa paragraf kosong' => ['<p><br></p><p> </p>', true],
            'spasi tak terputus sebagai entitas' => ['<p>&nbsp;</p>', true],
            'spasi tak terputus literal' => ["<p>\u{00A0}</p>", true],
            'hanya spasi' => ['   ', true],
            'heading kosong' => ['<h2></h2>', true],
            'ada teks' => ['<p>Halo</p>', false],
            'teks di antara tag kosong' => ['<p><br></p><p>Isi</p>', false],
            'gambar saja' => ['<img src="/storage/editor/2026/10/a.jpg" alt="a">', false],
            'gambar dalam paragraf' => ['<p><img src="/storage/editor/2026/10/a.jpg" alt="a"></p>', false],
            'angka nol' => ['<p>0</p>', false],
        ];
    }

    #[DataProvider('samples')]
    public function test_blank_detection_is_public_and_static_on_every_rich_text_model(string $html, bool $blank): void
    {
        $this->assertSame($blank, Article::isBlankRichText($html));
        $this->assertSame($blank, Page::isBlankRichText($html));
        $this->assertSame($blank, Clinic::isBlankRichText($html));
    }

    public function test_it_is_meant_to_run_on_the_sanitized_html(): void
    {
        $sanitizer = app(HtmlSanitizer::class);

        $this->assertTrue(Article::isBlankRichText($sanitizer->clean('<script>alert(1)</script>')));
        $this->assertTrue(Article::isBlankRichText($sanitizer->clean('<p style="color:red"><br></p>')));
        $this->assertTrue(Article::isBlankRichText($sanitizer->clean('<iframe src="https://evil.test"></iframe>')));
        $this->assertFalse(Article::isBlankRichText($sanitizer->clean('<script>x</script><p>Isi artikel</p>')));
        $this->assertTrue(Article::isBlankRichText($sanitizer->clean('<img src="https://evil.test/x.png" alt="x">')), 'Gambar luar dibuang sanitizer sehingga isi kosong.');
    }
}
