<?php

namespace App\Http\Controllers\Auth;

use App\Enums\AccountType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;
use Throwable;

/**
 * Permintaan tautan reset password pasien (#25, F5-R15). Kredensial tambahan `account_type`/`is_active` diteruskan ke
 * `EloquentUserProvider::retrieveByCredentials()` sebagai klausa `where`, sehingga staf dan akun nonaktif diperlakukan
 * sama dengan email tak terdaftar. Status broker DIABAIKAN: apa pun hasilnya (`RESET_LINK_SENT`, `INVALID_USER`,
 * `RESET_THROTTLED`) jawabannya identik (anti-enumerasi, NTF-05, SEK-16). Tautan hanya dikirim lewat email.
 */
class PasswordResetLinkController extends Controller
{
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(ForgotPasswordRequest $request): RedirectResponse
    {
        try {
            Password::broker()->sendResetLink([
                'email' => $request->validated('email'),
                'account_type' => AccountType::Patient->value,
                'is_active' => true,
            ]);
        } catch (Throwable $e) {
            // Kegagalan kirim email tidak boleh membedakan respons (dan tidak boleh menjadi 500).
            report($e);
        }

        return redirect()->route('password.request')->with('success', __('passwords.sent'));
    }
}
