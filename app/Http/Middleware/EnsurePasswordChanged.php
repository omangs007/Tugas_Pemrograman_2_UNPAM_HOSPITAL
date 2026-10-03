<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Alias `password.changed` (11 §7.3): staf dengan password sementara hanya boleh membuka halaman ganti
 * password dan keluar. Daftar pengecualian bersifat pertahanan berlapis; route tersebut juga berada
 * di luar grup G-ADMIN penuh sehingga tidak menimbulkan pengalihan berulang (00 I.4).
 */
class EnsurePasswordChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user?->must_change_password
            && ! $request->routeIs('admin.password.edit', 'admin.password.update', 'admin.logout')) {
            if ($request->expectsJson()) {
                abort(403, 'Ganti password sementara terlebih dahulu.');
            }

            return redirect()->route('admin.password.edit')
                ->with('warning', 'Anda harus mengganti password sementara sebelum melanjutkan.');
        }

        return $next($request);
    }
}
