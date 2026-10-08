<?php

namespace App\Http\Requests\Patient;

use App\Rules\PasswordRules;
use Illuminate\Foundation\Http\FormRequest;

/**
 * V-07: ganti password pasien (#28). Aturan dan pesan sama dengan Admin\UpdatePasswordRequest; berbeda pada authorize()
 * (akun pasien) dan pada `current_password`: `bail` + `string` mendahului aturan `current_password` karena aturan bawaan
 * meneruskan nilai ke `password_verify()` tanpa memeriksa tipe (`current_password[]=x` akan menjadi 500).
 */
class UpdatePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isPatient();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'current_password' => ['bail', 'required', 'string', 'current_password'],
            'password' => [...PasswordRules::default(), 'different:current_password'],
            'password_confirmation' => ['required'],
        ];
    }

    /**
     * Kunci `password.password.letters`/`numbers` dipakai oleh validator bersarang aturan Password.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'current_password.required' => 'Password saat ini wajib diisi.',
            'current_password.string' => 'Password saat ini tidak valid.',
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

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'current_password' => 'Password saat ini',
            'password' => 'Password baru',
            'password_confirmation' => 'Konfirmasi password baru',
        ];
    }
}
