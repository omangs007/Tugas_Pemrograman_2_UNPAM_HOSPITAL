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
            || str_contains($this->relative($file), 'components/doctor-card')
            || str_contains($this->relative($file), 'components/schedule-table')
            || str_contains($this->relative($file), 'components/availability-badge')
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
