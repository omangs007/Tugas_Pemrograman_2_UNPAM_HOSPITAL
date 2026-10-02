<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class FrontendManifestTest extends TestCase
{
    private function path(string $file): string
    {
        return dirname(__DIR__, 2).'/'.$file;
    }

    public function test_package_json_uses_bootstrap_stack_without_tailwind(): void
    {
        $package = json_decode(file_get_contents($this->path('package.json')), true, flags: JSON_THROW_ON_ERROR);
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

    public function test_vite_builds_public_and_admin_entries(): void
    {
        $config = file_get_contents($this->path('vite.config.js'));

        foreach (['resources/scss/app.scss', 'resources/scss/admin.scss', 'resources/js/app.js', 'resources/js/admin.js'] as $entry) {
            $this->assertStringContainsString("'{$entry}'", $config);
            $this->assertFileExists($this->path($entry));
        }

        $this->assertStringNotContainsString('tailwind', $config);
        $this->assertFileDoesNotExist($this->path('resources/css/app.css'));
        $this->assertFileDoesNotExist($this->path('resources/js/bootstrap.js'));
    }
}
