<?php

declare(strict_types=1);

namespace App\Rules;

use Illuminate\Validation\Rules\Password;

/**
 * Bundel aturan password baku (12 §3.1): wajib, terkonfirmasi, maksimal 64, minimal 8 berisi huruf dan angka.
 * `uncompromised()` sengaja tidak dipakai karena memerlukan koneksi internet (00 H).
 */
final class PasswordRules
{
    /**
     * @return list<mixed>
     */
    public static function default(): array
    {
        return ['required', 'confirmed', 'max:64', Password::min(8)->letters()->numbers()];
    }
}
