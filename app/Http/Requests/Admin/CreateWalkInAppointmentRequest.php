<?php

namespace App\Http\Requests\Admin;

use App\Enums\DoctorStatus;
use App\Http\Requests\ListFilterRequest;
use App\Services\Settings\SettingsService;
use Carbon\CarbonImmutable;
use Illuminate\Validation\Rule;

/**
 * Query form walk-in (F7A-R21, 12 V-46): `pasien` (id profil), `cari` (pencarian pasien), `poli` dan `dokter` (slug),
 * `tanggal` (hari ini sampai hari ini + `booking.window_days`), `sesi` (id sesi), `kembali`. BUKAN pengalihan galat:
 * turunan ListFilterRequest, nilai tidak valid atau larik diabaikan dengan peringatan, tidak pernah 500. Keberadaan dan
 * keaktifan profil serta kelayakan sesi diperiksa controller (hasilnya peringatan, bukan galat validasi).
 */
class CreateWalkInAppointmentRequest extends ListFilterRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $today = CarbonImmutable::today();
        $last = $today->addDays(max(1, app(SettingsService::class)->bookingRules()->windowDays));

        return array_merge(parent::rules(), [
            'pasien' => ['nullable', 'integer', 'min:1', Rule::exists('patient_profiles', 'id')],
            'cari' => ['nullable', 'string', 'max:100'],
            'poli' => [
                'nullable', 'string', 'max:150',
                Rule::exists('clinics', 'slug')->where('is_active', true)->where('is_bookable', true),
            ],
            'dokter' => ['nullable', 'string', 'max:150', Rule::exists('doctors', 'slug')->where('status', DoctorStatus::Active->value)],
            'tanggal' => [
                'nullable', 'date_format:Y-m-d',
                'after_or_equal:'.$today->toDateString(),
                'before_or_equal:'.$last->toDateString(),
            ],
            'sesi' => ['nullable', 'integer', 'min:1', Rule::exists('practice_sessions', 'id')],
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'pasien.integer' => 'Pilihan filter tidak valid.',
            'pasien.min' => 'Pilihan filter tidak valid.',
            'pasien.exists' => 'Pilihan filter tidak valid.',
            'cari.string' => 'Kata kunci tidak valid.',
            'cari.max' => 'Kata kunci maksimal 100 karakter.',
            'poli.string' => 'Pilihan filter tidak valid.',
            'poli.max' => 'Pilihan filter tidak valid.',
            'poli.exists' => 'Pilihan filter tidak valid.',
            'dokter.string' => 'Pilihan filter tidak valid.',
            'dokter.max' => 'Pilihan filter tidak valid.',
            'dokter.exists' => 'Pilihan filter tidak valid.',
            'tanggal.date_format' => 'Format tanggal tidak valid.',
            'tanggal.after_or_equal' => 'Tanggal tidak valid.',
            'tanggal.before_or_equal' => 'Tanggal tidak valid.',
            'sesi.integer' => 'Pilihan filter tidak valid.',
            'sesi.min' => 'Pilihan filter tidak valid.',
            'sesi.exists' => 'Pilihan filter tidak valid.',
        ]);
    }
}
