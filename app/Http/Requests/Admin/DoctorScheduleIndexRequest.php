<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\ListFilterRequest;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

/**
 * Filter daftar templat jadwal (F3-R22, 07 W-17): `dokter` (slug), `hari` (1-7), `status` aktif/nonaktif,
 * `urut` dokter/hari. Nilai tidak valid diabaikan oleh basis (12 V-45).
 */
class DoctorScheduleIndexRequest extends ListFilterRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'dokter' => ['nullable', 'string', 'max:150', Rule::exists('doctors', 'slug')],
            'hari' => ['nullable', 'integer', 'between:1,7'],
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'dokter.string' => 'Pilihan filter tidak valid.',
            'dokter.max' => 'Pilihan filter tidak valid.',
            'dokter.exists' => 'Pilihan filter tidak valid.',
            'hari.integer' => 'Hari tidak valid.',
            'hari.between' => 'Hari tidak valid.',
        ]);
    }

    /**
     * @return list<string>
     */
    protected function statusValues(): array
    {
        return ['aktif', 'nonaktif'];
    }

    /**
     * @return list<string>
     */
    protected function sortable(): array
    {
        return ['dokter', 'hari'];
    }

    /**
     * @param  Builder<DoctorSchedule>  $query
     * @return Builder<DoctorSchedule>
     */
    public function applyTo(Builder $query): Builder
    {
        $filters = $this->filters();
        $table = $query->getModel()->getTable();

        if (isset($filters['dokter'])) {
            $query->where($table.'.doctor_id', (int) Doctor::query()->where('slug', $filters['dokter'])->value('id'));
        }

        if (isset($filters['hari'])) {
            $query->where($table.'.day_of_week', (int) $filters['hari']);
        }

        $status = $filters['status'] ?? null;

        if ($status === 'aktif' || $status === 'nonaktif') {
            $query->where($table.'.is_active', $status === 'aktif');
        }

        $sort = $filters['urut'] ?? 'dokter';
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $doctorName = Doctor::query()->select('doctors.name')->whereColumn('doctors.id', $table.'.doctor_id');

        if (ltrim($sort, '-') === 'hari') {
            return $query
                ->orderBy($table.'.day_of_week', $direction)
                ->orderBy($table.'.start_time')
                ->orderBy($doctorName)
                ->orderBy($table.'.id');
        }

        return $query
            ->orderBy($doctorName, $direction)
            ->orderBy($table.'.day_of_week')
            ->orderBy($table.'.start_time')
            ->orderBy($table.'.id');
    }
}
