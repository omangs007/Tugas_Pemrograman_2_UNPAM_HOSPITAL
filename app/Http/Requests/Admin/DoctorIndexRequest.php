<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\ListFilterRequest;
use App\Models\Clinic;
use App\Models\Doctor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

/**
 * Filter daftar dokter admin (F2-R25, 07 W-15): `q`, `status` (default `active`), `poli`, `spesialisasi`, `urut`.
 * Nilai tidak valid diabaikan oleh basis (12 V-45).
 */
class DoctorIndexRequest extends ListFilterRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'poli' => ['nullable', 'string', 'max:150', Rule::exists('clinics', 'slug')],
            'spesialisasi' => ['nullable', 'string', 'max:150', Rule::exists('specialties', 'slug')],
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'poli.string' => 'Pilihan filter tidak valid.',
            'poli.max' => 'Pilihan filter tidak valid.',
            'poli.exists' => 'Pilihan filter tidak valid.',
            'spesialisasi.string' => 'Pilihan filter tidak valid.',
            'spesialisasi.max' => 'Pilihan filter tidak valid.',
            'spesialisasi.exists' => 'Pilihan filter tidak valid.',
        ]);
    }

    /**
     * @return list<string>
     */
    protected function statusValues(): array
    {
        return ['active', 'inactive', 'archived', 'semua'];
    }

    /**
     * @return list<string>
     */
    protected function sortable(): array
    {
        return ['nama', 'terbaru', 'urutan'];
    }

    /** Status yang berlaku: `active` bila tidak ada atau tidak valid. */
    public function statusFilter(): string
    {
        return $this->filters()['status'] ?? 'active';
    }

    /**
     * @param  Builder<Doctor>  $query
     * @return Builder<Doctor>
     */
    public function applyTo(Builder $query): Builder
    {
        $filters = $this->filters();
        $model = $query->getModel();

        $status = $this->statusFilter();

        if ($status !== 'semua') {
            $query->where($model->qualifyColumn('status'), $status);
        }

        if (isset($filters['q'])) {
            $query->search($filters['q']);
        }

        if (isset($filters['poli'])) {
            $query->inClinic((int) Clinic::query()->where('slug', $filters['poli'])->value('id'));
        }

        if (isset($filters['spesialisasi'])) {
            $query->inSpecialty($filters['spesialisasi']);
        }

        $sort = $filters['urut'] ?? null;

        if ($sort === null) {
            return $query->ordered()->orderBy($model->qualifyColumn('id'));
        }

        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';

        return match (ltrim($sort, '-')) {
            'nama' => $query->orderBy($model->qualifyColumn('name'), $direction)->orderBy($model->qualifyColumn('id')),
            'terbaru' => $query->orderBy($model->qualifyColumn('created_at'), $direction)->orderBy($model->qualifyColumn('id'), $direction),
            default => $query->orderBy($model->qualifyColumn('sort_order'), $direction)->orderBy($model->qualifyColumn('name'))->orderBy($model->qualifyColumn('id')),
        };
    }
}
