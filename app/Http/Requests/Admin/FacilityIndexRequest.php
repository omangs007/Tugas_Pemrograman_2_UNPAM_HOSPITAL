<?php

namespace App\Http\Requests\Admin;

use App\Enums\FacilityCategory;
use App\Http\Requests\ListFilterRequest;
use App\Support\LikePattern;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * Filter daftar fasilitas (F4B-R22): `q` pada `name`, `status` aktif/nonaktif, `kategori` salah satu FacilityCategory,
 * dan `urut` urutan/nama (awalan "-" = menurun). Parameter yang tidak valid diabaikan oleh basis (12 V-45).
 */
class FacilityIndexRequest extends ListFilterRequest
{
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
        return ['urutan', 'nama'];
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'kategori' => ['nullable', Rule::in(FacilityCategory::values())],
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'kategori.in' => 'Kategori tidak valid.',
        ]);
    }

    /**
     * Tanpa `urut` dipakai scope ordered() (sort_order, name, id) lalu id. Karakter `%`, `_`, dan `\` pada kata kunci
     * di-escape oleh LikePattern.
     *
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public function applyTo(Builder $query): Builder
    {
        $filters = $this->filters();
        $model = $query->getModel();

        if (isset($filters['q'])) {
            $query->where($model->qualifyColumn('name'), 'like', LikePattern::contains($filters['q']));
        }

        $status = $filters['status'] ?? null;

        if ($status === 'aktif' || $status === 'nonaktif') {
            $query->where($model->qualifyColumn('is_active'), $status === 'aktif');
        }

        $category = isset($filters['kategori']) ? FacilityCategory::tryFrom($filters['kategori']) : null;

        if ($category !== null) {
            $query->where($model->qualifyColumn('category'), $category->value);
        }

        $sort = $filters['urut'] ?? null;

        if ($sort === null) {
            return $query->ordered()->orderBy($model->qualifyColumn('id'));
        }

        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';

        if (ltrim($sort, '-') === 'nama') {
            return $query->orderBy($model->qualifyColumn('name'), $direction)->orderBy($model->qualifyColumn('id'));
        }

        return $query
            ->orderBy($model->qualifyColumn('sort_order'), $direction)
            ->orderBy($model->qualifyColumn('id'), $direction);
    }
}
