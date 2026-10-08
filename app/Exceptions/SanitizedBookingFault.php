<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Database\QueryException;
use RuntimeException;
use Throwable;

/**
 * Pengecualian pelaporan untuk kegagalan sistem booking yang diterjemahkan menjadi BookingException (yang berada di
 * `dontReport`, sehingga kegagalannya tidak akan tercatat). Hanya memuat SQLSTATE, kode driver, nama constraint,
 * dan id sesi/profil: tanpa SQL, tanpa bindings, dan tanpa pesan driver asli (yang dapat memuat keluhan, nomor
 * BPJS, atau polis). Galat asli sengaja tidak dijadikan `previous`.
 */
final class SanitizedBookingFault extends RuntimeException
{
    /**
     * Mencatat kegagalan ke log lewat `report()` (bukan bernama `report` agar Handler tidak menganggapnya metode pelaporan kustom).
     *
     * @param  string  $event  nama kejadian, mis. `SYSTEM_BUSY` atau `QUOTA_DRIFT`
     * @param  array{practice_session_id?: int|null, patient_profile_id?: int|null}  $ids
     */
    public static function capture(string $event, ?Throwable $source = null, array $ids = []): void
    {
        $query = self::queryException($source);
        $info = $query?->errorInfo ?? [];
        $driverMessage = (string) ($info[2] ?? '');

        $constraint = preg_match('/(?:for key|CONSTRAINT)\s+[`\']([^`\']+)[`\']/i', $driverMessage, $matches) === 1
            ? $matches[1]
            : '-';

        report(new self(sprintf(
            'Kegagalan sistem booking %s: jenis=%s sqlstate=%s driver=%s constraint=%s sesi=%s profil=%s.',
            $event,
            $source === null ? '-' : class_basename($source),
            (string) ($info[0] ?? '-'),
            (string) ($info[1] ?? '-'),
            $constraint,
            (string) ($ids['practice_session_id'] ?? '-'),
            (string) ($ids['patient_profile_id'] ?? '-'),
        )));
    }

    private static function queryException(?Throwable $source): ?QueryException
    {
        while ($source !== null) {
            if ($source instanceof QueryException) {
                return $source;
            }

            $source = $source->getPrevious();
        }

        return null;
    }
}
