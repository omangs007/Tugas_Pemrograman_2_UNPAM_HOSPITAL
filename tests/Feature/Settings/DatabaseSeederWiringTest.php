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
}
