<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\DatabaseGuard;

class DatabaseGuardTest extends TestCase
{
    public function test_accepts_database_name_ending_with_test(): void
    {
        DatabaseGuard::assertIsTestDatabase('unpam_hospital_test');

        $this->addToAssertionCount(1);
    }

    public function test_rejects_development_database(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("database 'unpam_hospital' bukan database uji");

        DatabaseGuard::assertIsTestDatabase('unpam_hospital');
    }

    public function test_rejects_empty_database_name(): void
    {
        $this->expectException(RuntimeException::class);

        DatabaseGuard::assertIsTestDatabase('');
    }
}
