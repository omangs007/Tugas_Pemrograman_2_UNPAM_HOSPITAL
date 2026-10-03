<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('DemoSeeder DILARANG di APP_ENV='.app()->environment().'. Data demo memuat akun berpassword umum.');
        }

        if (User::query()->where('email', 'superadmin@unpamhospital.test')->exists()) {
            $this->command?->warn('Data demo sudah ada. Jalankan: php artisan migrate:fresh --seed');

            return;
        }

        $this->command?->warn('MODE DEMO: akun & data contoh dibuat. Jangan gunakan di produksi.');
        config(['mail.default' => 'array']); // surat notifikasi seeding dibuang, bukan ditumpuk di log

        // Seeder data master (fase 2 dst.) ditambahkan ke daftar ini mengikuti urutan 13 §2.1.
        $this->call([DemoUserSeeder::class]);

        $password = (string) config('app.demo_password');

        $this->command?->table(
            ['Nama', 'Email', 'Peran', 'Password'],
            array_map(
                fn (array $account): array => [$account['name'], $account['email'], $account['role'], $password],
                DemoUserSeeder::ACCOUNTS,
            ),
        );
    }
}
