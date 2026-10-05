<?php

declare(strict_types=1);

namespace App\Http\Requests\Public;

use App\Services\Settings\SettingsService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Filter halaman #6 Daftar Dokter (05 hal. 6, 00 I.5): `q`, `spesialisasi` (slug), `poli` (slug), `tanggal`.
 * GET-filter longgar (12 V-45, VAL-07): nilai yang tidak lolos aturan diabaikan, tidak pernah menjadi galat 500
 * atau pengalihan; hanya `tanggal` yang gagal diberi pesan inline (`dateError()`).
 */
final class DoctorIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        $today = CarbonImmutable::today();
        $last = $today->addDays($this->windowDays());

        return [
            'q' => ['nullable', 'string', 'max:100'],
            'spesialisasi' => ['nullable', 'string', 'max:170'],
            'poli' => ['nullable', 'string', 'max:170'],
            'tanggal' => ['bail', 'nullable', 'date_format:Y-m-d', 'after_or_equal:'.$today->toDateString(), 'before_or_equal:'.$last->toDateString()],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'q.string' => 'Kata kunci tidak valid.',
            'q.max' => 'Kata kunci maksimal 100 karakter.',
            'tanggal.date_format' => 'Format tanggal tidak valid. Gunakan format TTTT-BB-HH.',
            'tanggal.after_or_equal' => 'Tanggal praktik tidak boleh sebelum hari ini.',
            'tanggal.before_or_equal' => "Tanggal praktik maksimal {$this->windowDays()} hari ke depan.",
        ];
    }

    /**
     * Tidak melempar: filter yang tidak valid diabaikan dan halaman tetap tampil (VAL-07).
     */
    protected function failedValidation(Validator $validator): void
    {
        //
    }

    /**
     * Hanya parameter yang lolos aturan (tanpa `page`, yang dibaca paginator sendiri). Validator::valid()
     * mengembalikan seluruh input dikurangi yang gagal, sehingga hasilnya diiris ke kunci yang beraturan.
     *
     * @return array<string, string>
     */
    public function filters(): array
    {
        $valid = $this->getValidatorInstance()->valid();
        $filters = [];

        foreach (['q', 'spesialisasi', 'poli', 'tanggal'] as $key) {
            $value = $valid[$key] ?? null;

            if (is_string($value) && trim($value) !== '') {
                $filters[$key] = trim($value);
            }
        }

        return $filters;
    }

    /** Pesan galat `tanggal` untuk ditampilkan di bawah field, atau null bila tanggal kosong atau valid. */
    public function dateError(): ?string
    {
        $message = $this->getValidatorInstance()->errors()->first('tanggal');

        return $message === '' ? null : $message;
    }

    /** Jendela pendaftaran dalam hari (`booking.window_days`, minimal 1). */
    private function windowDays(): int
    {
        return max(1, app(SettingsService::class)->bookingRules()->windowDays);
    }
}
