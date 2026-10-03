<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Tests\Support\DatabaseGuard;

abstract class TestCase extends BaseTestCase
{
    /**
     * Semua tes berjalan tanpa manifest Vite (F1-R14); build aset diverifikasi terpisah
     * lewat `npm run build` dan FrontendManifestTest.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

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
