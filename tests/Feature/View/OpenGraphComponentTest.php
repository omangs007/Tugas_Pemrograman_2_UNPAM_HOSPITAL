<?php

namespace Tests\Feature\View;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * `x-open-graph` (F4A-R19): meta Open Graph dan canonical untuk halaman 4a. Anonim, tanpa JS, semua nilai di-escape.
 */
class OpenGraphComponentTest extends TestCase
{
    /** @param  array<string, mixed>  $data */
    private function render(string $attributes, array $data = []): string
    {
        return Blade::render('<x-open-graph '.$attributes.' />', $data);
    }

    public function test_it_renders_type_and_title_only_by_default(): void
    {
        $html = $this->render('title="Judul Uji"');

        $this->assertStringContainsString('<meta property="og:type" content="website">', $html);
        $this->assertStringContainsString('<meta property="og:title" content="Judul Uji">', $html);
        $this->assertStringNotContainsString('og:description', $html);
        $this->assertStringNotContainsString('og:image', $html);
        $this->assertStringNotContainsString('rel="canonical"', $html);
    }

    public function test_description_image_and_canonical_are_rendered_when_filled(): void
    {
        $html = $this->render(
            ':title="$title" :description="$description" :image="$image" :canonical="$canonical" type="article"',
            [
                'title' => 'Artikel Uji',
                'description' => 'Ringkasan artikel uji.',
                'image' => 'https://contoh.test/storage/cover.jpg',
                'canonical' => 'https://contoh.test/artikel/uji',
            ],
        );

        $this->assertStringContainsString('<meta property="og:type" content="article">', $html);
        $this->assertStringContainsString('<meta property="og:title" content="Artikel Uji">', $html);
        $this->assertStringContainsString('<meta property="og:description" content="Ringkasan artikel uji.">', $html);
        $this->assertStringContainsString('<meta property="og:image" content="https://contoh.test/storage/cover.jpg">', $html);
        $this->assertStringContainsString('<link rel="canonical" href="https://contoh.test/artikel/uji">', $html);
    }

    public function test_blank_description_image_and_canonical_are_omitted(): void
    {
        $html = $this->render(':title="$title" :description="$blank" :image="null" :canonical="$blank"', ['title' => 'Judul', 'blank' => '']);

        $this->assertStringNotContainsString('og:description', $html);
        $this->assertStringNotContainsString('og:image', $html);
        $this->assertStringNotContainsString('rel="canonical"', $html);
    }

    public function test_every_value_is_escaped(): void
    {
        $html = $this->render(
            ':title="$title" :description="$title" :image="$title" :canonical="$title"',
            ['title' => 'Judul "uji" <b>tebal</b> & lain'],
        );

        $this->assertStringContainsString('content="Judul &quot;uji&quot; &lt;b&gt;tebal&lt;/b&gt; &amp; lain"', $html);
        $this->assertStringNotContainsString('<b>tebal</b>', $html);
        $this->assertSame(0, preg_match('/\son[a-z]+\s*=/i', $html));
    }

    public function test_it_is_an_anonymous_component_without_inline_script(): void
    {
        $this->assertFileExists(resource_path('views/components/open-graph.blade.php'));
        $this->assertFileDoesNotExist(resource_path('views/components/open-graph.php'));
        $this->assertStringNotContainsString('<script', $this->render('title="Judul Uji"'));
    }

    public function test_title_is_required(): void
    {
        $this->expectException(\Throwable::class);

        $this->render('description="tanpa judul"');
    }
}
