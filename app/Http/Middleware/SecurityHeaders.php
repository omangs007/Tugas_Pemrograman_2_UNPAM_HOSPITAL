<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Header keamanan global (00 J.3, 11 §7.4, 12 §5.4).
 */
class SecurityHeaders
{
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
