<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Nomor seluler Indonesia (12 Asumsi 1, F4B-R34): spasi, tanda hubung, titik, dan kurung dibuang lebih dulu
 * (`normalize()`), lalu hasilnya harus cocok `^(\+62|62|0)8[0-9]{7,12}$`. Panjang terjauh 16 karakter (`+62` + `8` +
 * 12 digit) muat di `VARCHAR(20)`. Aturan tidak dijalankan untuk nilai kosong (gunakan `nullable` atau `required`).
 */
final class IndonesianPhone implements ValidationRule
{
    public const PATTERN = '/^(\+62|62|0)8[0-9]{7,12}$/';

    public const MESSAGE = 'Nomor telepon tidak valid. Gunakan nomor seluler Indonesia, mis. 081234567890.';

    /** Membuang spasi, tanda hubung, titik, dan kurung; tanda plus dan digit dipertahankan. */
    public static function normalize(string $value): string
    {
        return preg_replace('/[\s\-.()]+/u', '', $value) ?? $value;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || preg_match(self::PATTERN, self::normalize($value)) !== 1) {
            $fail(self::MESSAGE);
        }
    }
}
