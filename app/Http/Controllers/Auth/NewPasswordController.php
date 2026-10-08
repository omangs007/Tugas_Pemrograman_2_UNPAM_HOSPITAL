<?php

namespace App\Http\Controllers\Auth;

use App\Enums\AccountType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\Actor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Form dan pemrosesan reset password pasien (#26, F5-R15). Callback broker (dalam satu transaksi): menyimpan password
 * (cast `hashed` pada User meng-hash; JANGAN `Hash::make`), mengganti `remember_token`, menghapus SEMUA baris `sessions`
 * milik akun (BR-148), dan mencatat audit `auth.password_reset` tanpa token/password. Tidak ada login otomatis (K-11).
 */
class NewPasswordController extends Controller
{
    public function create(Request $request, string $token): View
    {
        $email = $request->query('email');

        // `?email[]=x` menghasilkan larik: dianggap kosong (bukan 500).
        $email = is_string($email) ? Str::lower(trim($email)) : '';

        return view('auth.reset-password', [
            'token' => $token,
            'email' => $email,
        ]);
    }

    public function store(ResetPasswordRequest $request, AuditLogger $audit): RedirectResponse
    {
        $status = Password::broker()->reset(
            [
                'email' => $request->validated('email'),
                'token' => $request->validated('token'),
                'password' => $request->validated('password'),
                'password_confirmation' => $request->validated('password_confirmation'),
                'account_type' => AccountType::Patient->value,
                'is_active' => true,
            ],
            function (User $user, string $password) use ($audit): void {
                DB::transaction(function () use ($user, $password, $audit): void {
                    $user->forceFill([
                        'password' => $password,
                        'remember_token' => Str::random(60),
                    ])->save();

                    DB::table('sessions')->where('user_id', $user->id)->delete();

                    $number = $user->patientProfile?->patient_number ?? '-';

                    $audit->log(
                        'auth.password_reset',
                        $user,
                        null,
                        null,
                        "Password akun pasien {$number} diatur ulang lewat tautan email.",
                        Actor::patient($user),
                    );
                });
            },
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('login')->with('success', __('passwords.reset'));
        }

        // Token salah/kedaluwarsa, email tidak cocok, staf, atau nonaktif: satu pesan yang sama (tanpa membedakan).
        return back()
            ->withInput($request->only('email'))
            ->withErrors(['email' => __('passwords.token')]);
    }
}
