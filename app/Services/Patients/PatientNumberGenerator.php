<?php

declare(strict_types=1);

namespace App\Services\Patients;

/**
 * Format nomor pasien (F5-R10; 06, 09, 14): "P" + id profil dengan lebar minimal 6 digit, mis. P000123.
 * Id lebih dari enam digit memperlebar nomor (P1000000), tidak dipotong.
 *
 * Murni dan tanpa DB. Id baru diketahui setelah insert, sehingga PatientRegistrationService mengisi
 * kolom `patient_number` dalam transaksi yang sama (bukan lewat event model).
 */
final class PatientNumberGenerator
{
    public static function format(int $id): string
    {
        return 'P'.str_pad((string) $id, 6, '0', STR_PAD_LEFT);
    }
}
