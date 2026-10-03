<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;

final class SafeRedirect
{
    private const MAX_LENGTH = 2000;

    /**
     * Tujuan kembali setelah galat/penolakan. Halaman "tinjau" dan "simpan" wizard adalah hasil POST
     * (tidak bisa di-GET), sehingga kembali ke langkah wizard, bukan ke back().
     */
    public static function back(Request $request): string
    {
        if ($request->routeIs('patient.appointments.review', 'patient.appointments.store')) {
            return route('patient.appointments.create', array_filter(['sesi' => $request->input('practice_session_id')]));
        }

        return url()->previous(route('home'));
    }

    /**
     * Mengembalikan $value hanya bila berupa path internal yang aman (parameter `kembali`), selain itu null.
     */
    public static function internalPath(?string $value): ?string
    {
        if ($value === null || $value === '' || strlen($value) > self::MAX_LENGTH) {
            return null;
        }

        // Harus path absolut di situs ini: diawali "/", bukan "//" (protokol relatif).
        if (! str_starts_with($value, '/') || str_starts_with($value, '//')) {
            return null;
        }

        // Backslash dan karakter kontrol (tab/CR/LF dibuang browser sehingga "/\t/evil" menjadi "//evil").
        if (str_contains($value, '\\') || preg_match('/[\x00-\x1F\x7F]/', $value) === 1) {
            return null;
        }

        $parts = parse_url($value);

        if ($parts === false || isset($parts['scheme']) || isset($parts['host'])) {
            return null;
        }

        return $value;
    }
}
