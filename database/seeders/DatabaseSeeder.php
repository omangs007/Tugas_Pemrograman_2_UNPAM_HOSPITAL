<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seeder inti (semua lingkungan): peran/izin lalu pengaturan. Halaman ditambahkan di fase 4a.
     * DemoSeeder hanya dijalankan di lingkungan local/testing (blueprint bagian 13).
     */
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            SettingsSeeder::class,
        ]);
    }
}
