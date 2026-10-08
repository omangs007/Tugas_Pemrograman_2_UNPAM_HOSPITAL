<?php

namespace App\Http\Requests\Auth;

use App\Enums\Gender;
use App\Rules\IndonesianPhone;
use App\Rules\PasswordRules;
use App\Rules\PersonName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * V-01 (12 §4, F5-R9): registrasi pasien, form tamu. Tanpa NIK dan tanpa field sistem (`user_id`, `patient_number`,
 * `registration_channel`): hanya sembilan field di `rules()` yang dipakai lewat `validated()`. Nilai larik atau
 * nilai liar menghasilkan galat berbahasa Indonesia, bukan 500.
 */
class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalisasi sebelum validasi (12 §1 butir 4): trim, email huruf kecil, telepon tanpa spasi/tanda pemisah.
     * Hanya untuk nilai string; nilai lain dibiarkan agar divalidasi sebagai galat. Password tidak pernah diubah.
     */
    protected function prepareForValidation(): void
    {
        $normalized = [];

        foreach (['full_name', 'birth_date', 'gender', 'address'] as $field) {
            $value = $this->input($field);

            if (is_string($value)) {
                $normalized[$field] = trim($value);
            }
        }

        $email = $this->input('email');

        if (is_string($email)) {
            $normalized['email'] = Str::lower(trim($email));
        }

        $phone = $this->input('phone');

        if (is_string($phone)) {
            $normalized['phone'] = IndonesianPhone::normalize(trim($phone));
        }

        $this->merge($normalized);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'full_name' => ['bail', 'required', 'string', 'min:3', 'max:100', new PersonName],
            'email' => ['bail', 'required', 'string', 'max:255', 'email:rfc', 'unique:users,email'],
            'phone' => ['bail', 'required', new IndonesianPhone],
            'birth_date' => ['bail', 'required', 'date_format:Y-m-d', 'before:today', 'after_or_equal:-120 years'],
            'gender' => ['bail', 'required', Rule::enum(Gender::class)],
            'address' => ['bail', 'required', 'string', 'min:10', 'max:500'],
            'password' => PasswordRules::default(),
            'password_confirmation' => ['required'],
            'consent' => ['accepted'],
        ];
    }

    /**
     * Pesan V-01. Kunci `password.password.letters`/`numbers` dipakai oleh validator bersarang aturan Password.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'full_name.required' => 'Nama lengkap wajib diisi.',
            'full_name.string' => 'Nama lengkap tidak valid.',
            'full_name.min' => 'Nama lengkap minimal 3 karakter.',
            'full_name.max' => 'Nama lengkap maksimal 100 karakter.',
            'email.required' => 'Email wajib diisi.',
            'email.string' => 'Format email tidak valid.',
            'email.max' => 'Email maksimal 255 karakter.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email ini sudah terdaftar. Silakan masuk atau gunakan email lain.',
            'phone.required' => 'Nomor telepon wajib diisi.',
            'birth_date.required' => 'Tanggal lahir wajib diisi.',
            'birth_date.date_format' => 'Format tanggal lahir tidak valid.',
            'birth_date.before' => 'Tanggal lahir harus sebelum hari ini.',
            'birth_date.after_or_equal' => 'Tanggal lahir tidak boleh lebih dari 120 tahun yang lalu.',
            'gender.required' => 'Jenis kelamin wajib dipilih.',
            'gender.enum' => 'Jenis kelamin tidak valid.',
            'address.required' => 'Alamat wajib diisi.',
            'address.string' => 'Alamat tidak valid.',
            'address.min' => 'Alamat minimal 10 karakter.',
            'address.max' => 'Alamat maksimal 500 karakter.',
            'password.required' => 'Password wajib diisi.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
            'password.max' => 'Password maksimal 64 karakter.',
            'password.min' => 'Password minimal 8 karakter.',
            'password.string' => 'Password tidak valid.',
            'password.password.letters' => 'Password harus mengandung huruf.',
            'password.password.numbers' => 'Password harus mengandung angka.',
            'password_confirmation.required' => 'Konfirmasi password wajib diisi.',
            'consent.accepted' => 'Anda harus menyetujui Kebijakan Privasi untuk melanjutkan.',
        ];
    }

    /**
     * Nama bidang untuk pesan aturan kustom (`PersonName` memakai `:Attribute`).
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'full_name' => 'Nama lengkap',
            'email' => 'Email',
            'phone' => 'Nomor telepon',
            'birth_date' => 'Tanggal lahir',
            'gender' => 'Jenis kelamin',
            'address' => 'Alamat',
            'password' => 'Password',
            'password_confirmation' => 'Konfirmasi password',
            'consent' => 'Persetujuan',
        ];
    }
}
