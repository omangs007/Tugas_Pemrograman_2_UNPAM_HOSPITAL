<?php

namespace App\Http\Requests\Admin;

use App\Models\PatientProfile;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * V-18: `is_active` wajib boolean; `reason` wajib (5-255 karakter) saat menonaktifkan, opsional saat mengaktifkan.
 * Penolakan karena pendaftaran aktif (BR-105) datang dari PatientRegistrationService::changeStatus() sebagai
 * ValidationException kunci `is_active` dan ditangani controller.
 */
class UpdatePatientStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $patient = $this->route('patient');

        return $patient instanceof PatientProfile && (bool) $this->user()?->can('changeStatus', $patient);
    }

    protected function prepareForValidation(): void
    {
        $reason = $this->input('reason');

        if (is_string($reason)) {
            $this->merge(['reason' => trim($reason)]);
        }
    }

    /** Permintaan menonaktifkan: `is_active` bernilai salah yang dikenal (`0`, `false`). */
    private function deactivating(): bool
    {
        return in_array($this->input('is_active'), [0, '0', false, 'false'], true);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'is_active' => ['required', 'boolean'],
            'reason' => ['nullable', Rule::requiredIf(fn (): bool => $this->deactivating()), 'string', 'min:5', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'is_active.required' => 'Status pasien wajib dipilih.',
            'is_active.boolean' => 'Status pasien tidak valid.',
            'reason.required' => 'Alasan penonaktifan wajib diisi.',
            'reason.string' => 'Alasan harus berupa teks.',
            'reason.min' => 'Alasan minimal 5 karakter.',
            'reason.max' => 'Alasan maksimal 255 karakter.',
        ];
    }

    public function isActive(): bool
    {
        return $this->boolean('is_active');
    }

    public function reason(): ?string
    {
        $reason = $this->validated('reason');

        return is_string($reason) && trim($reason) !== '' ? trim($reason) : null;
    }

    /**
     * Modal ditutup setelah kirim, jadi galat juga dipasang sebagai flash `error` agar terlihat di halaman asal.
     */
    protected function failedValidation(ValidatorContract $validator): void
    {
        session()->flash('error', (string) $validator->errors()->first());

        parent::failedValidation($validator);
    }
}
