<?php

namespace App\Http\Requests\Patient;

use App\Enums\ClinicType;
use App\Enums\DoctorStatus;
use App\Services\Settings\SettingsService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

/**
 * V-09 (F6-R19): query `poli`, `dokter`, `sesi`, `tanggal` pada GET `patient.appointments.create`. BUKAN turunan
 * ListFilterRequest: kegagalan apa pun (nilai larik, tidak ada, nonaktif, di luar jendela) mengalihkan ke langkah 1
 * (tanpa query) dengan flash `warning`, tidak pernah 500 dan tidak pernah mengulang query yang rusak. `dokter` tanpa
 * `poli` dan tanpa `sesi` menghasilkan pesan khusus.
 */
class BookingWizardQueryRequest extends FormRequest
{
    public const MESSAGE_INVALID = 'Poliklinik, dokter, atau sesi yang dipilih tidak tersedia. Silakan pilih kembali.';

    public const MESSAGE_CLINIC_FIRST = 'Pilih poliklinik terlebih dahulu.';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Tanggal batas dihitung eksplisit dari `now()` aplikasi (kata kunci `today` pada aturan tanggal memakai waktu nyata).
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $today = CarbonImmutable::today();
        $last = $today->addDays(max(1, app(SettingsService::class)->bookingRules()->windowDays));

        return [
            'poli' => [
                'bail', 'nullable', 'string', 'max:100',
                Rule::exists('clinics', 'slug')
                    ->where('is_active', true)
                    ->where('is_bookable', true)
                    ->where('type', ClinicType::Poliklinik->value),
            ],
            'dokter' => [
                'bail', 'nullable', 'string', 'max:150',
                Rule::exists('doctors', 'slug')->where('status', DoctorStatus::Active->value),
            ],
            'sesi' => ['bail', 'nullable', 'integer', 'min:1', Rule::exists('practice_sessions', 'id')],
            'tanggal' => [
                'bail', 'nullable', 'date_format:Y-m-d',
                'after_or_equal:'.$today->toDateString(),
                'before_or_equal:'.$last->toDateString(),
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $dokter = $this->input('dokter');

            if (
                $validator->errors()->isEmpty()
                && is_string($dokter) && $dokter !== ''
                && ! $this->filled('poli') && ! $this->filled('sesi')
            ) {
                $validator->errors()->add('dokter', self::MESSAGE_CLINIC_FIRST);
            }
        });
    }

    protected function failedValidation(Validator $validator): void
    {
        $message = $validator->errors()->all() === [self::MESSAGE_CLINIC_FIRST]
            ? self::MESSAGE_CLINIC_FIRST
            : self::MESSAGE_INVALID;

        throw new HttpResponseException(
            redirect()->route('patient.appointments.create')->with('warning', $message),
        );
    }

    /**
     * Nilai yang lolos validasi; yang tidak diisi atau kosong menjadi null.
     *
     * @return array{poli: ?string, dokter: ?string, sesi: ?int, tanggal: ?string}
     */
    public function filters(): array
    {
        $validated = $this->validated();

        return [
            'poli' => ($validated['poli'] ?? null) ?: null,
            'dokter' => ($validated['dokter'] ?? null) ?: null,
            'sesi' => isset($validated['sesi']) && $validated['sesi'] !== '' ? (int) $validated['sesi'] : null,
            'tanggal' => ($validated['tanggal'] ?? null) ?: null,
        ];
    }
}
