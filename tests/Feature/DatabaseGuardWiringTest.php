<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

/**
 * Mengunci pemasangan pengaman di Tests\TestCase::setUpTraits().
 *
 * Sengaja tanpa trait database dan tanpa query: pengaman harus melempar
 * sebelum koneksi ke database non-uji dibuka.
 */
class DatabaseGuardWiringTest extends TestCase
{
    public function test_set_up_traits_refuses_a_non_test_database(): void
    {
        config(['database.connections.mariadb.database' => 'unpam_hospital']);
        DB::purge('mariadb');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('bukan database uji');

        $this->setUpTraits();
    }

    public function test_set_up_traits_refuses_a_db_url_pointing_at_a_non_test_database(): void
    {
        config(['database.connections.mariadb.url' => 'mysql://u:p@127.0.0.1:3306/unpam_hospital']);
        DB::purge('mariadb');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('bukan database uji');

        $this->setUpTraits();
    }
}
