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

    public function test_example_env_files_follow_the_blueprint_reconciliation(): void
    {
        $env = file_get_contents($this->path('.env.example'));

        foreach ([
            '/^PHP_CLI_SERVER_WORKERS=4\r?$/m',
            '/^LOG_STACK=single\r?$/m',
            '/^MAIL_FROM_ADDRESS="info@unpamhospital\.test"\r?$/m',
            '/^DEMO_PASSWORD=\r?$/m',
        ] as $pattern) {
            $this->assertMatchesRegularExpression($pattern, $env, ".env.example harus cocok dengan {$pattern}");
        }

        $testing = file_get_contents($this->path('.env.testing.example'));

        $this->assertMatchesRegularExpression('/^LOG_CHANNEL=single\r?$/m', $testing);
    }
}
