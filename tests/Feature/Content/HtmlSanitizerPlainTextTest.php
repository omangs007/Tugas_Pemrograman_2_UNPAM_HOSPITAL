<?php

namespace Tests\Feature\Content;

use App\Services\Content\HtmlSanitizer;
use Tests\TestCase;

class HtmlSanitizerPlainTextTest extends TestCase
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

    public function test_tags_are_stripped_blocks_are_separated_and_entities_decoded(): void
    {
        $html = '<p>Satu&nbsp;dua</p><p>Tiga &amp; <strong>empat</strong></p><script>x()</script><ul><li>lima</li><li>enam</li></ul>';

        $this->assertSame('Satu dua Tiga & empat lima enam', $this->sanitizer->toPlainText($html));
    }

    public function test_escaped_angle_brackets_become_plain_characters(): void
    {
        $this->assertSame('1 < 2', $this->sanitizer->toPlainText('<p>1 &lt; 2</p>'));
    }

    public function test_dangerous_markup_never_reaches_the_plain_text(): void
    {
        $this->assertSame('Aman', $this->sanitizer->toPlainText('<p>Aman</p><script>alert(1)</script>'));
    }

    public function test_short_text_is_not_truncated(): void
    {
        $this->assertSame('pendek', $this->sanitizer->toPlainText('<p>pendek</p>'));
    }

    public function test_long_text_is_cut_at_the_limit_with_an_ellipsis(): void
    {
        $text = $this->sanitizer->toPlainText('<p>'.str_repeat('kata ', 100).'</p>', 20);

        $this->assertSame('kata kata kata kata…', $text);
    }

    public function test_default_limit_is_160_characters(): void
    {
        $text = $this->sanitizer->toPlainText(str_repeat('<p>'.str_repeat('abcde ', 40).'</p>', 3));

        $this->assertSame(161, mb_strlen($text));
        $this->assertStringEndsWith('…', $text);
    }

    public function test_empty_input_returns_an_empty_string(): void
    {
        $this->assertSame('', $this->sanitizer->toPlainText(null));
        $this->assertSame('', $this->sanitizer->toPlainText(''));
        $this->assertSame('', $this->sanitizer->toPlainText('<p>   </p>'));
    }
}
