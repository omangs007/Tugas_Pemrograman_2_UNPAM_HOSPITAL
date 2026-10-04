<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Menolak pola tag HTML sungguhan (12 §3.1); teks seperti "tekanan <90 dan >100" tetap lolos.
 * Aturan tidak dijalankan untuk nilai kosong.
 */
final class NoHtml implements ValidationRule
{
    public const PATTERN = '/<\s*\/?\s*[a-zA-Z!][^>]*>/';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || preg_match(self::PATTERN, $value) === 1) {
            $fail('messages.rules.no_html')->translate();
        }
    }
}
