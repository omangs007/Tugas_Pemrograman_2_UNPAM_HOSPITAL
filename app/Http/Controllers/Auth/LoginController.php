<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Support\PatientIntendedUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $user = $request->authenticate();

        $request->session()->regenerate();

        // Penghitung limiter `login` (email + IP) dibersihkan setelah login sukses. Kunci sama dengan ThrottleRequests:
        // md5(nama limiter . kunci Limit); email sudah ternormalisasi oleh LoginRequest (lihat AppServiceProvider::emailKey()).
        RateLimiter::clear(md5('login'.$request->validated('email').'|'.$request->ip()));

        $user->forceFill(['last_login_at' => now()])->save();

        return redirect()->to(PatientIntendedUrl::pull($request));
    }

    /** Logout bersama (F5-R8): `auth` saja; staf yang memakainya ikut keluar tanpa audit. */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('success', 'Anda telah keluar.');
    }
}
