<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Tautan tombol banner (BR-124, 12 §3.1): path internal (diawali satu `/`, bukan `//`) atau URL `https://`
 * yang lolos FILTER_VALIDATE_URL. Skema lain (`http:`, `javascript:`, `data:`) ditolak. Path internal juga
 * menolak backslash, spasi, dan karakter kontrol karena browser memperlakukan `/\host` sebagai `//host`.
 * Aturan tidak dijalankan untuk nilai kosong; pasangkan dengan `nullable` atau `required`.
 */
final class InternalOrHttpsUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! $this->accepts($value)) {
            $fail('messages.rules.internal_or_https_url')->translate();
        }
    }

    private function accepts(string $value): bool
    {
        if ($value === '') {
            return false;
        }

        if ($value[0] === '/') {
            return ! str_starts_with($value, '//') && preg_match('/[\x00-\x20\x7F\\\\]/', $value) !== 1;
        }

        return str_starts_with($value, 'https://') && filter_var($value, FILTER_VALIDATE_URL) !== false;
    }
}
