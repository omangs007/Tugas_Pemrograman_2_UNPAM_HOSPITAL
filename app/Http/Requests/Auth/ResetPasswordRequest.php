<?php

namespace App\Http\Requests\Auth;

use App\Rules\PasswordRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

/**
 * V-05: pengaturan ulang password dengan token. Kegagalan token/email tidak cocok BUKAN galat validasi: ia diputuskan
 * broker dan dijawab dengan satu pesan (`passwords.token`) oleh controller. Nilai larik pada field mana pun menghasilkan
 * galat berbahasa Indonesia, bukan 500.
 */
class ResetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $email = $this->input('email');

        if (is_string($email)) {
            $this->merge(['email' => Str::lower(trim($email))]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'token' => ['bail', 'required', 'string'],
            'email' => ['bail', 'required', 'string', 'max:255', 'email:rfc'],
            'password' => PasswordRules::default(),
            'password_confirmation' => ['required'],
        ];
    }

    /**
     * Kunci `password.password.letters`/`numbers` dipakai oleh validator bersarang aturan Password (pola Admin\UpdatePasswordRequest).
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'token.required' => 'Tautan pengaturan ulang password tidak valid. Silakan ajukan permintaan baru.',
            'token.string' => 'Tautan pengaturan ulang password tidak valid. Silakan ajukan permintaan baru.',
            'email.required' => 'Email wajib diisi.',
            'email.string' => 'Format email tidak valid.',
            'email.max' => 'Email maksimal 255 karakter.',
            'email.email' => 'Format email tidak valid.',
            'password.required' => 'Password wajib diisi.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
            'password.max' => 'Password maksimal 64 karakter.',
            'password.min' => 'Password minimal 8 karakter.',
            'password.string' => 'Password tidak valid.',
            'password.password.letters' => 'Password harus mengandung huruf.',
            'password.password.numbers' => 'Password harus mengandung angka.',
            'password_confirmation.required' => 'Konfirmasi password wajib diisi.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'token' => 'Token',
            'email' => 'Email',
            'password' => 'Password',
            'password_confirmation' => 'Konfirmasi password',
        ];
    }
}
