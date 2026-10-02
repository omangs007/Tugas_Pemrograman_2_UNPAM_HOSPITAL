<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Support\DatabaseGuard;

abstract class TestCase extends BaseTestCase
{
    /**
     * Dipanggil sebelum trait (mis. RefreshDatabase) menyentuh database,
     * sehingga suite tes tidak pernah berjalan pada database pengembangan.
     */
    protected function setUpTraits()
    {
        $connection = (string) config('database.default');

        DatabaseGuard::assertIsTestDatabase((string) config("database.connections.{$connection}.database"));

        return parent::setUpTraits();
    }
}
