<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Services\Patients\PatientRegistrationService;
use App\Support\PatientIntendedUrl;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Throwable;

class RegisterController extends Controller
{
    /**
     * Field yang tidak pernah dikembalikan ke form. `back()->withInput()` tanpa argumen tidak menyaring daftar
     * `dontFlash` (hanya ValidationException yang disaring handler), sehingga controller menyaringnya sendiri.
     */
    private const SENSITIVE_INPUT = ['password', 'password_confirmation', 'current_password', 'bpjs_number', 'payment_reference'];

    public function create(): View
    {
        return view('auth.register');
    }

    public function store(RegisterRequest $request, PatientRegistrationService $registration): RedirectResponse
    {
        try {
            $user = $registration->register($request->validated());
        } catch (UniqueConstraintViolationException) {
            // Balapan: email yang sama terdaftar di antara validasi `unique` dan insert.
            return back()
                ->withInput($request->except(self::SENSITIVE_INPUT))
                ->withErrors(['email' => 'Email ini sudah terdaftar. Silakan masuk atau gunakan email lain.']);
        } catch (Throwable $e) {
            report($e);

            return back()
                ->withInput($request->except(self::SENSITIVE_INPUT))
                ->with('error', 'Registrasi belum berhasil karena gangguan sistem. Silakan coba lagi.');
        }

        Auth::login($user);
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();

        return redirect()->to(PatientIntendedUrl::pull($request))
            ->with('success', "Registrasi berhasil. Selamat datang, {$user->name}.");
    }
}
