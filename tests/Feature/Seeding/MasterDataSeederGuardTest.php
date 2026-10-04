<?php

namespace Tests\Feature\Seeding;

use Database\Seeders\ClinicSeeder;
use Database\Seeders\DoctorSeeder;
use Database\Seeders\SpecialtySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

/**
 * SEED-02 untuk seeder master: hanya `local`/`testing`.
 */
class MasterDataSeederGuardTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{class-string}> */
    public static function seeders(): array
    {
        return [
            'SpecialtySeeder' => [SpecialtySeeder::class],
            'ClinicSeeder' => [ClinicSeeder::class],
            'DoctorSeeder' => [DoctorSeeder::class],
        ];
    }

    #[DataProvider('seeders')]
    public function test_seeder_throws_in_production_and_writes_nothing(string $seeder): void
    {
        $original = $this->app['env'];
        $this->app['env'] = 'production';
        $thrown = null;

        try {
            $this->app->make($seeder)->run();
        } catch (RuntimeException $e) {
            $thrown = $e;
        } finally {
            $this->app['env'] = $original;
        }

        $this->assertInstanceOf(RuntimeException::class, $thrown);
        $this->assertStringContainsString('DILARANG di APP_ENV=production', $thrown->getMessage());
        $this->assertSame(0, DB::table('specialties')->count() + DB::table('clinics')->count() + DB::table('doctors')->count());
    }
}
