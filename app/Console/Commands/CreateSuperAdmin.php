<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\AccountType;
use App\Models\User;
use App\Rules\PasswordRules;
use App\Services\Audit\AuditLogger;
use App\Support\Actor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class CreateSuperAdmin extends Command
{
    private const ROLE = 'super-admin';

    protected $signature = 'app:create-super-admin
        {--name= : Nama lengkap}
        {--email= : Alamat email untuk login}
        {--password= : Password (hindari: terekam di riwayat shell)}';

    protected $description = 'Membuat akun Super Admin (instalasi produksi pertama; tanpa seeder demo)';

    public function handle(AuditLogger $audit): int
    {
        if (! Role::query()->where('name', self::ROLE)->where('guard_name', 'web')->exists()) {
            $this->components->error('Peran super-admin belum ada. Jalankan: php artisan db:seed --class=RolePermissionSeeder');

            return self::FAILURE;
        }

        $name = $this->option('name');
        $email = $this->option('email');
        $password = $this->option('password');

        if (! $this->input->isInteractive() && (blank($name) || blank($email) || blank($password))) {
            $this->components->error('Mode non-interaktif membutuhkan opsi --name, --email, dan --password.');

            return self::FAILURE;
        }

        $passwordFromOption = filled($password);

        if ($passwordFromOption) {
            $this->components->warn('Password pada opsi --password terekam di riwayat shell. Gunakan prompt interaktif untuk lingkungan produksi.');
        }

        $name = trim((string) (filled($name) ? $name : $this->ask('Nama lengkap')));
        $email = Str::lower(trim((string) (filled($email) ? $email : $this->ask('Email'))));

        if (! $passwordFromOption) {
            $password = (string) $this->secret('Password');
            $confirmation = (string) $this->secret('Konfirmasi password');

            if ($password !== $confirmation) {
                $this->components->error('Konfirmasi password tidak cocok.');

                return self::FAILURE;
            }
        }

        $validator = Validator::make(
            ['name' => $name, 'email' => $email, 'password' => (string) $password],
            [
                'name' => ['required', 'string', 'min:3', 'max:100'],
                'email' => ['required', 'email:rfc', 'max:255', Rule::unique('users', 'email')],
                // Konfirmasi sudah diminta lewat prompt, jadi aturan 'confirmed' dibuang.
                'password' => array_values(array_filter(
                    PasswordRules::default(),
                    fn (mixed $rule): bool => $rule !== 'confirmed',
                )),
            ],
            $this->customMessages(),
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->components->error($message);
            }

            return self::FAILURE;
        }

        $user = DB::transaction(function () use ($audit, $name, $email, $password): User {
            $user = new User;
            $user->forceFill([
                'name' => $name,
                'email' => $email,
                'password' => (string) $password,
                'account_type' => AccountType::Staff,
                'is_active' => true,
                'must_change_password' => true,
            ])->save();

            $user->assignRole(self::ROLE);

            $audit->log(
                'staff.created',
                $user,
                null,
                ['name' => $user->name, 'email' => $user->email, 'role' => self::ROLE],
                "Akun Super Admin {$user->name} dibuat lewat perintah konsol.",
                Actor::system(),
            );

            return $user;
        });

        $this->components->info("Akun Super Admin dibuat: {$user->email}.");
        $this->line('Saat login pertama di /admin/login, Anda diminta mengganti password.');

        return self::SUCCESS;
    }

    /**
     * @return array<string, string>
     */
    private function customMessages(): array
    {
        return [
            'name.required' => 'Nama wajib diisi.',
            'name.min' => 'Nama minimal 3 karakter.',
            'name.max' => 'Nama maksimal 100 karakter.',
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.max' => 'Email maksimal 255 karakter.',
            'email.unique' => 'Email ini sudah terdaftar. Gunakan email lain.',
            'password.required' => 'Password wajib diisi.',
            'password.min' => 'Password minimal 8 karakter.',
            'password.max' => 'Password maksimal 64 karakter.',
            'password.letters' => 'Password harus mengandung huruf.',
            'password.numbers' => 'Password harus mengandung angka.',
        ];
    }
}
