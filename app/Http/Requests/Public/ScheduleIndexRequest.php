<?php

declare(strict_types=1);

namespace App\Http\Requests\Public;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Filter halaman #8 Jadwal Dokter (05 hal. 8, 00 I.5): `minggu` (tanggal mana pun dalam minggu, `YYYY-MM-DD`),
 * `poli` dan `dokter` (slug). GET-filter longgar (12 V-45, VAL-07): nilai yang tidak lolos aturan diabaikan.
 * Penormalan `minggu` ke hari Senin dan pembatasan pada jendela pendaftaran dilakukan controller (F3-R24).
 */
final class ScheduleIndexRequest extends FormRequest
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
        return [
            'minggu' => ['nullable', 'date_format:Y-m-d'],
            'poli' => ['nullable', 'string', 'max:170'],
            'dokter' => ['nullable', 'string', 'max:170'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'minggu.date_format' => 'Format minggu tidak valid. Menampilkan minggu ini.',
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
     * Hanya parameter yang lolos aturan (Validator::valid() mengembalikan seluruh input dikurangi yang gagal,
     * sehingga hasilnya diiris ke kunci yang beraturan).
     *
     * @return array<string, string>
     */
    public function filters(): array
    {
        $valid = $this->getValidatorInstance()->valid();
        $filters = [];

        foreach (['minggu', 'poli', 'dokter'] as $key) {
            $value = $valid[$key] ?? null;

            if (is_string($value) && trim($value) !== '') {
                $filters[$key] = trim($value);
            }
        }

        return $filters;
    }

    /** Pesan galat `minggu` untuk ditampilkan di atas jadwal, atau null bila kosong atau valid. */
    public function weekError(): ?string
    {
        $message = $this->getValidatorInstance()->errors()->first('minggu');

        return $message === '' ? null : $message;
    }
}
