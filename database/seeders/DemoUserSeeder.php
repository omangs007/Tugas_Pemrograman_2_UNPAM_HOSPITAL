<?php

namespace Database\Seeders;

use App\Enums\AccountType;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class DemoUserSeeder extends Seeder
{
    /**
     * Tiga akun staf demo (13 §3). Akun pasien demo ditambahkan di fase 5 (F1-R10).
     *
     * @var list<array{name: string, email: string, role: string}>
     */
    public const ACCOUNTS = [
        ['name' => 'Super Admin Demo', 'email' => 'superadmin@unpamhospital.test', 'role' => 'super-admin'],
        ['name' => 'Admin Konten Demo', 'email' => 'konten@unpamhospital.test', 'role' => 'content-admin'],
        ['name' => 'Petugas Pendaftaran Demo', 'email' => 'pendaftaran@unpamhospital.test', 'role' => 'registration-officer'],
    ];

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('DemoUserSeeder DILARANG di APP_ENV='.app()->environment().'. Akun demo berpassword umum.');
        }

        // Idempoten (firstOrCreate): membuat DemoUserSeeder dapat dijalankan sendiri pada database kosong.
        $this->call(RolePermissionSeeder::class, true);

        $password = (string) config('app.demo_password');

        foreach (self::ACCOUNTS as $account) {
            if (User::query()->where('email', $account['email'])->exists()) {
                $this->command?->warn("Akun demo {$account['email']} sudah ada, dilewati.");

                continue;
            }

            $user = new User;
            $user->forceFill([
                'name' => $account['name'],
                'email' => $account['email'],
                'password' => $password,
                'account_type' => AccountType::Staff,
                'is_active' => true,
                'must_change_password' => false,
            ])->save();

            $user->assignRole($account['role']);
        }
    }
}
