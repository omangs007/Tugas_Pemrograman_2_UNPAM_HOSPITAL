<?php

namespace App\Http\Requests\Admin;

use App\Enums\DoctorStatus;
use App\Enums\SessionStatus;
use App\Models\PracticeSession;
use App\Models\ScheduleException;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * V-24: satu kelas untuk tambah sesi manual dan ubah sesi (dibedakan dari parameter route `session`).
 *
 * Saat ubah hanya jam dan kuota yang dapat diubah; dokter, poliklinik, dan tanggal tetap (09 §3.7).
 * Aturan yang juga mengikat Super Admin ada di sini dan di ScheduleChangeService: sesi lampau tidak diubah
 * (F3-R27, BR-33), kuota tidak di bawah booked_count (BR-31), dokter harus aktif (F3-R26), tanggal tidak
 * di dalam pengecualian dan jam tidak berbenturan (BR-29).
 */
class PracticeSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        $session = $this->practiceSession();

        return $session !== null
            ? $user->can('update', $session)
            : $user->can('create', PracticeSession::class);
    }

    /** Sesi yang sedang diubah, atau null pada pembuatan. */
    public function practiceSession(): ?PracticeSession
    {
        $session = $this->route('session');

        return $session instanceof PracticeSession ? $session : null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = [
            'start_time' => ['bail', 'required', 'date_format:H:i'],
            'end_time' => ['bail', 'required', 'date_format:H:i', 'after:start_time'],
            'quota' => ['bail', 'required', 'integer', 'between:1,200'],
        ];

        if ($this->practiceSession() !== null) {
            return $rules;
        }

        return [
            'doctor_id' => [
                'bail',
                'required',
                'integer',
                Rule::exists('doctors', 'id')->where('status', DoctorStatus::Active->value),
            ],
            'clinic_id' => [
                'bail',
                'required',
                'integer',
                Rule::exists('clinics', 'id')->where('is_active', true)->where('is_bookable', true),
            ],
            'session_date' => [
                'bail',
                'required',
                'date_format:Y-m-d',
                'after_or_equal:today',
                'before_or_equal:'.now()->addDays(365)->toDateString(),
            ],
        ] + $rules;
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
            'session_date.required' => 'Tanggal sesi wajib diisi.',
            'session_date.date_format' => 'Format tanggal tidak valid.',
            'session_date.after_or_equal' => 'Tanggal sesi tidak boleh di masa lalu.',
            'session_date.before_or_equal' => 'Tanggal sesi maksimal 365 hari ke depan.',
            'start_time.required' => 'Jam mulai wajib diisi.',
            'start_time.date_format' => 'Format jam mulai tidak valid. Gunakan format JJ:MM.',
            'end_time.required' => 'Jam selesai wajib diisi.',
            'end_time.date_format' => 'Format jam selesai tidak valid. Gunakan format JJ:MM.',
            'end_time.after' => 'Jam selesai harus setelah jam mulai.',
            'quota.required' => 'Kuota wajib diisi.',
            'quota.integer' => 'Kuota minimal 1 dan maksimal 200.',
            'quota.between' => 'Kuota minimal 1 dan maksimal 200.',
        ];
    }

    /**
     * Pemeriksaan yang membutuhkan data lain: sesi lampau, kuota di bawah booked_count, pengecualian, benturan jam.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $session = $this->practiceSession();

                if ($session !== null && $session->isEnded()) {
                    $validator->errors()->add('start_time', 'Sesi yang sudah lewat tidak dapat diubah.');

                    return;
                }

                $errors = $validator->errors();

                if ($session !== null && ! $errors->has('quota') && (int) $this->input('quota') < (int) $session->booked_count) {
                    $errors->add('quota', 'Kuota tidak boleh kurang dari jumlah pendaftaran yang sudah ada ('.(int) $session->booked_count.').');
                }

                $doctorId = $session !== null ? (int) $session->doctor_id : $this->numericInput('doctor_id');
                $date = $session !== null ? CarbonImmutable::parse($session->session_date->toDateString()) : $this->dateInput('session_date');

                if ($session === null && $doctorId !== null && $date !== null && ! $errors->hasAny(['doctor_id', 'session_date'])) {
                    $exceptions = ScheduleException::query()->covering($date)->forDoctor($doctorId)->get();
                    $covering = ScheduleException::pickFor($exceptions, $doctorId, $date);

                    if ($covering !== null) {
                        $errors->add('session_date', 'Tanggal ini berada dalam pengecualian jadwal ('.$covering->reason.'). Hapus atau ubah pengecualian terlebih dahulu.');
                    }
                }

                if ($doctorId === null || $date === null || $errors->hasAny(['doctor_id', 'session_date', 'start_time', 'end_time'])) {
                    return;
                }

                $overlaps = PracticeSession::query()
                    ->where('doctor_id', $doctorId)
                    ->where('session_date', $date->toDateString())
                    ->where('status', '!=', SessionStatus::Cancelled->value)
                    ->when($session !== null, fn ($query) => $query->where('id', '!=', $session?->id))
                    ->where('start_time', '<', $this->input('end_time').':00')
                    ->where('end_time', '>', $this->input('start_time').':00')
                    ->exists();

                if ($overlaps) {
                    $errors->add('start_time', 'Jam sesi bertabrakan dengan sesi lain milik dokter ini pada tanggal tersebut.');
                }
            },
        ];
    }

    /**
     * Atribut untuk ScheduleChangeService: tambah memakai enam kunci, ubah hanya jam dan kuota.
     *
     * @return array<string, mixed>
     */
    public function sessionAttributes(): array
    {
        $data = $this->validated();

        $attributes = [
            'start_time' => (string) $data['start_time'],
            'end_time' => (string) $data['end_time'],
            'quota' => (int) $data['quota'],
        ];

        if ($this->practiceSession() !== null) {
            return $attributes;
        }

        return [
            'doctor_id' => (int) $data['doctor_id'],
            'clinic_id' => (int) $data['clinic_id'],
            'session_date' => (string) $data['session_date'],
        ] + $attributes;
    }

    private function numericInput(string $key): ?int
    {
        $value = $this->input($key);

        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }

    private function dateInput(string $key): ?CarbonImmutable
    {
        $value = $this->input($key);

        if (! is_string($value)) {
            return null;
        }

        $date = CarbonImmutable::canBeCreatedFromFormat($value, '!Y-m-d') ? CarbonImmutable::createFromFormat('!Y-m-d', $value) : false;

        return $date !== false && $date->format('Y-m-d') === $value ? $date : null;
    }
}
