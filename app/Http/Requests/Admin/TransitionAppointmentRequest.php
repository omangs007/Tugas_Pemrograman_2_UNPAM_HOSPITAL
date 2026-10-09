<?php

namespace App\Http\Requests\Admin;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Services\Booking\AppointmentStatusService;
use App\Support\AdminReturn;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * V-15 (F7A-R18): POST `admin.appointments.transition`. `to` hanya konfirmasi, check-in, selesai, atau batal (`no_show` dan
 * `pending` tidak pernah per individu); `reason` 5 sampai 255 karakter, wajib untuk pembatalan dan koreksi check-in;
 * `expected_status` = status yang dilihat petugas saat halaman dimuat (anti aksi ganda, KON-05). Aturan jendela waktu dan
 * status ada di AppointmentStatusService. Modal sudah tertutup setelah kirim, jadi galat pertama juga dijadikan flash `error`.
 */
class TransitionAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $appointment = $this->route('appointment');

        return $appointment instanceof Appointment && (bool) $this->user()?->can('transition', $appointment);
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
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $targets = array_map(
            fn (AppointmentStatus $status): string => $status->value,
            AppointmentStatusService::INDIVIDUAL_TARGETS,
        );

        return [
            'to' => ['bail', 'required', 'string', Rule::in($targets)],
            'expected_status' => ['bail', 'required', 'string', Rule::in(AppointmentStatus::values())],
            'reason' => ['bail', 'nullable', Rule::requiredIf(fn (): bool => $this->requiresReason()), 'string', 'min:5', 'max:255'],
            // `kembali` larik/liar diabaikan oleh AdminReturn (F7A-R19), bukan galat validasi.
            'kembali' => ['nullable'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'to.required' => 'Status tujuan wajib dipilih.',
            'to.string' => 'Status tujuan tidak valid.',
            'to.in' => __('booking.admin.invalid_target'),
            'expected_status.required' => __('booking.transitions.STALE_STATUS'),
            'expected_status.string' => __('booking.transitions.STALE_STATUS'),
            'expected_status.in' => __('booking.transitions.STALE_STATUS'),
            'reason.required' => __('booking.transitions.REASON_REQUIRED'),
            'reason.string' => 'Alasan harus berupa teks.',
            'reason.min' => 'Alasan minimal 5 karakter.',
            'reason.max' => 'Alasan maksimal 255 karakter.',
        ];
    }

    /** Pembatalan, atau konfirmasi pada pendaftaran yang sudah check-in (koreksi). */
    private function requiresReason(): bool
    {
        $to = $this->input('to');

        if ($to === AppointmentStatus::Cancelled->value) {
            return true;
        }

        if ($to !== AppointmentStatus::Confirmed->value) {
            return false;
        }

        $appointment = $this->route('appointment');
        $current = $appointment instanceof Appointment ? $appointment->status : null;

        return $current === AppointmentStatus::CheckedIn || $this->input('expected_status') === AppointmentStatus::CheckedIn->value;
    }

    protected function failedValidation(Validator $validator): void
    {
        session()->flash('error', (string) $validator->errors()->first());

        parent::failedValidation($validator);
    }

    /** Galat validasi kembali ke `kembali` yang sah (detail atau antrean), selain itu ke detail pendaftaran. */
    protected function getRedirectUrl(): string
    {
        $appointment = $this->route('appointment');
        $fallback = $appointment instanceof Appointment
            ? route('admin.appointments.show', ['appointment' => $appointment->booking_code])
            : route('admin.appointments.index');

        return AdminReturn::path($this->returnTo(), $fallback);
    }

    public function target(): AppointmentStatus
    {
        return AppointmentStatus::from((string) $this->validated('to'));
    }

    public function expectedStatus(): AppointmentStatus
    {
        return AppointmentStatus::from((string) $this->validated('expected_status'));
    }

    public function reason(): ?string
    {
        $reason = $this->validated('reason');

        return is_string($reason) && $reason !== '' ? $reason : null;
    }

    /** `kembali` mentah bila berupa teks (belum disaring AdminReturn). */
    public function returnTo(): ?string
    {
        $value = $this->input('kembali');

        return is_string($value) ? $value : null;
    }
}
