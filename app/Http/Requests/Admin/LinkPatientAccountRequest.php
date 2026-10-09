<?php

namespace App\Http\Requests\Admin;

use App\Models\PatientProfile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

/**
 * V-17 (F7A-R12): `account_email` (email akun pasien yang ditemukan lewat panel `?hubungkan=`) dan `identity_verified`
 * (petugas menegaskan identitas diverifikasi di loket, K-14). Otorisasi `PatientProfilePolicy::linkAccount`. Profil
 * nonaktif ditolak di sini (keputusan koordinator): linker menyamakan `is_active` profil dengan akun, sehingga tanpa
 * penolakan ini menghubungkan akan mengaktifkan kembali profil secara diam-diam. Aturan bisnis lain (sudah terhubung,
 * akun tidak ada/nonaktif/beriwayat) ada di PatientAccountLinker.
 */
class LinkPatientAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        $patient = $this->route('patient');

        return $patient instanceof PatientProfile && (bool) $this->user()?->can('linkAccount', $patient);
    }

    protected function prepareForValidation(): void
    {
        $email = $this->input('account_email');

        if (is_string($email)) {
            $this->merge(['account_email' => Str::lower(trim($email))]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'account_email' => ['bail', 'required', 'string', 'email:rfc', 'max:255'],
            'identity_verified' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'account_email.required' => 'Email akun wajib diisi.',
            'account_email.string' => 'Format email tidak valid.',
            'account_email.email' => 'Format email tidak valid.',
            'account_email.max' => 'Email maksimal 255 karakter.',
            'identity_verified.accepted' => __('patients.link.identity_required'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'account_email' => 'Email akun',
            'identity_verified' => 'Verifikasi identitas',
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $patient = $this->route('patient');

                if ($patient instanceof PatientProfile && ! $patient->is_active) {
                    $validator->errors()->add('account_email', 'Profil pasien tidak aktif. Aktifkan profil terlebih dahulu.');
                }
            },
        ];
    }

    public function accountEmail(): string
    {
        return (string) $this->validated('account_email');
    }
}
