<?php

namespace App\Http\Requests\Admin;

use App\Enums\DoctorStatus;
use App\Models\DoctorSchedule;
use App\Rules\NoOverlappingSchedule;
use App\Services\Scheduling\ScheduleChangeService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;

/**
 * V-23: satu kelas untuk tambah dan ubah templat (dibedakan dari parameter route `schedule`).
 *
 * Aturan yang tidak boleh dilewati siapa pun, termasuk Super Admin, ada di sini dan di service:
 * dokter arsip tidak diberi templat (BR-06), tidak bentrok (BR-08), ganti poliklinik ditolak bila ada sesi
 * mendatang berpendaftaran aktif (BR-10). Dokter pada templat yang sudah ada tidak dapat diganti, karena
 * propagasi templat (00 F.4) tidak memindahkan sesi antar dokter.
 */
class DoctorScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        $schedule = $this->schedule();

        return $schedule !== null
            ? $user->can('update', $schedule)
            : $user->can('create', DoctorSchedule::class);
    }

    /** Templat yang sedang diubah, atau null pada pembuatan. */
    public function schedule(): ?DoctorSchedule
    {
        $schedule = $this->route('schedule');

        return $schedule instanceof DoctorSchedule ? $schedule : null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $schedule = $this->schedule();

        $validFrom = ['required', 'date_format:Y-m-d'];

        // Poliklinik yang sudah dipakai templat tetap boleh dipertahankan walau kemudian nonaktif atau tidak dibuka
        // untuk pendaftaran; aturan penuh hanya untuk tambah dan untuk ganti poliklinik.
        $clinicId = $this->input('clinic_id');
        $keepsCurrentClinic = $schedule !== null
            && is_numeric($clinicId)
            && (int) $clinicId === (int) $schedule->clinic_id;
        $clinicExists = $keepsCurrentClinic
            ? Rule::exists('clinics', 'id')
            : Rule::exists('clinics', 'id')->where('is_active', true)->where('is_bookable', true);

        if ($schedule === null) {
            $validFrom[] = 'after_or_equal:today';
        }

        return [
            'doctor_id' => [
                'bail',
                'required',
                'integer',
                Rule::exists('doctors', 'id')->whereNot('status', DoctorStatus::Archived->value),
            ],
            'clinic_id' => [
                'bail',
                'required',
                'integer',
                $clinicExists,
            ],
            'day_of_week' => ['bail', 'required', 'integer', 'between:1,7'],
            'start_time' => [
                'bail',
                'required',
                'date_format:H:i',
                new NoOverlappingSchedule($this->doctorId(), $schedule?->id),
            ],
            'end_time' => ['bail', 'required', 'date_format:H:i', 'after:start_time'],
            'quota' => ['bail', 'required', 'integer', 'between:1,200'],
            'valid_from' => $validFrom,
            'valid_until' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:valid_from'],
            'is_active' => ['required', 'boolean'],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'doctor_id.required' => 'Dokter wajib dipilih.',
            'doctor_id.integer' => 'Dokter yang dipilih tidak tersedia.',
            'doctor_id.exists' => 'Dokter yang dipilih tidak tersedia.',
            'clinic_id.required' => 'Poliklinik wajib dipilih.',
            'clinic_id.integer' => 'Poliklinik yang dipilih tidak tersedia atau tidak dibuka untuk pendaftaran.',
            'clinic_id.exists' => 'Poliklinik yang dipilih tidak tersedia atau tidak dibuka untuk pendaftaran.',
            'day_of_week.required' => 'Hari wajib dipilih.',
            'day_of_week.integer' => 'Hari tidak valid.',
            'day_of_week.between' => 'Hari tidak valid.',
            'start_time.required' => 'Jam mulai wajib diisi.',
            'start_time.date_format' => 'Format jam mulai tidak valid. Gunakan format JJ:MM.',
            'end_time.required' => 'Jam selesai wajib diisi.',
            'end_time.date_format' => 'Format jam selesai tidak valid. Gunakan format JJ:MM.',
            'end_time.after' => 'Jam selesai harus setelah jam mulai.',
            'quota.required' => 'Kuota wajib diisi.',
            'quota.integer' => 'Kuota harus berupa angka bulat.',
            'quota.between' => 'Kuota minimal 1 dan maksimal 200.',
            'valid_from.required' => 'Tanggal mulai berlaku wajib diisi.',
            'valid_from.date_format' => 'Format tanggal tidak valid.',
            'valid_from.after_or_equal' => 'Tanggal mulai berlaku tidak boleh di masa lalu.',
            'valid_until.date_format' => 'Format tanggal tidak valid.',
            'valid_until.after_or_equal' => 'Tanggal berlaku sampai tidak boleh sebelum tanggal mulai berlaku.',
            'is_active.required' => 'Status aktif tidak valid.',
            'is_active.boolean' => 'Status aktif tidak valid.',
            'notes.string' => 'Catatan harus berupa teks.',
            'notes.max' => 'Catatan maksimal 255 karakter.',
        ];
    }

    /**
     * Pemeriksaan yang bergantung pada templat yang sudah ada (hanya saat ubah).
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $schedule = $this->schedule();

                if ($schedule === null) {
                    return;
                }

                if (
                    ! $validator->errors()->has('doctor_id')
                    && is_numeric($this->input('doctor_id'))
                    && (int) $this->input('doctor_id') !== (int) $schedule->doctor_id
                ) {
                    $validator->errors()->add('doctor_id', 'Dokter pada templat tidak dapat diganti. Buat templat baru, lalu nonaktifkan templat lama.');
                }

                $clinicId = $this->input('clinic_id');

                if (
                    $validator->errors()->has('clinic_id')
                    || ! is_numeric($clinicId)
                    || (int) $clinicId === (int) $schedule->clinic_id
                ) {
                    return;
                }

                try {
                    app(ScheduleChangeService::class)->assertClinicChangeAllowed($schedule, (int) $clinicId);
                } catch (ValidationException $exception) {
                    foreach ($exception->errors() as $field => $messages) {
                        foreach ($messages as $message) {
                            $validator->errors()->add($field, $message);
                        }
                    }
                }
            },
        ];
    }

    /**
     * Atribut model hasil validasi. `doctor_id` hanya dipakai saat tambah (tidak dapat diganti saat ubah).
     *
     * @return array<string, mixed>
     */
    public function attributesForModel(): array
    {
        $data = $this->validated();

        $attributes = [
            'doctor_id' => (int) $data['doctor_id'],
            'clinic_id' => (int) $data['clinic_id'],
            'day_of_week' => (int) $data['day_of_week'],
            'start_time' => (string) $data['start_time'],
            'end_time' => (string) $data['end_time'],
            'quota' => (int) $data['quota'],
            'valid_from' => (string) $data['valid_from'],
            'valid_until' => $this->blankToNull($data['valid_until'] ?? null),
            'is_active' => (bool) $data['is_active'],
            'notes' => $this->blankToNull($data['notes'] ?? null),
        ];

        if ($this->schedule() !== null) {
            unset($attributes['doctor_id']);
        }

        return $attributes;
    }

    private function blankToNull(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function doctorId(): ?int
    {
        $value = $this->input('doctor_id');

        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }
}
