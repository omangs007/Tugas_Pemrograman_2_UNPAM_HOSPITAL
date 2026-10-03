<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seeder inti selalu berjalan (13 §2.3). PageSeeder ditambahkan di fase 4a.
     * DemoSeeder hanya untuk lingkungan local/testing; di produksi Super Admin dibuat
     * dengan: php artisan app:create-super-admin.
     */
    public function run(): void
    {
        $this->call([RolePermissionSeeder::class, SettingsSeeder::class]);

        if (app()->environment(['local', 'testing'])) {
            $this->call(DemoSeeder::class);

            return;
        }

        $this->command?->warn('DemoSeeder dilewati (APP_ENV='.app()->environment().'). Buat Super Admin dengan: php artisan app:create-super-admin');
    }
}
