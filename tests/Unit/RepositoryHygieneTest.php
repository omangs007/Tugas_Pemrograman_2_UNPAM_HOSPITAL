<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class RepositoryHygieneTest extends TestCase
{
    private function path(string $file): string
    {
        return dirname(__DIR__, 2).'/'.$file;
    }

    public function test_secrets_and_generated_folders_are_git_ignored(): void
    {
        $lines = array_map('trim', file($this->path('.gitignore')));

        foreach (['.env', '.env.testing', '/backups', '/vendor', '/node_modules', '/public/storage', '/public/build'] as $entry) {
            $this->assertContains($entry, $lines, "{$entry} harus ada di .gitignore");
        }
    }

    public function test_example_env_files_contain_no_secrets(): void
    {
        foreach (['.env.example', '.env.testing.example'] as $file) {
            $content = file_get_contents($this->path($file));

            $this->assertMatchesRegularExpression('/^APP_KEY=\r?$/m', $content, "{$file}: APP_KEY harus kosong");
            $this->assertMatchesRegularExpression('/^DB_PASSWORD=\r?$/m', $content, "{$file}: DB_PASSWORD harus kosong");
            $this->assertMatchesRegularExpression('/^DB_CONNECTION=mariadb\r?$/m', $content);
        }
    }
}
