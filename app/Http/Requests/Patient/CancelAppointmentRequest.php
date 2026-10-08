<?php

namespace App\Http\Requests\Patient;

use App\Models\Appointment;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Gate;

/**
 * Pembatalan pendaftaran oleh pasien (V-12, BR-73..BR-81). Otorisasi = `AppointmentPolicy::cancel` (pemilik; selain
 * itu 404 `denyAsNotFound`) dan dijalankan SEBELUM validasi, sehingga pasien lain tidak pernah melihat galat
 * validasi. Status dan batas waktu dijaga `AppointmentStatusService`, bukan di sini. Gagal validasi = redirect ke
 * halaman detail dengan flash `error` (halaman detail menampilkan flash, bukan galat per-field).
 */
class CancelAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $appointment = $this->route('appointment');

        if (! $appointment instanceof Appointment) {
            return false;
        }

        Gate::forUser($this->user())->authorize('cancel', $appointment);

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'cancel_reason' => ['bail', 'nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cancel_reason.string' => 'Alasan tidak valid.',
            'cancel_reason.max' => 'Alasan maksimal 255 karakter.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        $appointment = $this->route('appointment');
        $reason = $this->input('cancel_reason');

        throw new HttpResponseException(
            redirect()
                ->route('patient.appointments.show', $appointment->booking_code)
                ->with('error', (string) $validator->errors()->first())
                ->withInput(is_string($reason) ? ['cancel_reason' => $reason] : []),
        );
    }
}
