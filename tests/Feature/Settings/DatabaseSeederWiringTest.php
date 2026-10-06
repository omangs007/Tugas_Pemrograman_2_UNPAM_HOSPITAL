<?php

namespace Tests\Feature\Settings;

use Tests\TestCase;

class DatabaseSeederWiringTest extends TestCase
{
    public function test_database_seeder_runs_the_role_and_settings_seeders_in_that_order(): void
    {
        $source = (string) file_get_contents(database_path('seeders/DatabaseSeeder.php'));

        $this->assertStringContainsString('RolePermissionSeeder::class', $source);
        $this->assertStringContainsString('SettingsSeeder::class', $source);
        $this->assertLessThan(
            strpos($source, 'SettingsSeeder::class'),
            strpos($source, 'RolePermissionSeeder::class'),
        );
    }

    public function test_database_seeder_runs_the_page_seeder_after_settings_in_every_environment(): void
    {
        $source = (string) file_get_contents(database_path('seeders/DatabaseSeeder.php'));

        $this->assertStringContainsString('PageSeeder::class', $source);
        $this->assertLessThan(
            strpos($source, 'PageSeeder::class'),
            strpos($source, 'SettingsSeeder::class'),
            'PageSeeder dibaca alamat dari pengaturan, jadi harus setelah SettingsSeeder.',
        );
        $this->assertLessThan(
            strpos($source, 'app()->environment'),
            strpos($source, 'PageSeeder::class'),
            'PageSeeder berjalan sebelum percabangan lingkungan demo.',
        );
        $this->assertStringNotContainsString('fase 4a.', $source, 'Komentar penanda "PageSeeder ditambahkan di fase 4a." dihapus.');
    }
}
