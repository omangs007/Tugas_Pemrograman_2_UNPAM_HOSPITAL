<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

/**
 * Alias `active` (11 §7.2): memutus sesi akun yang dinonaktifkan saat sesi masih berjalan.
 * Login baru oleh akun nonaktif sudah gagal generik di Auth::attempt (kondisi is_active = true).
 */
class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || $user->is_active) {
            return $next($request);
        }

        $isStaff = $user->isStaff();

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Cadangan F1-R2: `login` belum ada sampai fase 5.
        $loginRoute = $isStaff ? 'admin.login' : (Route::has('login') ? 'login' : 'home');

        return redirect()->route($loginRoute)
            ->with('error', 'Akun Anda dinonaktifkan. Silakan hubungi administrator rumah sakit.');
    }
}
