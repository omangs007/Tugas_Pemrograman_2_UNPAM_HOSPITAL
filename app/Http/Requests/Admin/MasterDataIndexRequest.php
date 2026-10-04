<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\ListFilterRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Filter daftar spesialisasi dan poliklinik (F2-R25): `q`, `status` aktif/nonaktif, `urut` nama/urutan.
 * Parameter yang tidak valid diabaikan oleh basis (12 V-45), tidak pernah menjadi galat 500.
 */
class MasterDataIndexRequest extends ListFilterRequest
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
        return ['nama', 'urutan'];
    }

    /**
     * Menerapkan pencarian, status, dan urutan ke kueri. Tanpa `urut` dipakai scope ordered()
     * (sort_order lalu name). Karakter `%`, `_`, dan `\` pada kata kunci di-escape.
     *
     * @param  Builder<Model>  $query
     * @param  list<string>  $searchColumns
     * @return Builder<Model>
     */
    public function applyTo(Builder $query, array $searchColumns = ['name']): Builder
    {
        $filters = $this->filters();
        $model = $query->getModel();

        if (isset($filters['q'])) {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $filters['q']).'%';

            $query->where(function (Builder $inner) use ($searchColumns, $like, $model): void {
                foreach ($searchColumns as $column) {
                    $inner->orWhere($model->qualifyColumn($column), 'like', $like);
                }
            });
        }

        $status = $filters['status'] ?? null;

        if ($status === 'aktif' || $status === 'nonaktif') {
            $query->where($model->qualifyColumn('is_active'), $status === 'aktif');
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
            ->orderBy($model->qualifyColumn('name'))
            ->orderBy($model->qualifyColumn('id'));
    }
}
