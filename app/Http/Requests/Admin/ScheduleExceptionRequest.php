<?php

namespace App\Http\Requests\Admin;

use App\Enums\DoctorStatus;
use App\Enums\ExceptionType;
use App\Models\ScheduleException;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * V-25: satu kelas untuk tambah dan ubah pengecualian (dibedakan dari parameter route `exception`).
 *
 * Kombinasi jenis dan dokter menurut 00 E.2 / BR-25 ditegakkan di sini, di service, dan oleh CHECK
 * `chk_schedule_exceptions_scope`. F3-R21: `start_date` yang tidak berubah boleh tetap di masa lalu saat ubah.
 */
class ScheduleExceptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        $exception = $this->exception();

        return $exception !== null
            ? $user->can('update', $exception)
            : $user->can('create', ScheduleException::class);
    }

    /** Pengecualian yang sedang diubah, atau null pada pembuatan. */
    public function exception(): ?ScheduleException
    {
        $exception = $this->route('exception');

        return $exception instanceof ScheduleException ? $exception : null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $exception = $this->exception();

        $startDate = ['bail', 'required', 'date_format:Y-m-d'];

        if ($exception === null || $this->input('start_date') !== $exception->start_date->toDateString()) {
            $startDate[] = 'after_or_equal:today';
        }

        $endDate = ['bail', 'required', 'date_format:Y-m-d', 'after_or_equal:start_date'];
        $limit = $this->maxEndDate();

        if ($limit !== null) {
            $endDate[] = 'before_or_equal:'.$limit;
        }

        return [
            'type' => ['bail', 'required', Rule::enum(ExceptionType::class)],
            'doctor_id' => [
                'bail',
                'nullable',
                'integer',
                Rule::exists('doctors', 'id')->whereNot('status', DoctorStatus::Archived->value),
                Rule::requiredIf(fn (): bool => $this->input('type') === ExceptionType::DoctorLeave->value),
                Rule::prohibitedIf(fn (): bool => in_array($this->input('type'), [
                    ExceptionType::HospitalHoliday->value,
                    ExceptionType::NationalHoliday->value,
                ], true)),
            ],
            'start_date' => $startDate,
            'end_date' => $endDate,
            'reason' => ['bail', 'required', 'string', 'min:3', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'type.required' => 'Jenis pengecualian wajib dipilih.',
            'type.enum' => 'Jenis pengecualian tidak valid.',
            'doctor_id.required' => 'Pilih dokter untuk cuti dokter.',
            'doctor_id.prohibited' => 'Libur rumah sakit atau libur nasional berlaku untuk semua dokter. Kosongkan pilihan dokter.',
            'doctor_id.integer' => 'Dokter yang dipilih tidak tersedia.',
            'doctor_id.exists' => 'Dokter yang dipilih tidak tersedia.',
            'start_date.required' => 'Tanggal mulai wajib diisi.',
            'start_date.date_format' => 'Format tanggal tidak valid.',
            'start_date.after_or_equal' => 'Tanggal mulai tidak boleh di masa lalu.',
            'end_date.required' => 'Tanggal selesai wajib diisi.',
            'end_date.date_format' => 'Format tanggal tidak valid.',
            'end_date.after_or_equal' => 'Tanggal selesai tidak boleh sebelum tanggal mulai.',
            'end_date.before_or_equal' => 'Rentang pengecualian maksimal 365 hari.',
            'reason.required' => 'Alasan wajib diisi.',
            'reason.string' => 'Alasan harus berupa teks.',
            'reason.min' => 'Alasan minimal 3 karakter.',
            'reason.max' => 'Alasan maksimal 255 karakter.',
        ];
    }

    /**
     * Atribut model hasil validasi. `doctor_id` NULL berarti seluruh dokter/RS.
     *
     * @return array{type: string, doctor_id: ?int, start_date: string, end_date: string, reason: string}
     */
    public function attributesForModel(): array
    {
        $data = $this->validated();

        return [
            'type' => (string) $data['type'],
            'doctor_id' => is_numeric($data['doctor_id'] ?? null) ? (int) $data['doctor_id'] : null,
            'start_date' => (string) $data['start_date'],
            'end_date' => (string) $data['end_date'],
            'reason' => trim((string) $data['reason']),
        ];
    }

    /** Batas `end_date` (BR-26: maksimal 365 hari dari `start_date`), atau null bila `start_date` belum valid. */
    private function maxEndDate(): ?string
    {
        $start = $this->input('start_date');

        if (! is_string($start)) {
            return null;
        }

        $date = CarbonImmutable::canBeCreatedFromFormat($start, '!Y-m-d') ? CarbonImmutable::createFromFormat('!Y-m-d', $start) : false;

        return $date !== false && $date->format('Y-m-d') === $start ? $date->addDays(365)->toDateString() : null;
    }
}
