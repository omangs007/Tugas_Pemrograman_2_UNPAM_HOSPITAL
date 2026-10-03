<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Header keamanan global (00 J.3, 11 §7.4, 12 §5.4).
 *
 * `style-src 'unsafe-inline'` dipertahankan karena Bootstrap, Chart.js, dan Quill menulis atribut `style`
 * (11 §7.4 [ASUMSI]); skrip inline dan atribut event inline dilarang oleh `script-src 'self'`, sehingga
 * fallback gambar memakai `data-fallback` + listener terdelegasi di app.js (00 K).
 */
class SecurityHeaders
{
    public const CSP = "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; connect-src 'self'; frame-src https://www.google.com https://maps.google.com; frame-ancestors 'self'; base-uri 'self'; form-action 'self'; object-src 'none'";

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $headers = $response->headers;

        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'SAMEORIGIN');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()');

        if ($this->isSensitivePath($request) && ! $this->hasExplicitCaching($response)) {
            $headers->set('Cache-Control', 'no-store, private');
        }

        // CSP hanya di produksi: di lokal dinonaktifkan agar dev server Vite (HMR) berjalan (00 B.2).
        if (app()->isProduction()) {
            $headers->set('Content-Security-Policy', self::CSP);

            if ($request->isSecure()) {
                $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
            }
        }

        return $response;
    }

    /** Halaman berisi data pribadi/token tidak boleh disimpan cache bersama atau tombol Back. */
    private function isSensitivePath(Request $request): bool
    {
        return $request->is(
            'admin', 'admin/*',
            'pasien', 'pasien/*',
            'masuk', 'daftar', 'lupa-password', 'reset-password/*',
        );
    }

    /** Respons yang sudah menetapkan kebijakan cache sendiri (mis. PDF) tidak ditimpa. */
    private function hasExplicitCaching(Response $response): bool
    {
        $headers = $response->headers;

        return $headers->hasCacheControlDirective('no-store')
            || $headers->hasCacheControlDirective('max-age')
            || $headers->hasCacheControlDirective('s-maxage');
    }
}
