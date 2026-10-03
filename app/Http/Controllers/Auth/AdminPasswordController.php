<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdatePasswordRequest;
use App\Services\Audit\AuditLogger;
use App\Support\Actor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminPasswordController extends Controller
{
    public function edit(Request $request): View
    {
        return view('auth.admin-change-password', [
            'forced' => (bool) $request->user()->must_change_password,
        ]);
    }

    public function update(UpdatePasswordRequest $request, AuditLogger $audit): RedirectResponse
    {
        $user = $request->user();
        $wasForced = (bool) $user->must_change_password;

        // Cast `hashed` pada User meng-hash password; jangan memanggil Hash::make() lagi.
        $user->forceFill([
            'password' => $request->validated('password'),
            'must_change_password' => false,
        ])->save();

        $request->session()->regenerate();

        $audit->log(
            'auth.password_changed',
            $user,
            ['must_change_password' => $wasForced],
            ['must_change_password' => false],
            "Staf {$user->name} mengganti password akunnya.",
            Actor::staff($user),
        );

        return redirect()->route('admin.dashboard')->with('success', 'Password berhasil diganti.');
    }
}
