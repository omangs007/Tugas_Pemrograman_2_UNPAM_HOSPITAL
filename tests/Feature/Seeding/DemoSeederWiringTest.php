<?php

namespace Tests\Feature\Seeding;

use Tests\TestCase;

class DemoSeederWiringTest extends TestCase
{
    public function test_demo_seeder_runs_master_seeders_in_the_13_section_2_1_order(): void
    {
        $source = (string) file_get_contents(database_path('seeders/DemoSeeder.php'));
        $positions = array_map(
            fn (string $class): int|false => strpos($source, $class.'::class'),
            ['DemoUserSeeder', 'SpecialtySeeder', 'ClinicSeeder', 'DoctorSeeder', 'DoctorScheduleSeeder', 'ScheduleExceptionSeeder', 'RoomCategorySeeder', 'FacilitySeeder', 'ContentSeeder', 'ContactMessageSeeder'],
        );

        $this->assertNotContains(false, $positions);
        $sorted = $positions;
        sort($sorted);
        $this->assertSame($sorted, $positions);
    }

    public function test_database_seeder_does_not_call_master_seeders_directly(): void
    {
        $source = (string) file_get_contents(database_path('seeders/DatabaseSeeder.php'));

        foreach (['SpecialtySeeder', 'ClinicSeeder', 'DoctorSeeder', 'DoctorScheduleSeeder', 'ScheduleExceptionSeeder', 'RoomCategorySeeder', 'FacilitySeeder', 'ContentSeeder', 'ContactMessageSeeder'] as $class) {
            $this->assertStringNotContainsString($class, $source);
        }
    }
}
