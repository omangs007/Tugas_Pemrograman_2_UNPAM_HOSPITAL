<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\ListFilterRequest;
use App\Services\Settings\SettingsService;
use Carbon\CarbonImmutable;
use Illuminate\Validation\Rule;

/**
 * Filter Antrean Hari Ini (F7A-R23, W-19, 12 V-46): `tanggal` (bawaan hari ini; boleh lampau sampai hari ini +
 * `booking.window_days`), `poli` dan `dokter` (slug), `sesi` (id sesi; hanya dipakai bila sesi itu ada pada tanggal
 * yang ditampilkan), `kode` (kode booking apa adanya; dinormalkan dan dicari controller). Nilai tidak valid atau larik
 * diabaikan dengan flash "Filter tidak valid dan diabaikan." (pola ListFilterRequest); tidak pernah 500.
 */
class QueueIndexRequest extends ListFilterRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $last = CarbonImmutable::today()
            ->addDays(max(1, app(SettingsService::class)->bookingRules()->windowDays))
            ->toDateString();

        return array_merge(parent::rules(), [
            'tanggal' => ['bail', 'nullable', 'date_format:Y-m-d', 'before_or_equal:'.$last],
            'poli' => ['bail', 'nullable', 'string', 'max:150', Rule::exists('clinics', 'slug')],
            'dokter' => ['bail', 'nullable', 'string', 'max:150', Rule::exists('doctors', 'slug')],
            'sesi' => ['bail', 'nullable', 'integer', 'min:1'],
            'kode' => ['bail', 'nullable', 'string', 'max:100'],
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'tanggal.date_format' => 'Format tanggal tidak valid.',
            'tanggal.before_or_equal' => 'Tanggal antrean melebihi batas pendaftaran.',
            'poli.string' => 'Pilihan filter tidak valid.',
            'poli.max' => 'Pilihan filter tidak valid.',
            'poli.exists' => 'Pilihan filter tidak valid.',
            'dokter.string' => 'Pilihan filter tidak valid.',
            'dokter.max' => 'Pilihan filter tidak valid.',
            'dokter.exists' => 'Pilihan filter tidak valid.',
            'sesi.integer' => 'Pilihan filter tidak valid.',
            'sesi.min' => 'Pilihan filter tidak valid.',
            'kode.string' => 'Kode booking tidak valid.',
            'kode.max' => 'Kode booking tidak valid.',
        ]);
    }

    /** Tanggal antrean yang ditampilkan; hari ini bila `tanggal` kosong atau tidak valid. */
    public function queueDate(): CarbonImmutable
    {
        $value = $this->filters()['tanggal'] ?? null;
        $date = $value === null ? null : CarbonImmutable::createFromFormat('!Y-m-d', $value);

        return $date instanceof CarbonImmutable ? $date : CarbonImmutable::today();
    }

    public function clinicSlug(): ?string
    {
        return $this->filters()['poli'] ?? null;
    }

    public function doctorSlug(): ?string
    {
        return $this->filters()['dokter'] ?? null;
    }

    /** Id sesi yang diminta (belum tentu ada pada tanggal itu). */
    public function sessionId(): ?int
    {
        $value = $this->filters()['sesi'] ?? null;

        return $value === null ? null : (int) $value;
    }

    /** Kode yang diketik atau dipindai (dipangkas), atau null bila kosong. Belum dinormalkan. */
    public function bookingCode(): ?string
    {
        $value = trim($this->filters()['kode'] ?? '');

        return $value === '' ? null : $value;
    }
}
