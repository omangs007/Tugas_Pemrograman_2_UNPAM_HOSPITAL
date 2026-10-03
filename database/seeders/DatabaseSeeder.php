<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seeder inti. Seeder pengaturan ditambahkan di Task 7 dan guard demo di Task 18
     * (DemoSeeder hanya dijalankan di lingkungan local/testing; blueprint bagian 13).
     */
    public function run(): void
    {
        $this->call(RolePermissionSeeder::class);
    }
}
