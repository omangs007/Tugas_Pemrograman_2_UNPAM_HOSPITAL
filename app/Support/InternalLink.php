<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Memeriksa apakah sebuah path internal (mis. `/dokter?q=a`) cocok dengan route GET yang terdaftar
 * (F4A-R15). Dipakai `Banner::hasUsableButton()` agar tombol banner yang menunjuk route fase lain
 * (mis. `/pasien/pendaftaran/baru` sebelum fase 6) tidak tampil sebagai tautan mati.
 *
 * Hanya path yang diawali satu `/`; `//host`, karakter kontrol, spasi, dan backslash ditolak (browser
 * memperlakukan `/\host` sebagai `//host`). Query dan fragmen diabaikan. Pencocokan memakai router
 * (`RouteCollection::match`), jadi segmen berparameter seperti `/dokter/abc` dianggap ada; keberadaan
 * modelnya tidak diperiksa.
 */
final class InternalLink
{
    public static function exists(string $path): bool
    {
        if ($path === '' || $path[0] !== '/' || str_starts_with($path, '//')) {
            return false;
        }

        if (preg_match('/[\x00-\x20\x7F\\\\]/', $path) === 1) {
            return false;
        }

        $target = parse_url($path, PHP_URL_PATH);

        if (! is_string($target) || $target === '') {
            return false;
        }

        try {
            app(Router::class)->getRoutes()->match(Request::create($target, 'GET'));
        } catch (NotFoundHttpException|MethodNotAllowedHttpException) {
            return false;
        }

        return true;
    }
}
