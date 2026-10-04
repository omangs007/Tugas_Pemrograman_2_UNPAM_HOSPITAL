<?php

namespace App\Http\Requests\Admin;

use App\Models\Doctor;
use App\Services\Doctors\DoctorService;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * V-20 (arsip), kelas terpisah dari UpdateDoctorStatusRequest (00 J.6). Arsip ditolak bila masih ada
 * appointment aktif pada sesi mendatang (00 F.4, BR-05); pemeriksaan di sini berlaku juga untuk Super Admin.
 */
class ArchiveDoctorRequest extends FormRequest
{
    public function authorize(): bool
    {
        $doctor = $this->route('doctor');

        return $doctor instanceof Doctor && (bool) $this->user()?->can('archive', $doctor);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return ['reason' => ['nullable', 'string', 'max:255']];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.string' => 'Alasan harus berupa teks.',
            'reason.max' => 'Alasan maksimal 255 karakter.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $doctor = $this->route('doctor');

            if (! $doctor instanceof Doctor) {
                return;
            }

            $blocked = $this->blockedReason($doctor);

            if ($blocked !== null) {
                $validator->errors()->add('status', $blocked);
            }
        });
    }

    public function reason(): ?string
    {
        $reason = $this->validated('reason');

        return is_string($reason) && trim($reason) !== '' ? trim($reason) : null;
    }

    /**
     * Alasan arsip ditolak (Task 8); fase 2 selalu null (F2-R8). Tes menimpa method ini lewat subkelas
     * karena DoctorService bersifat final.
     */
    protected function blockedReason(Doctor $doctor): ?string
    {
        return app(DoctorService::class)->archiveBlockedReason($doctor);
    }

    protected function failedValidation(ValidatorContract $validator): void
    {
        session()->flash('error', (string) $validator->errors()->first());

        parent::failedValidation($validator);
    }
}
