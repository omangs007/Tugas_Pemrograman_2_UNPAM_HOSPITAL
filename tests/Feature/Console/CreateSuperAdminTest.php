<?php

namespace Tests\Feature\Console;

use App\Enums\AccountType;
use App\Enums\ActorType;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

class CreateSuperAdminTest extends TestCase
{
    use InteractsWithStaff;
    use RefreshDatabase;

    public function test_interactive_run_creates_super_admin_that_must_change_password(): void
    {
        $this->seedRoles();

        $this->artisan('app:create-super-admin')
            ->expectsQuestion('Nama lengkap', 'Budi Santoso')
            ->expectsQuestion('Email', '  Budi.Santoso@UnpamHospital.test ')
            ->expectsQuestion('Password', 'Rahasia123')
            ->expectsQuestion('Konfirmasi password', 'Rahasia123')
            ->expectsOutputToContain('Akun Super Admin dibuat: budi.santoso@unpamhospital.test')
            ->doesntExpectOutputToContain('Rahasia123')
            ->assertExitCode(0);

        $user = User::query()->where('email', 'budi.santoso@unpamhospital.test')->sole();

        $this->assertSame('Budi Santoso', $user->name);
        $this->assertSame(AccountType::Staff, $user->account_type);
        $this->assertTrue($user->is_active);
        $this->assertTrue($user->must_change_password);
        $this->assertSame(['super-admin'], $user->roles()->pluck('name')->all());
        $this->assertTrue(Hash::check('Rahasia123', $user->password));
        $this->assertStringStartsWith('$2y$', $user->password);
    }

    public function test_options_for_name_and_email_still_prompt_for_password_twice(): void
    {
        $this->seedRoles();

        $this->artisan('app:create-super-admin', ['--name' => 'Dewi Lestari', '--email' => 'dewi@unpamhospital.test'])
            ->expectsQuestion('Password', 'Rahasia123')
            ->expectsQuestion('Konfirmasi password', 'Rahasia123')
            ->expectsOutputToContain('Akun Super Admin dibuat: dewi@unpamhospital.test')
            ->assertExitCode(0);

        $this->assertTrue(User::query()->where('email', 'dewi@unpamhospital.test')->exists());
    }

    public function test_non_interactive_run_with_all_options_works_and_warns_about_shell_history(): void
    {
        $this->seedRoles();

        $this->artisan('app:create-super-admin', [
            '--name' => 'Sari Dewi',
            '--email' => 'sari@unpamhospital.test',
            '--password' => 'Rahasia123',
            '--no-interaction' => true,
        ])
            ->expectsOutputToContain('terekam di riwayat shell')
            ->expectsOutputToContain('Akun Super Admin dibuat: sari@unpamhospital.test')
            ->assertExitCode(0);

        $this->assertSame(1, User::query()->count());
    }

    public function test_non_interactive_run_without_all_options_fails_before_creating_anything(): void
    {
        $this->seedRoles();

        $this->artisan('app:create-super-admin', ['--name' => 'Sari Dewi', '--no-interaction' => true])
            ->expectsOutputToContain('Mode non-interaktif membutuhkan opsi --name, --email, dan --password.')
            ->assertExitCode(1);

        $this->assertSame(0, User::query()->count());
    }

    public function test_duplicate_email_is_rejected_case_insensitively(): void
    {
        $this->seedRoles();
        User::factory()->create(['email' => 'ada@unpamhospital.test']);

        $this->artisan('app:create-super-admin', [
            '--name' => 'Orang Lain',
            '--email' => 'ADA@unpamhospital.test',
            '--password' => 'Rahasia123',
            '--no-interaction' => true,
        ])
            ->expectsOutputToContain('Email ini sudah terdaftar. Gunakan email lain.')
            ->assertExitCode(1);

        $this->assertSame(1, User::query()->count());
    }

