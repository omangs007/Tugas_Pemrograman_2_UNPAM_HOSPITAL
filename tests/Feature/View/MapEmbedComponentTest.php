<?php

namespace Tests\Feature\View;

use App\Services\Settings\SettingsService;
use App\View\Components\MapEmbed;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * `x-map-embed` (F4B-R33, 10 §3.4, OFF-04, A11-05): alamat dan tombol Petunjuk Arah selalu ada; iframe hanya bila
 * `map.embed_url` berupa URL https; kotak pengganti berperan `status`; tanpa atribut event inline.
 */
class MapEmbedComponentTest extends TestCase
{
    use RefreshDatabase;

    private const EMBED = 'https://www.google.com/maps?q=Universitas+Pamulang+Kampus+2+Viktor&output=embed';

    private const DIRECTIONS = 'https://www.google.com/maps/search/?api=1&query=Universitas+Pamulang+Kampus+2+Viktor';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SettingsSeeder::class);
    }

    private function render(string $tag = '<x-map-embed />'): string
    {
        return Blade::render($tag);
    }

    /** @param  array<string, ?string>  $values */
    private function configure(array $values): void
    {
        app(SettingsService::class)->setMany($values);
    }

    public function test_it_renders_the_container_fallback_and_iframe_from_the_settings(): void
    {
        $html = $this->render();

        $this->assertStringContainsString('<div class="map-embed" data-map-embed style="--map-height: 360px">', $html);
        $this->assertStringContainsString('<div class="map-embed-fallback" role="status" data-map-fallback>', $html);
        $this->assertStringContainsString('Memuat peta…', $html);
        $this->assertStringContainsString('<iframe src="'.e(self::EMBED).'" title="Peta lokasi UNPAM HOSPITAL" loading="lazy" referrerpolicy="no-referrer-when-downgrade" data-map-frame></iframe>', $html);
        $this->assertSame(1, substr_count($html, '<iframe'));
    }

    public function test_the_address_is_always_rendered_in_the_fallback(): void
    {
        $this->assertStringContainsString('Universitas Pamulang Kampus 2 Viktor, Jl. Raya Puspitek', $this->render());

        $this->configure(['map.embed_url' => null]);

        $html = $this->render();

        $this->assertStringContainsString('Universitas Pamulang Kampus 2 Viktor, Jl. Raya Puspitek', $html);
        $this->assertStringContainsString('Peta tidak tersedia.', $html);
        $this->assertStringNotContainsString('<iframe', $html);
        $this->assertStringNotContainsString('data-map-frame', $html);
        $this->assertStringNotContainsString('Memuat peta…', $html);
    }

    public function test_the_directions_button_opens_in_a_new_tab_safely(): void
    {
        $html = $this->render();

        $this->assertStringContainsString('href="'.e(self::DIRECTIONS).'" target="_blank" rel="noopener noreferrer"', $html);
        $this->assertStringContainsString('<i class="bi bi-geo-alt" aria-hidden="true"></i> Petunjuk Arah</a>', $html);

        $this->configure(['map.embed_url' => null]);

        $this->assertStringContainsString('Petunjuk Arah</a>', $this->render());
    }

    public function test_height_and_title_are_props(): void
    {
        $html = $this->render('<x-map-embed :height="300" title="Peta Uji Coba" />');

        $this->assertStringContainsString('style="--map-height: 300px"', $html);
        $this->assertStringContainsString('title="Peta Uji Coba"', $html);
        $this->assertStringNotContainsString('Peta lokasi UNPAM HOSPITAL', $html);
    }

    public function test_a_numeric_string_height_is_coerced_to_an_integer(): void
    {
        $html = $this->render('<x-map-embed height="250" />');

        $this->assertStringContainsString('style="--map-height: 250px"', $html);
    }

    public function test_a_missing_directions_url_hides_the_button_but_keeps_the_address(): void
    {
        $this->configure(['map.directions_url' => null]);

        $html = $this->render();

        $this->assertStringNotContainsString('Petunjuk Arah', $html);
        $this->assertStringContainsString('Jl. Raya Puspitek', $html);
        $this->assertSame(1, substr_count($html, '<iframe'));
    }

    public function test_with_no_address_and_no_urls_only_the_status_text_remains(): void
    {
        $this->configure(['contact.address' => null, 'map.directions_url' => null, 'map.embed_url' => null]);

        $html = $this->render();

        $this->assertStringContainsString('Peta tidak tersedia.', $html);
        $this->assertStringNotContainsString('<address', $html);
        $this->assertStringNotContainsString('<a ', $html);
        $this->assertStringNotContainsString('<iframe', $html);
    }

    public function test_unsafe_urls_are_never_rendered(): void
    {
        $this->configure(['map.embed_url' => 'javascript:alert(1)', 'map.directions_url' => 'javascript:alert(2)']);

        $html = $this->render();

        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringNotContainsString('<iframe', $html);
        $this->assertStringNotContainsString('Petunjuk Arah', $html);

        $this->configure(['map.embed_url' => 'http://www.google.com/maps?output=embed', 'map.directions_url' => 'ftp://contoh.test/arah']);

        $html = $this->render();

        $this->assertStringNotContainsString('<iframe', $html, 'Hanya https yang boleh disematkan.');
        $this->assertStringNotContainsString('ftp://', $html);
    }

    public function test_the_address_and_title_are_escaped(): void
    {
        $this->configure(['contact.address' => '<b>Jl. Uji</b> & Co']);

        $html = Blade::render('<x-map-embed :title="$judul" />', ['judul' => 'Peta "uji" <i>x</i>']);

        $this->assertStringContainsString('&lt;b&gt;Jl. Uji&lt;/b&gt; &amp; Co', $html);
        $this->assertStringContainsString('title="Peta &quot;uji&quot; &lt;i&gt;x&lt;/i&gt;"', $html);
        $this->assertStringNotContainsString('<b>Jl. Uji</b>', $html);
        $this->assertStringNotContainsString('<i>x</i>', $html);
    }

    public function test_the_fallback_is_a_single_status_region_and_the_iframe_has_a_title(): void
    {
        $html = $this->render();

        $this->assertSame(1, substr_count($html, 'role="status"'));
        $this->assertSame(1, preg_match('/<iframe[^>]*\stitle="[^"]+"/', $html));
        $this->assertStringContainsString('data-map-fallback-text', $html);
    }

    public function test_the_markup_has_no_inline_event_handlers_or_scripts(): void
    {
        $html = $this->render();

        $this->assertSame(0, preg_match('/\son[a-z]+\s*=/i', $html));
        $this->assertStringNotContainsString('<script', $html);

        $source = (string) file_get_contents(resource_path('views/components/map-embed.blade.php'));
        $this->assertStringNotContainsString('onerror', strtolower($source));
        $this->assertStringNotContainsString('{!!', $source);
    }

    public function test_the_component_is_a_final_strict_class(): void
    {
        $this->assertTrue((new \ReflectionClass(MapEmbed::class))->isFinal());
        $this->assertStringContainsString('declare(strict_types=1);', (string) file_get_contents(app_path('View/Components/MapEmbed.php')));
    }
}
