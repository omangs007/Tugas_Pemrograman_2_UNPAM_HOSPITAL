<?php

namespace App\Http\Requests\Admin;

use App\Enums\SessionStatus;
use App\Models\PracticeSession;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * V-24 (PATCH admin.sessions.status): `status` open/closed/cancelled; `reason` wajib dan minimal 5 karakter
 * bila `cancelled` (F3-R20/T9). Sesi lampau tidak dapat diubah statusnya (F3-R27), juga oleh Super Admin.
 * `needs_review` tidak dapat dipilih: status itu hanya ditetapkan ScheduleChangeService.
 */
class UpdateSessionStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $session = $this->route('session');

        return $session instanceof PracticeSession && (bool) $this->user()?->can('changeStatus', $session);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'status' => ['bail', 'required', Rule::in([
                SessionStatus::Open->value,
                SessionStatus::Closed->value,
                SessionStatus::Cancelled->value,
            ])],
            'reason' => [
                'nullable',
                Rule::requiredIf(fn (): bool => $this->input('status') === SessionStatus::Cancelled->value),
                'string',
                'min:5',
                'max:255',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.required' => 'Status sesi wajib dipilih.',
            'status.in' => 'Status sesi tidak valid.',
            'reason.required' => 'Alasan pembatalan sesi wajib diisi.',
            'reason.string' => 'Alasan harus berupa teks.',
            'reason.min' => 'Alasan minimal 5 karakter.',
            'reason.max' => 'Alasan maksimal 255 karakter.',
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $session = $this->route('session');

                if ($session instanceof PracticeSession && $session->isEnded()) {
                    $validator->errors()->add('status', 'Sesi yang sudah lewat tidak dapat diubah.');
                }
            },
        ];
    }

    public function targetStatus(): SessionStatus
    {
        return SessionStatus::from((string) $this->validated('status'));
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
