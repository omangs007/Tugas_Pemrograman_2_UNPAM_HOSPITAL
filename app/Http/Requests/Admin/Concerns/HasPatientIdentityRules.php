<?php

namespace App\Http\Requests\Admin\Concerns;

use App\Enums\Gender;
use App\Rules\IndonesianPhone;
use App\Rules\PersonName;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Aturan identitas profil pasien yang dipakai bersama `StorePatientRequest` dan `UpdatePatientRequest` (12 V-13):
 * nama, tanggal lahir, jenis kelamin, telepon, alamat, dan email opsional (kontak; bukan email login). Tanpa NIK dan
 * tanpa nomor BPJS (F7A-R8): field di luar daftar ini tidak pernah masuk `validated()`.
 */
trait HasPatientIdentityRules
{
    /**
     * Normalisasi (12 §1 butir 4): trim teks, email huruf kecil (kosong menjadi null), telepon tanpa spasi, tanda
     * hubung, titik, dan kurung. Nilai bukan string dibiarkan agar aturan `string`/`required` yang menolaknya.
     */
    protected function normalizeIdentityInput(bool $withEmail): void
    {
        $normalized = [];

        foreach (['full_name', 'address'] as $field) {
            $value = $this->input($field);

            if (is_string($value)) {
                $normalized[$field] = trim($value);
            }
        }

        if ($withEmail) {
            $email = $this->input('email');

            if (is_string($email)) {
                $email = Str::lower(trim($email));
                $normalized['email'] = $email === '' ? null : $email;
            }
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
    protected function identityRules(bool $withEmail): array
    {
        $rules = [
            'full_name' => ['bail', 'required', 'string', 'min:3', 'max:100', new PersonName],
            'birth_date' => ['bail', 'required', 'date_format:Y-m-d', 'before:today', 'after_or_equal:-120 years'],
            'gender' => ['bail', 'required', Rule::enum(Gender::class)],
            'phone' => ['bail', 'required', new IndonesianPhone],
            'address' => ['bail', 'required', 'string', 'min:10', 'max:500'],
        ];

        if ($withEmail) {
            $rules['email'] = ['bail', 'nullable', 'string', 'max:255', 'email:rfc'];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    protected function identityMessages(): array
    {
        return [
            'full_name.required' => 'Nama lengkap wajib diisi.',
            'full_name.string' => 'Nama lengkap harus berupa teks.',
            'full_name.min' => 'Nama lengkap minimal 3 karakter.',
            'full_name.max' => 'Nama lengkap maksimal 100 karakter.',
            'birth_date.required' => 'Tanggal lahir wajib diisi.',
            'birth_date.date_format' => 'Format tanggal lahir tidak valid.',
            'birth_date.before' => 'Tanggal lahir harus sebelum hari ini.',
            'birth_date.after_or_equal' => 'Tanggal lahir tidak boleh lebih dari 120 tahun yang lalu.',
            'gender.required' => 'Jenis kelamin wajib dipilih.',
            'gender.enum' => 'Jenis kelamin tidak valid.',
            'phone.required' => 'Nomor telepon wajib diisi.',
            'email.string' => 'Format email tidak valid.',
            'email.max' => 'Email maksimal 255 karakter.',
            'email.email' => 'Format email tidak valid.',
            'address.required' => 'Alamat wajib diisi.',
            'address.string' => 'Alamat harus berupa teks.',
            'address.min' => 'Alamat minimal 10 karakter.',
            'address.max' => 'Alamat maksimal 500 karakter.',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function identityAttributes(): array
    {
        return [
            'full_name' => 'Nama lengkap',
            'birth_date' => 'Tanggal lahir',
            'gender' => 'Jenis kelamin',
            'phone' => 'Nomor telepon',
            'email' => 'Email',
            'address' => 'Alamat',
            'verbal_consent' => 'Persetujuan privasi lisan',
            'confirm_not_duplicate' => 'Konfirmasi profil mirip',
            'is_active' => 'Status pasien',
            'reason' => 'Alasan',
        ];
    }
}
