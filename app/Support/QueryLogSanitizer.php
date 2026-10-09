<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\DeadlockException;
use Illuminate\Database\QueryException;
use Throwable;

/**
 * Pesan log galat basis data tanpa data pasien (F7A-R25, 00 E.6): hanya nama koneksi, SQLSTATE, kode driver, dan SQL
 * dengan penanda `?` (maks 1000 karakter). Bindings (keluhan, alamat, nomor BPJS), `getRawSql()`, pesan Laravel (yang
 * menyisipkan bindings), dan pesan driver (`Duplicate entry '…'`) sengaja tidak dipakai. `DeadlockException` tidak membawa
 * SQL sendiri dan pesannya menyalin pesan galat asli, sehingga rantai `getPrevious()` ditelusuri sampai `QueryException`.
 * Batasan: SQL yang sengaja menyisipkan nilai literal tidak dapat disanitasi; kode proyek selalu memakai bindings.
 */
final class QueryLogSanitizer
{
    /** Panjang SQL maksimum (karakter). */
    public const MAX_SQL_LENGTH = 1000;

    public static function message(QueryException|DeadlockException $e): string
    {
        $query = self::queryException($e);
        $info = $query?->errorInfo ?? [];

        return sprintf(
            'Galat basis data: koneksi=%s sqlstate=%s driver=%s sql=%s',
            $query === null ? '-' : self::part($query->getConnectionName()),
            self::part($info[0] ?? null),
            self::part($info[1] ?? null),
            $query === null ? '-' : self::part(mb_substr((string) $query->getSql(), 0, self::MAX_SQL_LENGTH)),
        );
    }

    private static function queryException(Throwable $source): ?QueryException
    {
        $current = $source;

        while ($current !== null) {
            if ($current instanceof QueryException) {
                return $current;
            }

            $current = $current->getPrevious();
        }

        return null;
    }

    private static function part(mixed $value): string
    {
        $text = is_scalar($value) ? (string) $value : '';

        return $text === '' ? '-' : $text;
    }
}
