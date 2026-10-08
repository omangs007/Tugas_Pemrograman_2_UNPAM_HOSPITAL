<?php

namespace App\Http\Requests\Patient;

use App\Enums\Gender;
use App\Models\User;
use App\Rules\IndonesianPhone;
use App\Rules\PersonName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * V-06: ubah profil pasien (#28). Daftar field TERTUTUP (BR-161): `user_id`, `patient_number`, `registration_channel`,
 * `is_active`, `consent_*`, `bpjs_number`, `bpjs_number_last4`, `created_by_user_id` tidak ada di `rules()` sehingga
 * tidak pernah muncul di `validated()`. Profil yang diubah SELALU `$user->patientProfile` (tanpa id di URL/form, BR-150).
 * Email login = email profil (D-07): unik di seluruh `users` kecuali akun sendiri. Nilai larik pada field mana pun
 * menghasilkan galat Indonesia (aturan `string`/`enum`/`boolean` mendahului), bukan 500.
 */
class UpdatePatientProfileRequest extends FormRequest
{
    /** F5-R13/F5-R14: butuh akun pasien yang punya profil dan boleh mengubahnya (PatientProfilePolicy::update). */
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user instanceof User || ! $user->isPatient()) {
            return false;
        }

        $profile = $user->patientProfile;

        return $profile !== null && $user->can('update', $profile);
    }

    /** Normalisasi (12 §1 butir 4): trim teks, email huruf kecil, telepon tanpa spasi/tanda hubung/titik/kurung. */
    protected function prepareForValidation(): void
    {
        $normalized = [];

        foreach (['full_name', 'address'] as $field) {
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
        $userId = $this->user()?->getKey();

        return [
            'full_name' => ['bail', 'required', 'string', 'min:3', 'max:100', new PersonName],
            'birth_date' => ['bail', 'required', 'date_format:Y-m-d', 'before:today', 'after_or_equal:-120 years'],
            'gender' => ['bail', 'required', Rule::enum(Gender::class)],
            'phone' => ['bail', 'required', new IndonesianPhone],
            'email' => ['bail', 'required', 'string', 'max:255', 'email:rfc', Rule::unique('users', 'email')->ignore($userId)],
            'address' => ['bail', 'required', 'string', 'min:10', 'max:500'],
            'remove_bpjs' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
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
            'email.required' => 'Email wajib diisi.',
            'email.string' => 'Format email tidak valid.',
            'email.max' => 'Email maksimal 255 karakter.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email ini sudah terdaftar. Gunakan email lain.',
            'address.required' => 'Alamat wajib diisi.',
            'address.string' => 'Alamat harus berupa teks.',
            'address.min' => 'Alamat minimal 10 karakter.',
            'address.max' => 'Alamat maksimal 500 karakter.',
            'remove_bpjs.boolean' => 'Pilihan hapus nomor BPJS tidak valid.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'full_name' => 'Nama lengkap',
            'birth_date' => 'Tanggal lahir',
            'gender' => 'Jenis kelamin',
            'phone' => 'Nomor telepon',
            'email' => 'Email',
            'address' => 'Alamat',
            'remove_bpjs' => 'Hapus nomor BPJS',
        ];
    }
}
