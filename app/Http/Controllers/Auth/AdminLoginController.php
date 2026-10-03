<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AdminLoginRequest;
use App\Services\Audit\AuditLogger;
use App\Support\Actor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AdminLoginController extends Controller
{
    public function create(): View
    {
        return view('auth.admin-login');
    }

    public function store(AdminLoginRequest $request, AuditLogger $audit): RedirectResponse
    {
        $user = $request->authenticate($audit);

        $request->session()->regenerate();

        // 12 §5.1 butir 3: penghitung limiter `login` (email + IP) dibersihkan setelah login sukses.
        // Kunci sama dengan ThrottleRequests: md5(nama limiter . kunci Limit) — lihat AppServiceProvider::emailKey().
        RateLimiter::clear(md5('login'.Str::lower(trim((string) $request->input('email'))).'|'.$request->ip()));

        $user->forceFill(['last_login_at' => now()])->save();

        $audit->log(
            'auth.staff_login',
            $user,
            null,
            null,
            "Staf {$user->name} masuk ke panel admin.",
            Actor::staff($user),
        );

        return redirect()->intended(route('admin.dashboard'));
    }

    public function destroy(Request $request, AuditLogger $audit): RedirectResponse
    {
        $user = $request->user();

        $audit->log(
            'auth.staff_logout',
            $user,
            null,
            null,
            "Staf {$user->name} keluar dari panel admin.",
            Actor::staff($user),
        );

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login')->with('success', 'Anda telah keluar.');
    }
}
