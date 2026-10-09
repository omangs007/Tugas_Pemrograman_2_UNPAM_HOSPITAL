<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Admin\Concerns\HasPatientIdentityRules;
use App\Models\PatientProfile;
use App\Services\Patients\PatientSearch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Validator;

/**
 * V-13 (buat), F7A-R8/R11: profil walk-in dibuat petugas `patients.manage`. `verbal_consent` wajib dicentang
 * (BR-99); bila ada profil aktif yang mirip (nama + tanggal lahir sama, atau telepon sama; BR-102) petugas harus
 * mencentang `confirm_not_duplicate`. Tanpa NIK, tanpa nomor BPJS, tanpa akun login. `kembali` TIDAK divalidasi di sini:
 * controller hanya menerimanya bila sah (F7A-R31) dan mengabaikan nilai lain.
 */
class StorePatientRequest extends FormRequest
{
    use HasPatientIdentityRules;

    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', PatientProfile::class);
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeIdentityInput(true);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge($this->identityRules(true), [
            'verbal_consent' => ['accepted'],
            'confirm_not_duplicate' => ['nullable', 'boolean'],
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge($this->identityMessages(), [
            'verbal_consent.accepted' => __('patients.walk_in.consent_required'),
            'confirm_not_duplicate.boolean' => 'Konfirmasi profil mirip tidak valid.',
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return $this->identityAttributes();
    }

    /**
     * Pemeriksaan profil mirip hanya berjalan bila nama, tanggal lahir, dan telepon sudah lolos dan petugas belum
     * mencentang konfirmasi. Daftar profil mirip ditampilkan ulang oleh PatientController::create.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['full_name', 'birth_date', 'phone', 'confirm_not_duplicate'])) {
                    return;
                }

                if ($this->boolean('confirm_not_duplicate')) {
                    return;
                }

                $similar = app(PatientSearch::class)->similarTo(
                    (string) $this->input('full_name'),
                    (string) $this->input('birth_date'),
                    (string) $this->input('phone'),
                );

                if ($similar->isNotEmpty()) {
                    $validator->errors()->add('confirm_not_duplicate', __('patients.walk_in.similar'));
                }
            },
        ];
    }

    /**
     * Data untuk PatientRegistrationService::createWalkIn(): enam kolom identitas; `email` null bila kosong.
     *
     * @return array<string, mixed>
     */
    public function walkInData(): array
    {
        return array_merge(
            ['email' => null],
            Arr::only($this->validated(), ['full_name', 'birth_date', 'gender', 'phone', 'email', 'address']),
        );
    }
}
