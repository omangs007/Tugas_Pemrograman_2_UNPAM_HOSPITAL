<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Admin\Concerns\HasPatientIdentityRules;
use App\Models\PatientProfile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;

/**
 * V-13 (ubah), F7A-R9: enam kolom identitas profil. Field sistem (`user_id`, `patient_number`, `registration_channel`,
 * `consent_*`, `is_active`, `created_by_user_id`) dan nomor BPJS tidak ada di `rules()` sehingga tidak pernah masuk
 * `validated()`. Untuk profil berakun `email` TIDAK divalidasi dan tidak dipakai (email profil = email login, hanya-baca
 * di layar admin); nama dan telepon disinkronkan ke akun oleh PatientRegistrationService::updateProfile().
 */
class UpdatePatientRequest extends FormRequest
{
    use HasPatientIdentityRules;

    public function authorize(): bool
    {
        $patient = $this->route('patient');

        return $patient instanceof PatientProfile && (bool) $this->user()?->can('update', $patient);
    }

    /** Profil yang diubah sudah terhubung ke akun login. */
    protected function hasAccount(): bool
    {
        $patient = $this->route('patient');

        return $patient instanceof PatientProfile && $patient->user_id !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeIdentityInput(! $this->hasAccount());
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return $this->identityRules(! $this->hasAccount());
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->identityMessages();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return $this->identityAttributes();
    }

    /**
     * Data untuk PatientRegistrationService::updateProfile(); `email` hanya ada untuk profil walk-in.
     *
     * @return array<string, mixed>
     */
    public function profileData(): array
    {
        return Arr::only($this->validated(), ['full_name', 'birth_date', 'gender', 'phone', 'email', 'address']);
    }
}
