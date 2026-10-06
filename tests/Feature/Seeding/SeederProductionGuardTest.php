<?php

namespace Tests\Feature\Seeding;

use App\Models\User;
use App\Models\WebsiteSetting;
use Closure;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Database\Seeders\DemoUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SeederProductionGuardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // DemoSeeder membuat 57 gambar contoh (32 fase 2 + 25 konten fase 4a): jangan menulis ke storage nyata.
        Storage::fake('public');
    }

    public function test_database_seeder_seeds_only_core_data_and_warns_in_production(): void
    {
        $this->inEnvironment('production', function (): void {
            $this->artisan('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true])
                ->expectsOutputToContain('DemoSeeder dilewati (APP_ENV=production). Buat Super Admin dengan: php artisan app:create-super-admin')
                ->assertExitCode(0);
        });

        $this->assertSame(3, Role::query()->count());
        $this->assertSame(19, Permission::query()->count());
        $this->assertSame(19, WebsiteSetting::query()->count());
        $this->assertSame(15, DB::table('pages')->count(), 'PageSeeder termasuk seeder inti (13 §2.3).');
        $this->assertSame(0, DB::table('specialties')->count() + DB::table('clinics')->count() + DB::table('doctors')->count() + DB::table('media')->count());

        foreach (['article_categories', 'articles', 'banners', 'galleries', 'faqs', 'insurance_partners'] as $table) {
            $this->assertSame(0, DB::table($table)->count(), $table);
        }
        $this->assertSame(0, User::query()->count());
        $this->assertSame(0, User::query()->where('email', 'like', '%.test')->count());
    }

    public function test_demo_seeder_throws_runtime_exception_in_production_even_when_called_directly(): void
    {
        $thrown = null;

        $this->inEnvironment('production', function () use (&$thrown): void {
            try {
                $this->app->make(DemoSeeder::class)->run();
            } catch (RuntimeException $e) {
                $thrown = $e;
            }
        });

        $this->assertInstanceOf(RuntimeException::class, $thrown);
        $this->assertStringContainsString('DemoSeeder DILARANG di APP_ENV=production', $thrown->getMessage());
        $this->assertSame(0, User::query()->count());
    }

    public function test_db_seed_class_demo_seeder_fails_in_production(): void
    {
        $thrown = null;

        $this->inEnvironment('production', function () use (&$thrown): void {
            try {
                $this->artisan('db:seed', ['--class' => DemoSeeder::class, '--force' => true])->run();
            } catch (RuntimeException $e) {
                $thrown = $e;
            }
        });

        $this->assertInstanceOf(RuntimeException::class, $thrown);
        $this->assertSame(0, User::query()->where('email', 'like', '%.test')->count());
    }

    public function test_database_seeder_runs_the_demo_seeder_in_testing_environment(): void
    {
        $this->artisan('db:seed', ['--class' => DatabaseSeeder::class, '--no-interaction' => true])
            ->expectsOutputToContain('MODE DEMO')
            ->assertExitCode(0);

        $this->assertSame(3, User::query()->count());
        $this->assertTrue(User::query()->where('email', 'superadmin@unpamhospital.test')->exists());
        $this->assertSame(3, Role::query()->count());
    }

    public function test_demo_seeder_warns_and_skips_when_demo_data_already_exists(): void
    {
        $this->seed(DemoSeeder::class);

        $this->artisan('db:seed', ['--class' => DemoSeeder::class, '--no-interaction' => true])
            ->expectsOutputToContain('Data demo sudah ada')
            ->assertExitCode(0);

        $this->assertSame(3, User::query()->count());
    }

    public function test_demo_user_seeder_throws_in_production_and_creates_no_users(): void
    {
        $this->assertDemoSeederRejected(DemoUserSeeder::class, 'production');
    }

    public function test_demo_user_seeder_is_rejected_outside_the_local_and_testing_whitelist(): void
    {
        $this->assertDemoSeederRejected(DemoUserSeeder::class, 'staging');
    }

    public function test_demo_seeder_is_rejected_in_staging(): void
    {
        $this->assertDemoSeederRejected(DemoSeeder::class, 'staging');
    }

    /**
     * @param  class-string  $seeder
     */
    private function assertDemoSeederRejected(string $seeder, string $environment): void
    {
        $thrown = null;

        $this->inEnvironment($environment, function () use (&$thrown, $seeder): void {
            try {
                $this->app->make($seeder)->run();
            } catch (RuntimeException $e) {
                $thrown = $e;
            }
        });

        $this->assertInstanceOf(RuntimeException::class, $thrown);
        $this->assertStringContainsString('DILARANG di APP_ENV='.$environment, $thrown->getMessage());
        $this->assertSame(0, User::query()->count());
    }

    private function inEnvironment(string $environment, Closure $callback): void
    {
        $original = $this->app['env'];
        $this->app['env'] = $environment;

        try {
            $callback();
        } finally {
            $this->app['env'] = $original;
        }
    }
}
