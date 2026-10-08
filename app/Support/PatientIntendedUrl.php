<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Tujuan setelah login atau registrasi pasien (F5-R7). URL tujuan semula (`url.intended`, diisi middleware `auth`
 * saat tamu membuka halaman terlindungi lewat GET) dipakai bila berupa string dan path-nya bukan area admin;
 * selain itu dashboard pasien. Kunci sesi selalu dihapus (`pull`).
 */
final class PatientIntendedUrl
{
    public static function pull(Request $request): string
    {
        $intended = $request->session()->pull('url.intended');

        if (is_string($intended) && self::isAllowed($intended)) {
            return $intended;
        }

        return route('patient.dashboard');
    }

    private static function isAllowed(string $url): bool
    {
        $path = parse_url($url, PHP_URL_PATH);

        if (! is_string($path) || $path === '') {
            return false;
        }

        return $path !== '/admin' && ! str_starts_with($path, '/admin/');
    }
}
