<?php

namespace App\Http\Requests\Admin;

use App\Rules\PasswordRules;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isStaff();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'current_password'],
            'password' => [...PasswordRules::default(), 'different:current_password'],
            'password_confirmation' => ['required'],
        ];
    }

    /**
     * Pesan V-07/V-08. Kunci `password.password.letters`/`numbers` dipakai oleh validator bersarang aturan Password.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'current_password.required' => 'Password saat ini wajib diisi.',
            'current_password.current_password' => 'Password saat ini tidak sesuai.',
            'password.required' => 'Password baru wajib diisi.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
            'password.max' => 'Password maksimal 64 karakter.',
            'password.min' => 'Password minimal 8 karakter.',
            'password.string' => 'Password tidak valid.',
            'password.password.letters' => 'Password harus mengandung huruf.',
            'password.password.numbers' => 'Password harus mengandung angka.',
            'password.different' => 'Password baru harus berbeda dari password saat ini.',
            'password_confirmation.required' => 'Konfirmasi password wajib diisi.',
        ];
    }
}
