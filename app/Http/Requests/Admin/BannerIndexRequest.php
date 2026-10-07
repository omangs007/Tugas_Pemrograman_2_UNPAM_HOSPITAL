<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\ListFilterRequest;
use App\Support\LikePattern;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Filter daftar banner (F4A-R14): `q` pada `title`, `status` aktif/nonaktif, `urut` urutan/judul (awalan "-" = menurun).
 * Parameter yang tidak valid diabaikan oleh basis (12 V-45), tidak pernah menjadi galat 500.
 */
class BannerIndexRequest extends ListFilterRequest
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
        return ['urutan', 'judul'];
    }

    /**
     * Tanpa `urut` dipakai scope ordered() (sort_order lalu id). Karakter `%`, `_`, dan `\` pada kata kunci di-escape.
     *
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public function applyTo(Builder $query): Builder
    {
        $filters = $this->filters();
        $model = $query->getModel();

        if (isset($filters['q'])) {
            $like = LikePattern::contains($filters['q']);
            $query->where($model->qualifyColumn('title'), 'like', $like);
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

        if (ltrim($sort, '-') === 'judul') {
            return $query->orderBy($model->qualifyColumn('title'), $direction)->orderBy($model->qualifyColumn('id'));
        }

        return $query
            ->orderBy($model->qualifyColumn('sort_order'), $direction)
            ->orderBy($model->qualifyColumn('id'), $direction);
    }
}
