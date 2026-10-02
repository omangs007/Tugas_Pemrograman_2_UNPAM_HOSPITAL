<?php

namespace Tests\Support;

use RuntimeException;

final class DatabaseGuard
{
    /**
     * Mencegah suite tes (mis. RefreshDatabase) menyentuh database selain database uji.
     */
    public static function assertIsTestDatabase(string $database): void
    {
        if ($database === '' || ! str_ends_with($database, '_test')) {
            throw new RuntimeException("Tes dihentikan: database '{$database}' bukan database uji (nama harus berakhiran _test).");
        }
    }
}
