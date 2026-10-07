<?php

namespace Tests\Feature\Seeding;

use Database\Seeders\ClinicSeeder;
use Database\Seeders\ContactMessageSeeder;
use Database\Seeders\ContentSeeder;
use Database\Seeders\DoctorSeeder;
use Database\Seeders\FacilitySeeder;
use Database\Seeders\RoomCategorySeeder;
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
            'ContentSeeder' => [ContentSeeder::class],
            'RoomCategorySeeder' => [RoomCategorySeeder::class],
            'FacilitySeeder' => [FacilitySeeder::class],
            'ContactMessageSeeder' => [ContactMessageSeeder::class],
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
        $written = 0;

        foreach (['specialties', 'clinics', 'doctors', 'article_categories', 'articles', 'banners', 'galleries', 'faqs', 'insurance_partners', 'room_categories', 'facilities', 'contact_messages', 'media'] as $table) {
            $written += DB::table($table)->count();
        }

        $this->assertSame(0, $written);
    }
}
