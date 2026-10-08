<?php

declare(strict_types=1);

namespace App\Services\Booking;

/**
 * Kode booking `UPH-XXXX-XXXX` (F6-R5, 00 F.7, BR-49): 8 karakter acak dari alfabet Crockford Base32
 * (tanpa I, L, O, U) lewat `random_int`, ±40 bit. Kode tidak memuat data pasien. Keunikan dijaga
 * `BookingService` (cek `exists()` di bawah kunci + UNIQUE `appointments_booking_code_unique`).
 */
final class BookingCodeGenerator
{
    public const PREFIX = 'UPH-';

    public const ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    public const PATTERN = '/^UPH-[0-9A-HJKMNP-TV-Z]{4}-[0-9A-HJKMNP-TV-Z]{4}$/';

    /** Panjang kode kanonik: "UPH-" + 4 + "-" + 4. */
    private const LENGTH = 13;

    public static function generate(): string
    {
        $last = strlen(self::ALPHABET) - 1;
        $body = '';

        for ($i = 0; $i < 8; $i++) {
            $body .= self::ALPHABET[random_int(0, $last)];
        }

        return self::compose($body);
    }

    /**
     * Hanya bentuk kanonik yang sah. Panjang diperiksa terpisah karena `$` pada PATTERN juga cocok sebelum
     * satu newline penutup ("UPH-ABCD-EFGH\n").
     */
    public static function isValid(string $code): bool
    {
        return strlen($code) === self::LENGTH && preg_match(self::PATTERN, $code) === 1;
    }

    /**
     * Merapikan ketikan atau keluaran pemindai: pangkas dan buang spasi/tab/newline, huruf besar,
     * O → 0 dan I/L → 1 (Crockford). Bila tanpa tanda hubung dan panjangnya 8 (hanya isi) atau 11
     * ("UPH" + isi), sisipkan "UPH-" dan "-". Hasilnya BELUM tentu sah: panggil `isValid()`.
     */
    public static function normalize(string $input): string
    {
        $code = strtoupper((string) preg_replace('/\s+/', '', $input));
        $code = strtr($code, ['O' => '0', 'I' => '1', 'L' => '1']);

        if (! str_contains($code, '-')) {
            if (strlen($code) === 8) {
                return self::compose($code);
            }

            if (strlen($code) === 11 && str_starts_with($code, 'UPH')) {
                return self::compose(substr($code, 3));
            }
        }

        return $code;
    }

    private static function compose(string $body): string
    {
        return self::PREFIX.substr($body, 0, 4).'-'.substr($body, 4, 4);
    }
}
