<?php

namespace App\Http\Requests\Auth;

use App\Enums\AccountType;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * V-02 (F5-R6): login pasien. Autentikasi hanya untuk akun pasien aktif; semua kegagalan (email tak dikenal,
 * password salah, akun staf, akun nonaktif) memakai pesan generik yang sama (D-22, AKS-08, SEK-16).
 */
class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** Email dinormalkan (trim + huruf kecil) sebelum validasi dan autentikasi; nilai bukan string dibiarkan. */
    protected function prepareForValidation(): void
    {
        $email = $this->input('email');

        if (is_string($email)) {
            $this->merge(['email' => Str::lower(trim($email))]);
        }
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['bail', 'required', 'string', 'max:255', 'email:rfc'],
            'password' => ['required', 'string', 'max:255'],
            'remember' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => 'Email wajib diisi.',
            'email.string' => 'Format email tidak valid.',
            'email.email' => 'Format email tidak valid.',
            'email.max' => 'Email maksimal 255 karakter.',
            'password.required' => 'Password wajib diisi.',
            'password.string' => 'Password tidak valid.',
            'password.max' => 'Password maksimal 255 karakter.',
            'remember.boolean' => 'Pilihan ingat saya tidak valid.',
        ];
    }

    public function authenticate(): User
    {
        $credentials = [
            'email' => (string) $this->validated('email'),
            'password' => (string) $this->validated('password'),
            'account_type' => AccountType::Patient->value,
            'is_active' => true,
        ];

        if (Auth::attempt($credentials, $this->boolean('remember'))) {
            /** @var User $user */
            $user = Auth::user();

            return $user;
        }

        throw ValidationException::withMessages(['email' => __('auth.failed')]);
    }
}
