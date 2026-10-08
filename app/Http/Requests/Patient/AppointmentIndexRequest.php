<?php

namespace App\Http\Requests\Patient;

use App\Enums\AppointmentStatus;
use App\Http\Requests\ListFilterRequest;
use App\Models\Appointment;
use App\Models\PracticeSession;
use Illuminate\Database\Eloquent\Builder;

/**
 * Filter riwayat pendaftaran pasien (F6-R23, 07 W-12): `status` (AppointmentStatus), `dari`/`sampai` (`Y-m-d`,
 * inklusif pada `session_date`), `jadwal_berubah` (hanya nilai `1` yang berlaku; nilai lain diabaikan tanpa
 * peringatan, PAS-10). Nilai tidak valid atau larik diabaikan dengan flash `warning` (pola ListFilterRequest).
 */
class AppointmentIndexRequest extends ListFilterRequest
{
    /** Kunci filter yang ditampilkan kembali di formulir. */
    public const FILTER_KEYS = ['status', 'dari', 'sampai', 'jadwal_berubah'];

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'dari' => ['nullable', 'date_format:Y-m-d'],
            'sampai' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:dari'],
            'jadwal_berubah' => ['nullable', 'string', 'max:10'],
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'dari.date_format' => 'Format tanggal tidak valid.',
            'sampai.date_format' => 'Format tanggal tidak valid.',
            'sampai.after_or_equal' => 'Tanggal sampai tidak boleh sebelum tanggal dari.',
            'jadwal_berubah.string' => 'Pilihan filter tidak valid.',
            'jadwal_berubah.max' => 'Pilihan filter tidak valid.',
        ]);
    }

    /**
     * @return list<string>
     */
    protected function statusValues(): array
    {
        return AppointmentStatus::values();
    }

    /**
     * Filter yang benar-benar berlaku (untuk mengisi ulang formulir): hanya yang lolos validasi, dan
     * `jadwal_berubah` hanya bila bernilai `1`.
     *
     * @return array<string, string>
     */
    public function activeFilters(): array
    {
        $filters = array_intersect_key($this->filters(), array_flip(self::FILTER_KEYS));

        if (($filters['jadwal_berubah'] ?? null) !== '1') {
            unset($filters['jadwal_berubah']);
        }

        return $filters;
    }

    /**
     * Terapkan filter dan urutan: tanggal sesi menurun, lalu jam mulai menurun, lalu id menurun.
     *
     * @param  Builder<Appointment>  $query
     * @return Builder<Appointment>
     */
    public function applyTo(Builder $query): Builder
    {
        $filters = $this->activeFilters();

        if (isset($filters['status'])) {
            $query->where('appointments.status', $filters['status']);
        }

        if (isset($filters['dari'])) {
            $query->where('appointments.session_date', '>=', $filters['dari']);
        }

        if (isset($filters['sampai'])) {
            $query->where('appointments.session_date', '<=', $filters['sampai']);
        }

        if (isset($filters['jadwal_berubah'])) {
            $query->scheduleChanged();
        }

        return $query
            ->orderByDesc('appointments.session_date')
            ->orderByDesc(
                PracticeSession::query()->select('start_time')->whereColumn('practice_sessions.id', 'appointments.practice_session_id'),
            )
            ->orderByDesc('appointments.id');
    }
}