    #[DataProvider('weakPasswords')]
    public function test_weak_password_is_rejected(string $password, string $message): void
    {
        $this->seedRoles();

        $this->artisan('app:create-super-admin', [
            '--name' => 'Eka Putri',
            '--email' => 'eka@unpamhospital.test',
            '--password' => $password,
            '--no-interaction' => true,
        ])
            ->expectsOutputToContain($message)
            ->assertExitCode(1);

        $this->assertSame(0, User::query()->count());
    }

    /** @return array<string, array{string, string}> */
    public static function weakPasswords(): array
    {
        return [
            'tanpa angka' => ['abcdefgh', 'Password harus mengandung angka.'],
            'tanpa huruf' => ['12345678', 'Password harus mengandung huruf.'],
            'terlalu pendek' => ['a1b2c3', 'Password minimal 8 karakter.'],
            'terlalu panjang' => ['a1'.str_repeat('x', 63), 'Password maksimal 64 karakter.'],
        ];
    }

    public function test_password_confirmation_mismatch_aborts(): void
    {
        $this->seedRoles();

        $this->artisan('app:create-super-admin')
            ->expectsQuestion('Nama lengkap', 'Gita Permata')
            ->expectsQuestion('Email', 'gita@unpamhospital.test')
            ->expectsQuestion('Password', 'Rahasia123')
            ->expectsQuestion('Konfirmasi password', 'Berbeda123')
            ->expectsOutputToContain('Konfirmasi password tidak cocok.')
            ->assertExitCode(1);

        $this->assertSame(0, User::query()->count());
    }

    public function test_missing_super_admin_role_aborts_with_instruction(): void
    {
        $this->artisan('app:create-super-admin', [
            '--name' => 'Hadi Pranoto',
            '--email' => 'hadi@unpamhospital.test',
            '--password' => 'Rahasia123',
            '--no-interaction' => true,
        ])
            ->expectsOutputToContain('Peran super-admin belum ada. Jalankan: php artisan db:seed --class=RolePermissionSeeder')
            ->assertExitCode(1);

        $this->assertSame(0, User::query()->count());
    }

    public function test_it_writes_staff_created_audit_by_the_system_actor_without_the_password(): void
    {
        $this->seedRoles();

        $this->artisan('app:create-super-admin', [
            '--name' => 'Indra Wijaya',
            '--email' => 'indra@unpamhospital.test',
            '--password' => 'Rahasia123',
            '--no-interaction' => true,
        ])->assertExitCode(0);

        $user = User::query()->where('email', 'indra@unpamhospital.test')->sole();
        $log = AuditLog::query()->where('action', 'staff.created')->sole();

        $this->assertSame(ActorType::System, $log->actor_type);
        $this->assertNull($log->user_id);
        $this->assertSame((new User)->getMorphClass(), $log->auditable_type);
        $this->assertSame($user->id, (int) $log->auditable_id);
        $this->assertNull($log->old_values);
        $this->assertEquals([
            'name' => 'Indra Wijaya',
            'email' => 'indra@unpamhospital.test',
            'role' => 'super-admin',
        ], $log->new_values);
        $this->assertStringNotContainsString('Rahasia123', DB::table('audit_logs')->get()->toJson());
    }

    public function test_user_is_not_created_when_the_audit_row_cannot_be_written(): void
    {
        $this->seedRoles();
        AuditLog::creating(fn () => throw new RuntimeException('Gagal menulis audit.'));

        $thrown = null;

        try {
            Artisan::call('app:create-super-admin', [
                '--name' => 'Joko Susilo',
                '--email' => 'joko@unpamhospital.test',
                '--password' => 'Rahasia123',
                '--no-interaction' => true,
            ]);
        } catch (RuntimeException $e) {
            $thrown = $e;
        }

        $this->assertNotNull($thrown, 'Galat audit tidak boleh ditelan.');
        $this->assertSame(0, User::query()->count());
    }
}
