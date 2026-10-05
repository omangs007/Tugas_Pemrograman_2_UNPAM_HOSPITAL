<?php

namespace App\Http\Requests\Admin;

use App\Enums\SessionStatus;
use App\Http\Requests\ListFilterRequest;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\PracticeSession;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

/**
 * Filter daftar sesi (F3-R22, 12 V-46): `dari`/`sampai` (bawaan hari ini sampai hari ini + 13), `dokter` (slug),
 * `poli` (slug), `status` (SessionStatus). `sampai` sebelum `dari` diabaikan. Nilai tidak valid diabaikan.
 */
class PracticeSessionIndexRequest extends ListFilterRequest
{
    /** Lebar rentang bawaan: hari ini sampai hari ini + 13 (dua minggu). */
    public const DEFAULT_SPAN_DAYS = 13;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'dari' => ['nullable', 'date_format:Y-m-d'],
            'sampai' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:dari'],
            'dokter' => ['nullable', 'string', 'max:150', Rule::exists('doctors', 'slug')],
            'poli' => ['nullable', 'string', 'max:150', Rule::exists('clinics', 'slug')],
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
            'dokter.string' => 'Pilihan filter tidak valid.',
            'dokter.max' => 'Pilihan filter tidak valid.',
            'dokter.exists' => 'Pilihan filter tidak valid.',
            'poli.string' => 'Pilihan filter tidak valid.',
            'poli.max' => 'Pilihan filter tidak valid.',
            'poli.exists' => 'Pilihan filter tidak valid.',
        ]);
    }

    /**
     * @return list<string>
     */
    protected function statusValues(): array
    {
        return SessionStatus::values();
    }

    /**
     * Rentang tanggal yang berlaku: `dari` (bawaan hari ini) sampai `sampai` (bawaan `dari` + 13 hari).
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function range(): array
    {
        $filters = $this->filters();

        $from = isset($filters['dari'])
            ? CarbonImmutable::createFromFormat('!Y-m-d', $filters['dari'])
            : CarbonImmutable::today();

        $to = isset($filters['sampai'])
            ? CarbonImmutable::createFromFormat('!Y-m-d', $filters['sampai'])
            : $from->addDays(self::DEFAULT_SPAN_DAYS);

        return [$from, $to];
    }

    /**
     * @param  Builder<PracticeSession>  $query
     * @return Builder<PracticeSession>
     */
    public function applyTo(Builder $query): Builder
    {
        $filters = $this->filters();
        $table = $query->getModel()->getTable();
        [$from, $to] = $this->range();

        $query->whereBetween($table.'.session_date', [$from->toDateString(), $to->toDateString()]);

        if (isset($filters['dokter'])) {
            $query->where($table.'.doctor_id', (int) Doctor::query()->where('slug', $filters['dokter'])->value('id'));
        }

        if (isset($filters['poli'])) {
            $query->where($table.'.clinic_id', (int) Clinic::query()->where('slug', $filters['poli'])->value('id'));
        }

        if (isset($filters['status'])) {
            $query->where($table.'.status', $filters['status']);
        }

        return $query
            ->orderBy($table.'.session_date')
            ->orderBy($table.'.start_time')
            ->orderBy($table.'.id');
    }
}
