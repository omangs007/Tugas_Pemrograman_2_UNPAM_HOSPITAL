<?php

namespace Tests\Feature\Seeding;

use App\Enums\AccountType;
use App\Models\User;
use Database\Seeders\DemoUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class DemoUserSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_three_staff_accounts_with_roles_and_the_demo_password(): void
    {
        $this->seed(DemoUserSeeder::class);

        $expected = [
            'superadmin@unpamhospital.test' => ['Super Admin Demo', 'super-admin'],
            'konten@unpamhospital.test' => ['Admin Konten Demo', 'content-admin'],
            'pendaftaran@unpamhospital.test' => ['Petugas Pendaftaran Demo', 'registration-officer'],
        ];

        $this->assertSame(3, User::query()->count());

        foreach ($expected as $email => [$name, $role]) {
            $user = User::query()->where('email', $email)->firstOrFail();

            $this->assertSame($name, $user->name);
            $this->assertSame(AccountType::Staff, $user->account_type);
            $this->assertTrue($user->is_active);
            $this->assertFalse($user->must_change_password);
            $this->assertSame([$role], $user->roles()->pluck('name')->all());
            $this->assertTrue(Hash::check('Demo12345', $user->password), "{$email} harus memakai password demo bawaan.");
        }
    }

    public function test_it_uses_the_configured_demo_password(): void
    {
        config(['app.demo_password' => 'Rahasia987']);

        $this->seed(DemoUserSeeder::class);

        $user = User::query()->where('email', 'konten@unpamhospital.test')->firstOrFail();
        $this->assertTrue(Hash::check('Rahasia987', $user->password));
        $this->assertFalse(Hash::check('Demo12345', $user->password));
    }

    public function test_running_twice_is_idempotent_and_warns_about_existing_accounts(): void
    {
        $this->seed(DemoUserSeeder::class);

        $this->artisan('db:seed', ['--class' => DemoUserSeeder::class, '--no-interaction' => true])
            ->expectsOutputToContain('sudah ada, dilewati')
            ->assertExitCode(0);

        $this->assertSame(3, User::query()->count());
    }

    public function test_it_refuses_to_run_in_production(): void
    {
        $thrown = null;
        $original = $this->app['env'];
        $this->app['env'] = 'production';

        try {
            $this->app->make(DemoUserSeeder::class)->run();
        } catch (RuntimeException $e) {
            $thrown = $e;
        } finally {
            $this->app['env'] = $original;
        }

        $this->assertInstanceOf(RuntimeException::class, $thrown);
        $this->assertStringContainsString('DemoUserSeeder DILARANG di APP_ENV=production', $thrown->getMessage());
        $this->assertSame(0, User::query()->count());
    }

    public function test_empty_demo_password_in_env_falls_back_to_the_default(): void
    {
        $this->assertSame('Demo12345', $this->demoPasswordConfigWithEnv(''));
        $this->assertSame('Rahasia987', $this->demoPasswordConfigWithEnv('Rahasia987'));
    }

    private function demoPasswordConfigWithEnv(string $value): string
    {
        $previous = [
            'env' => $_ENV['DEMO_PASSWORD'] ?? null,
            'server' => $_SERVER['DEMO_PASSWORD'] ?? null,
        ];

        $_ENV['DEMO_PASSWORD'] = $value;
        $_SERVER['DEMO_PASSWORD'] = $value;

        try {
            $config = require config_path('app.php');

            return $config['demo_password'];
        } finally {
            $this->restoreEnvValue($_ENV, $previous['env']);
            $this->restoreEnvValue($_SERVER, $previous['server']);
        }
    }

    private function restoreEnvValue(array &$bag, ?string $previous): void
    {
        if ($previous === null) {
            unset($bag['DEMO_PASSWORD']);

            return;
        }

        $bag['DEMO_PASSWORD'] = $previous;
    }
}
