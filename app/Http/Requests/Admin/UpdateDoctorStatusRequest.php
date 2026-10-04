<?php

namespace App\Http\Requests\Admin;

use App\Enums\DoctorStatus;
use App\Models\Doctor;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * V-20 (status): hanya `active` atau `inactive`. Dokter `archived` hanya dapat dipulihkan menjadi
 * `inactive` (F2-R7). Menonaktifkan tidak pernah ditolak karena appointment aktif (BR-05).
 */
class UpdateDoctorStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $doctor = $this->route('doctor');

        return $doctor instanceof Doctor && (bool) $this->user()?->can('changeStatus', $doctor);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in([DoctorStatus::Active->value, DoctorStatus::Inactive->value])],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.required' => 'Status dokter wajib dipilih.',
            'status.in' => 'Status dokter tidak valid.',
            'reason.string' => 'Alasan harus berupa teks.',
            'reason.max' => 'Alasan maksimal 255 karakter.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $doctor = $this->route('doctor');

            if (
                $doctor instanceof Doctor
                && $doctor->status === DoctorStatus::Archived
                && ! $validator->errors()->has('status')
                && $this->input('status') !== DoctorStatus::Inactive->value
            ) {
                $validator->errors()->add('status', 'Dokter yang diarsipkan hanya dapat dipulihkan menjadi nonaktif.');
            }
        });
    }

    public function reason(): ?string
    {
        $reason = $this->validated('reason');

        return is_string($reason) && trim($reason) !== '' ? trim($reason) : null;
    }

    public function targetStatus(): DoctorStatus
    {
        return DoctorStatus::from((string) $this->validated('status'));
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
