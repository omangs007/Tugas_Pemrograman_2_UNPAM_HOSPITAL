<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Tujuan `kembali` pada layar petugas (F7A-R19, 11 §1): path internal yang lolos `SafeRedirect::internalPath()` DAN
 * berada di `/admin/pendaftaran...` atau `/admin/antrean...`. Segmen `.`/`..` ditolak (termasuk yang diterjemahkan
 * `%2e`), supaya awalan yang diizinkan tidak dapat dipakai menyeberang ke area lain. Selain itu mengembalikan `$fallback`.
 */
final class AdminReturn
{
    /** @var list<string> */
    private const AREAS = ['/admin/pendaftaran', '/admin/antrean'];

    public static function path(?string $value, string $fallback): string
    {
        $path = SafeRedirect::internalPath($value);

        if ($path === null) {
            return $fallback;
        }

        $route = parse_url($path, PHP_URL_PATH);

        if (! is_string($route)) {
            return $fallback;
        }

        $decoded = rawurldecode($route);

        if (str_contains($decoded, '\\')) {
            return $fallback;
        }

        foreach (explode('/', $decoded) as $segment) {
            if ($segment === '.' || $segment === '..') {
                return $fallback;
            }
        }

        foreach (self::AREAS as $area) {
            if ($route === $area || str_starts_with($route, $area.'/')) {
                return $path;
            }
        }

        return $fallback;
    }
}
