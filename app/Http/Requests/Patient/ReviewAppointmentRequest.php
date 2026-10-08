<?php

namespace App\Http\Requests\Patient;

use App\Http\Requests\Patient\Concerns\HasBookingRules;
use App\Models\Appointment;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * V-10: POST `patient.appointments.review` (langkah 5, tidak menyimpan apa pun). Hanya akun pasien aktif dengan profil
 * aktif (AppointmentPolicy::createOnline); kegagalan validasi dan galat `precheck` dialihkan ke `create?sesi=`.
 */
class ReviewAppointmentRequest extends FormRequest
{
    use HasBookingRules;

    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->can('createOnline', Appointment::class);
    }

    protected function prepareForValidation(): void
    {
        $this->prepareBookingInput();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return $this->bookingRules();
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return $this->bookingMessages();
    }
}
