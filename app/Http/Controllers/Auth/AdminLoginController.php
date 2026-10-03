<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AdminLoginRequest;
use App\Services\Audit\AuditLogger;
use App\Support\Actor;
use Illuminate\Http\RedirectResponse;
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
}
