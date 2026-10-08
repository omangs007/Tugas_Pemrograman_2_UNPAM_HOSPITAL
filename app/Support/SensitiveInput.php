<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Sumber tunggal daftar input yang tidak pernah dikembalikan ke formulir (F6-R36, 00 E.6): `dontFlash`, redirect
 * 419/429, handler `BookingException`, dan controller wizard memakai daftar ini. Nomor BPJS dan nomor polis lengkap
 * tidak pernah masuk `old()`; pasien mengisinya ulang (atau memakai nomor tersimpan di profil).
 */
final class SensitiveInput
{
    /** @var list<string> */
    public const FIELDS = [
        'password',
        'password_confirmation',
        'current_password',
        'bpjs_number',
        'policy_number',
        'payment_reference',
    ];
}
