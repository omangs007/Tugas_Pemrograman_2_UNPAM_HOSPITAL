<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class PublicStorageHardeningTest extends TestCase
{
    private function path(string $file): string
    {
        return dirname(__DIR__, 2).'/'.$file;
    }

    public function test_public_disk_denies_script_execution_and_directory_listing(): void
    {
        $file = $this->path('storage/app/public/.htaccess');

        $this->assertFileExists($file);

        $content = (string) file_get_contents($file);

        $this->assertStringContainsString('Options -Indexes', $content);
        $this->assertStringContainsString('Require all denied', $content);

        foreach (['php', 'phtml', 'phar'] as $extension) {
            $this->assertStringContainsString($extension, $content, "Ekstensi {$extension} harus ditolak");
        }
    }

    public function test_htaccess_is_tracked_by_git_while_uploads_stay_ignored(): void
    {
        $lines = array_map('trim', file($this->path('storage/app/public/.gitignore')));

        $this->assertContains('*', $lines);
        $this->assertContains('!.gitignore', $lines);
        $this->assertContains('!.htaccess', $lines);
    }
}
