<?php

namespace App\Http\Requests\Admin;

use App\Models\PracticeSession;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * V-16 (F7A-R23): POST `admin.queue.mark-no-show`. `confirm` wajib dicentang (pesan `booking.no_show.confirm_required`);
 * `reason` opsional, maksimal 255 karakter. Aturan sesi (`SESSION_NOT_ENDED`, `needs_review`) ada di
 * AppointmentStatusService dan ditangani handler global + BookingRedirect. Modal sudah tertutup setelah kirim, jadi
 * galat pertama juga dijadikan flash `error`; galat validasi kembali ke antrean sesi itu (`tanggal`, `sesi`).
 */
class MarkNoShowRequest extends FormRequest
{
    public function authorize(): bool
    {
        $session = $this->route('session');

        return $session instanceof PracticeSession && (bool) $this->user()?->can('markNoShow', $session);
    }

    protected function prepareForValidation(): void
    {
        $reason = $this->input('reason');

        if (is_string($reason)) {
            $reason = trim($reason);

            $this->merge(['reason' => $reason === '' ? null : $reason]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'confirm' => ['bail', 'accepted'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'confirm.accepted' => __('booking.no_show.confirm_required'),
            'reason.string' => 'Alasan harus berupa teks.',
            'reason.max' => 'Alasan maksimal 255 karakter.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        session()->flash('error', (string) $validator->errors()->first());

        parent::failedValidation($validator);
    }

    /** Galat validasi kembali ke antrean sesi yang sama, bukan ke halaman sebelumnya. */
    protected function getRedirectUrl(): string
    {
        $session = $this->route('session');

        return $session instanceof PracticeSession
            ? route('admin.queue.index', ['tanggal' => $session->session_date->toDateString(), 'sesi' => $session->id])
            : route('admin.queue.index');
    }

    public function reason(): ?string
    {
        $reason = $this->validated('reason');

        return is_string($reason) && $reason !== '' ? $reason : null;
    }
}
