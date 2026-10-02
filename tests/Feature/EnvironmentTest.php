<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EnvironmentTest extends TestCase
{
    public function test_default_connection_is_the_mariadb_test_database(): void
    {
        $this->assertSame('mariadb', config('database.default'));
        $this->assertSame('unpam_hospital_test', DB::connection()->getDatabaseName());
    }

    public function test_server_is_mariadb_10_4_or_newer(): void
    {
        $version = DB::selectOne('SELECT VERSION() AS v')->v;

        $this->assertStringContainsString('MariaDB', $version);
        $this->assertTrue(version_compare(explode('-', $version)[0], '10.4.0', '>='), "MariaDB terlalu lama: {$version}");
    }

    public function test_database_session_time_zone_matches_jakarta(): void
    {
        $this->assertSame('07:00:00', DB::selectOne('SELECT TIMEDIFF(NOW(), UTC_TIMESTAMP()) AS d')->d);
    }

    public function test_connection_uses_utf8mb4_unicode(): void
    {
        $row = DB::selectOne('SELECT @@character_set_connection AS cs, @@collation_connection AS co');

        $this->assertSame('utf8mb4', $row->cs);
        $this->assertSame('utf8mb4_unicode_ci', $row->co);
    }
}
