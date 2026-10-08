<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

/**
 * V-04: permintaan tautan reset password pasien. Hanya format email yang divalidasi; email tak terdaftar, akun staf,
 * dan akun nonaktif TIDAK menghasilkan galat (respons selalu generik, BR-148). Email dinormalisasi (trim + huruf kecil)
 * sebelum validasi; nilai larik dibiarkan agar ditolak sebagai "Format email tidak valid." (bukan 500).
 */
class ForgotPasswordRequest extends FormRequest
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
            'email' => ['bail', 'required', 'string', 'max:255', 'email:rfc'],
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
            'email.max' => 'Email maksimal 255 karakter.',
            'email.email' => 'Format email tidak valid.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'email' => 'Email',
        ];
    }
}
