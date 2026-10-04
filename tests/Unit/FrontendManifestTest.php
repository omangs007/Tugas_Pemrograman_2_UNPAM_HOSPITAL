<?php

namespace Tests\Unit;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class FrontendManifestTest extends TestCase
{
    private function path(string $file): string
    {
        return dirname(__DIR__, 2).'/'.$file;
    }

    private function read(string $file): string
    {
        return file_get_contents($this->path($file));
    }

    /** @return list<string> */
    private function filesUnder(string $directory, string $suffix): array
    {
        $found = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->path($directory), FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), $suffix)) {
                $found[] = $file->getPathname();
            }
        }

        return $found;
    }

    /** @param list<string> $names */
    private function assertImportOrder(string $source, array $names): void
    {
        $previous = -1;

        foreach ($names as $name) {
            $position = strpos($source, '@import "'.$name.'";');
            $this->assertNotFalse($position, "@import \"{$name}\"; tidak ditemukan");
            $this->assertGreaterThan($previous, $position, "@import \"{$name}\"; berada di urutan yang salah");
            $previous = $position;
        }
    }

    public function test_package_json_uses_bootstrap_stack_without_tailwind(): void
    {
        $package = json_decode($this->read('package.json'), true, flags: JSON_THROW_ON_ERROR);
        $all = array_merge($package['dependencies'] ?? [], $package['devDependencies'] ?? []);

        foreach (['bootstrap', '@popperjs/core', 'sass', 'bootstrap-icons', 'chart.js', 'quill', '@fontsource/plus-jakarta-sans', 'vite', 'laravel-vite-plugin', 'concurrently'] as $name) {
            $this->assertArrayHasKey($name, $all, "{$name} belum dipasang");
        }

        foreach (['tailwindcss', '@tailwindcss/vite', 'axios'] as $name) {
            $this->assertArrayNotHasKey($name, $all, "{$name} harus dihapus");
        }

        $this->assertStringStartsWith('^5.', $all['bootstrap']);
        $this->assertStringStartsWith('^4.', $all['chart.js']);
        $this->assertStringStartsWith('^2.', $all['quill']);
    }

    public function test_vite_builds_three_entries_and_admin_scss_is_not_an_entry(): void
    {
        $config = $this->read('vite.config.js');

        foreach (['resources/scss/app.scss', 'resources/js/app.js', 'resources/js/admin.js'] as $entry) {
            $this->assertStringContainsString("'{$entry}'", $config);
            $this->assertFileExists($this->path($entry));
        }

        $this->assertStringNotContainsString("'resources/scss/admin.scss'", $config, 'admin.scss masuk lewat admin.js (F1-R11), bukan entri sendiri');
        $this->assertStringContainsString('quietDeps: true', $config);

        foreach (['import', 'mixed-decls', 'color-functions', 'global-builtin'] as $deprecation) {
            $this->assertStringContainsString("'{$deprecation}'", $config);
        }

        $this->assertStringNotContainsString('tailwind', $config);
        $this->assertFileDoesNotExist($this->path('resources/css/app.css'));
        $this->assertFileDoesNotExist($this->path('resources/js/bootstrap.js'));
    }

    public function test_admin_entry_imports_shared_script_and_admin_styles(): void
    {
        $admin = $this->read('resources/js/admin.js');

        $this->assertMatchesRegularExpression("#import\\s+'\\./app(\\.js)?';#", $admin);
        $this->assertStringContainsString("import '../scss/admin.scss';", $admin);
        $this->assertFileExists($this->path('resources/scss/admin.scss'));
        $this->assertStringContainsString('[data-confirm-modal]', $admin);
        $this->assertStringContainsString('[data-table]', $admin);
    }

    public function test_app_script_bundles_fonts_icons_bootstrap_and_shared_behaviours(): void
    {
        $app = $this->read('resources/js/app.js');

        foreach (['400', '500', '600', '700'] as $weight) {
            $this->assertStringContainsString("import '@fontsource/plus-jakarta-sans/{$weight}.css';", $app);
        }

        $this->assertStringContainsString("import 'bootstrap-icons/font/bootstrap-icons.css';", $app);
        $this->assertStringContainsString('window.bootstrap = bootstrap;', $app);

        foreach (['[data-loading-button]', '[data-errors-summary]', '[data-image-input]', "import('./image-preview.js')", 'pageshow'] as $needle) {
            $this->assertStringContainsString($needle, $app);
        }

        $this->assertFileExists($this->path('resources/js/image-preview.js'));
    }

    public function test_image_fallback_uses_one_delegated_capture_listener(): void
    {
        $app = $this->read('resources/js/app.js');

        $this->assertMatchesRegularExpression("/document\\.addEventListener\\(\\s*'error'\\s*,\\s*[A-Za-z_\$][\\w\$]*\\s*,\\s*true\\s*\\)/", $app);
        $this->assertStringContainsString('img.complete', $app);
        $this->assertStringContainsString('img.naturalWidth === 0', $app);
        $this->assertStringContainsString('DOMContentLoaded', $app);

        $listeners = 0;

        foreach ($this->filesUnder('resources/js', '.js') as $file) {
            $listeners += preg_match_all("/addEventListener\\(\\s*'error'/", file_get_contents($file));
        }

        $this->assertSame(1, $listeners, 'Harus ada tepat satu listener error terdelegasi di seluruh JS');
    }

    public function test_image_preview_uses_data_url_not_blob_url(): void
    {
        $preview = $this->read('resources/js/image-preview.js');

        $this->assertStringNotContainsString('createObjectURL', $preview, "CSP produksi img-src 'self' data: tidak mengizinkan blob:, pratinjau harus memakai data URL");
        $this->assertStringContainsString('readAsDataURL', $preview);
    }

    public function test_no_inline_error_handler_in_scripts_or_views(): void
    {
        $files = array_merge(
            $this->filesUnder('resources/js', '.js'),
            $this->filesUnder('resources/views', '.blade.php'),
        );

        $this->assertNotEmpty($files);

        foreach ($files as $file) {
            $this->assertStringNotContainsString('onerror', strtolower(file_get_contents($file)), basename($file).' memuat atribut event inline (CSP produksi memblokirnya)');
        }
    }

    public function test_scss_entries_follow_the_documented_import_order(): void
    {
        $this->assertImportOrder($this->read('resources/scss/app.scss'), ['variables', 'bootstrap-theme', 'shared', 'public', 'print']);
        $this->assertImportOrder($this->read('resources/scss/admin.scss'), ['variables', 'bootstrap-theme', 'shared', '_admin', 'print']);

        $this->assertStringNotContainsString('@import "public";', $this->read('resources/scss/admin.scss'));
        $this->assertStringNotContainsString('@import "_admin";', $this->read('resources/scss/app.scss'));
    }

    public function test_theme_tokens_follow_the_blueprint(): void
    {
        $variables = $this->read('resources/scss/_variables.scss');

        foreach (['#0A4D8C', '#0E7C74', '#13A89E', '#1E7D45', '#F3F7FB'] as $hex) {
            $this->assertStringContainsStringIgnoringCase($hex, $variables);
        }

        $this->assertStringContainsString("'Plus Jakarta Sans'", $variables);
        $this->assertMatchesRegularExpression('/\$font-size-base:\s*1rem/', $variables);
        $this->assertMatchesRegularExpression('/\$line-height-base:\s*1\.6/', $variables);
        $this->assertStringContainsString("'accent'", $variables);
        $this->assertDoesNotMatchRegularExpression('/^\$secondary\s*:/m', $variables, '$secondary dibiarkan abu-abu (00 D: cancelled = secondary)');
    }

    public function test_bootstrap_theme_merges_custom_colors_between_variables_and_maps(): void
    {
        $theme = $this->read('resources/scss/_bootstrap-theme.scss');
        $needles = [
            '@import "bootstrap/scss/functions";',
            '@import "bootstrap/scss/variables";',
            '$theme-colors: map-merge($theme-colors, $custom-theme-colors);',
            '@import "bootstrap/scss/maps";',
            '@import "bootstrap/scss/mixins";',
            '@import "bootstrap/scss/utilities/api";',
        ];

        $previous = -1;

        foreach ($needles as $needle) {
            $position = strpos($theme, $needle);
            $this->assertNotFalse($position, "{$needle} tidak ditemukan");
            $this->assertGreaterThan($previous, $position, "{$needle} berada di urutan yang salah");
            $previous = $position;
        }
    }

    public function test_admin_entry_loads_the_rich_text_editor_lazily(): void
    {
        $admin = $this->read('resources/js/admin.js');

        $this->assertStringContainsString('[data-rich-text]', $admin);
        $this->assertStringContainsString("import('./editor.js')", $admin);
        $this->assertDoesNotMatchRegularExpression("#^import\\s+['\"]\\./editor(\\.js)?['\"];#m", $admin, 'editor.js harus dimuat lazy, bukan impor statis');
        $this->assertFileExists($this->path('resources/js/editor.js'));
    }

    public function test_editor_script_uses_quill_snow_fetch_csrf_and_no_unsafe_apis(): void
    {
        $editor = $this->read('resources/js/editor.js');

        foreach ([
            "import Quill from 'quill';",
            "import 'quill/dist/quill.snow.css';",
            'fetch(',
            'X-CSRF-TOKEN',
            'meta[name="csrf-token"]',
            'readAsDataURL',
            'getSemanticHTML',
            'data-rich-text',
            "addEventListener('submit'",
            'window.bootstrap',
        ] as $needle) {
            $this->assertStringContainsString($needle, $editor, "editor.js harus memuat {$needle}");
        }

        // CSP produksi: script-src 'self', img-src 'self' data: (tanpa blob:), tanpa penulisan HTML mentah.
        foreach (['createObjectURL', 'blob:', 'eval(', 'new Function', 'innerHTML', 'outerHTML', 'insertAdjacentHTML', 'document.write', 'onerror', 'prompt(', 'localStorage'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $editor, "editor.js tidak boleh memuat {$forbidden}");
        }
    }

    public function test_editor_script_gives_visible_feedback_initializes_safely_and_maps_upload_errors(): void
    {
        $editor = $this->read('resources/js/editor.js');

        foreach ([
            'data-rich-text-feedback',
            'Anda tidak memiliki izin untuk mengunggah gambar.',
            'Sesi Anda telah berakhir. Muat ulang halaman lalu coba lagi.',
            'Gambar gagal diunggah. Coba lagi.',
            'hide.bs.modal',
            "quill.root.setAttribute('aria-describedby'",
            "quill.root.setAttribute('aria-invalid'",
            "quill.root.setAttribute('aria-required'",
            'catch',
        ] as $needle) {
            $this->assertStringContainsString($needle, $editor, "editor.js harus memuat {$needle}");
        }

        $quill = strpos($editor, 'new Quill(');
        $hide = strpos($editor, 'textarea.hidden = true');

        $this->assertNotFalse($quill);
        $this->assertNotFalse($hide);
        $this->assertGreaterThan($quill, $hide, 'textarea hanya disembunyikan setelah Quill berhasil dibuat');
    }

    public function test_quill_snow_stylesheet_is_installed_and_admin_styles_cover_the_editor(): void
    {
        if (! is_dir($this->path('node_modules/quill'))) {
            $this->markTestSkipped('node_modules belum terpasang (jalankan npm ci).');
        }

        $this->assertFileExists($this->path('node_modules/quill/dist/quill.snow.css'));

        $admin = $this->read('resources/scss/_admin.scss');

        foreach (['.rich-text', '.ql-toolbar', '.ql-container', '.ql-tooltip', '.rich-text-preview'] as $needle) {
            $this->assertStringContainsString($needle, $admin);
        }
    }

    public function test_built_manifest_contains_the_lazy_editor_chunk_with_its_css(): void
    {
        $file = $this->path('public/build/manifest.json');

        if (! is_file($file)) {
            $this->markTestSkipped('Belum ada build Vite (public/build/manifest.json); jalankan npm run build.');
        }

        $manifest = json_decode($this->read('public/build/manifest.json'), true, flags: JSON_THROW_ON_ERROR);

        $this->assertArrayHasKey('resources/js/editor.js', $manifest, 'Build usang: jalankan npm run build.');
        $this->assertTrue($manifest['resources/js/editor.js']['isDynamicEntry'] ?? false, 'editor.js harus chunk dinamis (lazy)');
        $this->assertNotEmpty(preg_grep('/\\.css$/', $manifest['resources/js/editor.js']['css'] ?? []), 'CSS Quill snow harus ikut chunk editor');
        $this->assertContains('resources/js/editor.js', $manifest['resources/js/admin.js']['dynamicImports'] ?? []);
        $this->assertArrayNotHasKey('resources/js/editor.js', array_filter($manifest, fn (array $entry): bool => ($entry['isEntry'] ?? false) === true), 'editor.js bukan entri Vite');
    }

    public function test_shared_and_admin_styles_define_the_classes_components_rely_on(): void
    {
        $shared = $this->read('resources/scss/_shared.scss');

        foreach (['.table-cards', 'td[data-label]::before', '.skip-link', ':focus-visible', '2.75rem', '.auth-card', 'prefers-reduced-motion'] as $needle) {
            $this->assertStringContainsString($needle, $shared);
        }

        $admin = $this->read('resources/scss/_admin.scss');

        foreach (['.admin-sidebar', '260px', '$light', '$primary', 'media-breakpoint-down(lg)'] as $needle) {
            $this->assertStringContainsString($needle, $admin);
        }

        $this->assertStringContainsString('@media print', $this->read('resources/scss/_print.scss'));
    }
}
