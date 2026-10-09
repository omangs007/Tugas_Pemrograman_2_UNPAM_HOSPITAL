<?php

namespace Tests\Feature\View;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

/**
 * SEK-15 (statis): `{!! !!}` hanya untuk kolom rich text tersanitasi. Di fase 2 hanya
 * `clinics.description` pada `public/clinics/show.blade.php`; fase 4a menambah kolom lain di tes ini.
 */
class RawOutputScanTest extends TestCase
{
    /** @return list<string> */
    private function viewFiles(): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('views'), FilesystemIterator::SKIP_DOTS));

        foreach ($iterator as $file) {
            if (str_ends_with($file->getFilename(), '.blade.php')) {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    private function relative(string $path): string
    {
        return ltrim(str_replace('\\', '/', substr($path, strlen(resource_path('views')))), '/');
    }

    public function test_raw_blade_output_is_only_used_for_the_rich_text_columns(): void
    {
        $found = [];

        foreach ($this->viewFiles() as $file) {
            foreach (file($file, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
                if (str_contains($line, '{!!')) {
                    $found[] = $this->relative($file).': '.trim($line);
                }
            }
        }

        sort($found);

        $this->assertSame([
            'public/articles/show.blade.php: <div class="rich-text article-body">{!! $article->body !!}</div>',
            'public/clinics/show.blade.php: <div class="rich-text clinic-description">{!! $clinic->description !!}</div>',
            'public/facilities/show.blade.php: <div class="rich-text facility-body">{!! $facility->description !!}</div>',
            'public/partials/_page-body.blade.php: <div class="rich-text page-body">{!! $page->body !!}</div>',
            'public/rooms/show.blade.php: <div class="rich-text room-body">{!! $room->description !!}</div>',
        ], $found);
    }

    public function test_views_do_not_build_html_strings_by_hand(): void
    {
        foreach ($this->viewFiles() as $file) {
            $this->assertStringNotContainsString('HtmlString', (string) file_get_contents($file), $this->relative($file));
        }
    }

    public function test_public_views_have_no_inline_event_handlers(): void
    {
        $files = array_filter($this->viewFiles(), fn (string $file): bool => str_contains($this->relative($file), 'public/')
            || str_contains($this->relative($file), 'auth/')
            || str_contains($this->relative($file), 'patient/')
            || str_contains($this->relative($file), 'pdf/')
            || str_contains($this->relative($file), 'components/layouts/')
            || str_contains($this->relative($file), 'components/doctor-card')
            || str_contains($this->relative($file), 'components/schedule-table')
            || str_contains($this->relative($file), 'components/availability-badge')
            || str_contains($this->relative($file), 'components/map-embed')
            || str_contains($this->relative($file), 'components/open-graph'));

        $this->assertNotEmpty($files);

        foreach ($files as $file) {
            $this->assertSame(0, preg_match('/\son[a-z]{3,}\s*=\s*["\']/i', (string) file_get_contents($file)), $this->relative($file));
        }
    }

    public function test_app_script_wires_the_gallery_modal_without_a_second_error_listener(): void
    {
        $app = (string) file_get_contents(base_path('resources/js/app.js'));

        $this->assertStringContainsString('[data-gallery-modal]', $app);
        $this->assertStringContainsString('initGalleryModals();', $app);
        $this->assertStringContainsString('data-gallery-index', $app);
        $this->assertSame(1, preg_match_all("/addEventListener\\(\\s*'error'/", $app));
    }

    /** @return list<string> */
    private function phase6Views(): array
    {
        return array_values(array_filter($this->viewFiles(), fn (string $file): bool => str_contains($this->relative($file), 'patient/appointments/')
            || str_contains($this->relative($file), 'pdf/')));
    }

    public function test_phase_6_views_have_no_inline_script_and_embed_the_qr_only_as_an_image(): void
    {
        $files = $this->phase6Views();

        $this->assertNotEmpty($files);

        foreach ($files as $file) {
            $source = (string) file_get_contents($file);
            $name = $this->relative($file);

            $this->assertStringNotContainsString('<script', $source, $name);
            $this->assertStringNotContainsString('javascript:', $source, $name);
            $this->assertStringNotContainsString('{!!', $source, $name);

            // View yang menyebut QR hanya boleh menanamkannya sebagai gambar data URI, bukan markup SVG mentah.
            if (preg_match('/qr/i', $source) === 1) {
                $this->assertStringContainsString('<img', $source, $name);
                $this->assertStringNotContainsString('<svg', $source, $name);
            }
        }
    }

    public function test_app_script_wires_print_and_payment_toggle_with_delegated_listeners_without_a_second_error_listener(): void
    {
        $app = (string) file_get_contents(base_path('resources/js/app.js'));

        $this->assertStringContainsString('[data-print]', $app);
        $this->assertStringContainsString('window.print()', $app);
        $this->assertStringContainsString('[data-payment-toggle]', $app);
        $this->assertSame(1, preg_match_all("/addEventListener\\(\\s*'error'/", $app));
    }

    /** @return list<string> */
    private function phase7aViews(): array
    {
        return array_values(array_filter($this->viewFiles(), fn (string $file): bool => str_contains($this->relative($file), 'admin/patients/')
            || str_contains($this->relative($file), 'admin/appointments/')
            || str_contains($this->relative($file), 'admin/queue/')
            || str_contains($this->relative($file), 'partials/appointment-proof')));
    }

    /** SEK-15 (statis): view admin 7a tanpa `<script`, `javascript:`, atribut event inline, atau `{!!`; QR bukti hanya sebagai `<img>`. */
    public function test_phase_7a_views_have_no_inline_script_event_handler_javascript_url_or_raw_output(): void
    {
        $files = $this->phase7aViews();

        // patients 7 + appointments 8 + queue 1 + partial bukti 1 (kerangka D).
        $this->assertGreaterThanOrEqual(17, count($files));

        foreach ($files as $file) {
            $source = (string) file_get_contents($file);
            $name = $this->relative($file);

            $this->assertStringNotContainsString('<script', $source, $name);
            $this->assertStringNotContainsString('javascript:', $source, $name);
            $this->assertStringNotContainsString('{!!', $source, $name);
            $this->assertSame(0, preg_match('/\son[a-z]{3,}\s*=\s*["\']/i', $source), $name);

            if (preg_match('/qr/i', $source) === 1) {
                $this->assertStringContainsString('<img', $source, $name);
                $this->assertStringNotContainsString('<svg', $source, $name);
            }
        }
    }

    public function test_gallery_carousel_instance_is_created_eagerly_with_keyboard_support(): void
    {
        $app = (string) file_get_contents(base_path('resources/js/app.js'));

        $start = strpos($app, 'function initGalleryModals()');
        $end = strpos($app, 'function init()');
        $this->assertNotFalse($start);
        $body = substr($app, $start, $end - $start);

        $this->assertMatchesRegularExpression('/Carousel\.getOrCreateInstance\(\s*carousel\s*,\s*\{[^}]*keyboard:\s*true/s', $body);
        $this->assertMatchesRegularExpression('/ride:\s*false/', $body);
        $this->assertMatchesRegularExpression('/interval:\s*false/', $body);
    }
}
