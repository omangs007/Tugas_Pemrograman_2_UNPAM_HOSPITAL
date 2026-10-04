<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Nama orang (12 §3.1): huruf Unicode, spasi, titik, apostrof (' dan ’), dan tanda hubung. Angka ditolak.
 * Aturan tidak dijalankan untuk nilai kosong (kewajiban diisi ditentukan `required`).
 */
final class PersonName implements ValidationRule
{
    public const PATTERN = '/^[\p{L}\s.\'’-]+$/u';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || preg_match(self::PATTERN, $value) !== 1) {
            $fail('messages.rules.person_name')->translate();
        }
    }
}
