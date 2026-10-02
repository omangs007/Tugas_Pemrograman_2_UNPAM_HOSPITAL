<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Tests\Support\DatabaseGuard;

abstract class TestCase extends BaseTestCase
{
    /**
     * Dipanggil sebelum trait (mis. RefreshDatabase) menyentuh database,
     * sehingga suite tes tidak pernah berjalan pada database pengembangan.
     * Yang diperiksa adalah nama database hasil resolusi koneksi (termasuk DB_URL),
     * bukan nilai mentah konfigurasi; membaca nama ini tidak membuka koneksi.
     */
    protected function setUpTraits()
    {
        DatabaseGuard::assertIsTestDatabase((string) DB::connection()->getDatabaseName());

        return parent::setUpTraits();
    }
}
