<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ComposerManifestTest extends TestCase
{
    /** @return array<string, mixed> */
    private function json(string $file): array
    {
        return json_decode(file_get_contents(dirname(__DIR__, 2).'/'.$file), true, flags: JSON_THROW_ON_ERROR);
    }

    private function lockedVersion(string $package): ?string
    {
        $lock = $this->json('composer.lock');

        foreach (array_merge($lock['packages'], $lock['packages-dev']) as $entry) {
            if ($entry['name'] === $package) {
                return ltrim($entry['version'], 'v');
            }
        }

        return null;
    }

    public function test_platform_php_is_pinned_to_xampp_runtime(): void
    {
        $this->assertSame('8.2.12', $this->json('composer.json')['config']['platform']['php'] ?? null);
    }

    public function test_required_packages_resolve_to_expected_major_versions(): void
    {
        $expected = [
            'laravel/framework' => '12',
            'spatie/laravel-permission' => '6',
            'intervention/image' => '3',
            'mews/purifier' => '3',
            'barryvdh/laravel-dompdf' => '3',
            'chillerlan/php-qrcode' => '6',
            'phpunit/phpunit' => '11',
        ];

        foreach ($expected as $package => $major) {
            $version = $this->lockedVersion($package);

            $this->assertNotNull($version, "{$package} belum terpasang");
            $this->assertStringStartsWith($major.'.', $version, "{$package} harus versi {$major}.x, terpasang {$version}");
        }
    }

    public function test_pail_is_removed_because_windows_has_no_pcntl(): void
    {
        $composer = $this->json('composer.json');

        $this->assertArrayNotHasKey('laravel/pail', $composer['require-dev'] ?? []);
        $this->assertStringNotContainsString('pail', implode(' ', (array) ($composer['scripts']['dev'] ?? [])));
        $this->assertNull($this->lockedVersion('laravel/pail'));
    }
}
