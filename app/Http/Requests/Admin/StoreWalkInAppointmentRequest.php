<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Patient\Concerns\HasBookingRules;
use App\Models\Appointment;
use App\Models\PatientProfile;
use App\Models\User;
use App\Support\Actor;
use App\Support\WalkInReturn;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * V-14 (F7A-R21): POST `admin.appointments.store`. `patient_profile_id` (profil AKTIF) dan `practice_session_id`, lalu aturan
 * pembayaran yang sama dengan sisi pasien (trait HasBookingRules: `payment_type`, `bpjs_number`, `insurance_partner_id`,
 * `policy_number`, `complaint`, `save_bpjs`). Setelah semua field lolos, `BookingService::precheck()` dijalankan dengan
 * aktor petugas (kanal loket: `now < end`, batas aktif tidak berlaku, duplikat dan benturan tetap diperiksa). Kolom sistem
 * (`status`, `channel`, `queue_number`, `booking_code`) tidak pernah dibaca dari input.
 */
class StoreWalkInAppointmentRequest extends FormRequest
{
    use HasBookingRules;

    private ?PatientProfile $selectedProfile = null;

    private bool $profileResolved = false;

    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->can('createCounter', Appointment::class);
    }

    protected function prepareForValidation(): void
    {
        $this->prepareBookingInput();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge($this->bookingRules(), [
            'patient_profile_id' => [
                'bail', 'required', 'integer', 'min:1',
                Rule::exists('patient_profiles', 'id')->where('is_active', true),
            ],
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge($this->bookingMessages(), [
            'patient_profile_id.required' => 'Pasien wajib dipilih.',
            'patient_profile_id.integer' => 'Profil pasien tidak aktif atau tidak ditemukan.',
            'patient_profile_id.min' => 'Profil pasien tidak aktif atau tidak ditemukan.',
            'patient_profile_id.exists' => 'Profil pasien tidak aktif atau tidak ditemukan.',
        ]);
    }

    /** Profil pilihan petugas (aktif); dipakai nomor BPJS tersimpan dan precheck. */
    protected function bookingProfile(): ?PatientProfile
    {
        if (! $this->profileResolved) {
            $this->profileResolved = true;
            $id = $this->input('patient_profile_id');

            if ((is_string($id) || is_int($id)) && preg_match('/^[1-9][0-9]{0,17}$/', (string) $id) === 1) {
                $this->selectedProfile = PatientProfile::query()->whereKey((int) $id)->where('is_active', true)->first();
            }
        }

        return $this->selectedProfile;
    }

    protected function bookingActor(): ?Actor
    {
        $user = $this->user();

        return $user instanceof User ? Actor::staff($user) : null;
    }

    /**
     * Kegagalan validasi kembali ke form walk-in: galat bagian 1 (profil) tanpa `pasien`; galat sesi (termasuk hasil
     * `precheck`) ke bagian 2 tanpa `sesi`; galat pembayaran ke bagian 3 dengan `sesi`. Tidak pernah back().
     */
    protected function getRedirectUrl(): string
    {
        $errors = $this->validator?->errors();

        if ($errors !== null && $errors->has('patient_profile_id')) {
            return WalkInReturn::url(null, $this->input('practice_session_id'), false);
        }

        $sessionLevel = $errors !== null && $errors->has('practice_session_id');

        return WalkInReturn::url($this->input('patient_profile_id'), $this->input('practice_session_id'), ! $sessionLevel);
    }
}
