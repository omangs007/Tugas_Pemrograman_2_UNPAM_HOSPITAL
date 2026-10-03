<?php

namespace Tests\Feature\Content;

use App\Services\Content\HtmlSanitizer;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class HtmlSanitizerTest extends TestCase
{
    private HtmlSanitizer $sanitizer;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.url' => 'http://unpam-hospital.test']);
        config(['purifier.settings.rich_text' => array_replace(
            config('purifier.settings.rich_text'),
            ['URI.Host' => 'unpam-hospital.test'],
        )]);

        $this->sanitizer = new HtmlSanitizer;
    }

    public function test_empty_input_returns_an_empty_string(): void
    {
        $this->assertSame('', $this->sanitizer->clean(null));
        $this->assertSame('', $this->sanitizer->clean(''));
        $this->assertSame('', $this->sanitizer->clean("  \n\t "));
    }

    /**
     * @return array<string, array{0: string, 1: list<string>}>
     */
    public static function dangerousPayloads(): array
    {
        return [
            'tag script' => ['<p>Halo</p><script>alert(1)</script>', ['<script', 'alert']],
            'atribut onerror' => ['<img src="/storage/photo/a.jpg" alt="x" onerror="alert(1)">', ['onerror', 'alert']],
            'atribut onclick' => ['<a href="/x" onclick="alert(1)">x</a>', ['onclick', 'alert']],
            'href javascript' => ['<a href="javascript:alert(1)">klik</a>', ['javascript', 'alert']],
            'href javascript campur huruf' => ['<a href="JaVaScRiPt:alert(1)">x</a>', ['javascript', 'alert']],
            'href javascript berawalan spasi' => ['<a href=" javascript:alert(1)">x</a>', ['javascript', 'alert']],
            'href javascript entitas html' => ['<a href="&#106;avascript:alert(1)">x</a>', ['javascript', 'alert']],
            'href vbscript' => ['<a href="vbscript:msgbox(1)">x</a>', ['vbscript', 'msgbox']],
            'href data' => ['<a href="data:text/html;base64,PHNjcmlwdD4=">x</a>', ['data:', 'base64']],
            'img data uri' => ['<img src="data:image/png;base64,AAAA" alt="x">', ['data:', 'base64']],
            'atribut style' => ['<p style="color:red;background:url(javascript:alert(1))">x</p>', ['style', 'javascript']],
            'tag style' => ['<style>body{display:none}</style><p>ok</p>', ['<style', 'display']],
            'iframe' => ['<iframe src="https://evil.test"></iframe>', ['iframe', 'evil.test']],
            'svg onload' => ['<svg onload="alert(1)"><circle/></svg>', ['svg', 'onload', 'alert']],
            'object embed form input' => ['<object data="x"></object><embed src="x"><form action="/x"><input name="a"></form>', ['<object', '<embed', '<form', '<input']],
            'video audio' => ['<video src="x"></video><audio src="y"></audio>', ['<video', '<audio']],
            'class id dan kelas Quill' => ['<p class="ql-align-center" id="a">x</p>', ['class', 'id=', 'ql-']],
        ];
    }

    /**
     * @param  list<string>  $forbidden
     */
    #[DataProvider('dangerousPayloads')]
    public function test_dangerous_markup_is_removed(string $html, array $forbidden): void
    {
        $clean = $this->sanitizer->clean($html);

        foreach ($forbidden as $fragment) {
            $this->assertStringNotContainsStringIgnoringCase($fragment, $clean, "Muatan lolos: {$fragment}");
        }
    }

    public function test_script_content_is_removed_but_the_surrounding_text_stays(): void
    {
        $this->assertSame('<p>Halo dunia</p>', $this->sanitizer->clean('<p>Halo <script>alert(1)</script>dunia</p>'));
    }

    public function test_style_class_and_id_attributes_are_stripped_from_allowed_tags(): void
    {
        $this->assertSame('<p>Teks</p>', $this->sanitizer->clean('<p style="color:red" class="ql-align-center" id="x">Teks</p>'));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function safeMarkup(): array
    {
        return [
            'paragraf, daftar, judul, kutipan' => ['<ul><li>satu</li></ul><h2>Judul</h2><p>Dokter — “anak” &amp; remaja <br>baris</p>'],
            'daftar berurut, kutipan, h3, h4, u, em' => ['<ol><li>a</li></ol><blockquote>kutip</blockquote><h3>H3</h3><h4>H4</h4><p><u>garis</u> <em>miring</em></p>'],
            'strong, em, tautan internal bertitle' => ['<p><strong>tebal</strong> <em>miring</em> <a href="/dokter" title="Daftar dokter">dokter</a></p>'],
            'gambar ber-alt dengan ukuran' => ['<p>Satu</p><p><img src="/storage/photo/2026/10/a.jpg" alt="Foto dokter" width="300" height="200"></p>'],
            'tabel sederhana' => ['<table><thead><tr><th>A</th></tr></thead><tbody><tr><td>1</td></tr></tbody></table>'],
            'entitas teks' => ['<p>1 &lt; 2 &amp;&amp; 3 &gt; 2</p>'],
        ];
    }

    #[DataProvider('safeMarkup')]
    public function test_safe_markup_is_preserved_unchanged(string $html): void
    {
        $this->assertSame($html, $this->sanitizer->clean($html));
    }

    public function test_image_without_alt_gets_an_alt_from_the_file_name(): void
    {
        $this->assertStringContainsString('alt="a.jpg"', $this->sanitizer->clean('<img src="/storage/photo/a.jpg">'));
    }

    public function test_image_dimensions_are_capped_at_1600(): void
    {
        $clean = $this->sanitizer->clean('<img src="/storage/photo/a.jpg" alt="x" width="9999" height="20">');

        $this->assertStringContainsString('width="1600"', $clean);
        $this->assertStringContainsString('height="20"', $clean);
    }

    public function test_unsupported_formatting_tags_are_unwrapped(): void
    {
        $this->assertSame('tebal x', $this->sanitizer->clean('<b>tebal</b> <font color="red">x</font>'));
    }

    public function test_plain_text_without_tags_is_returned_as_is(): void
    {
        $this->assertSame('teks polos', $this->sanitizer->clean('teks polos'));
    }
}
