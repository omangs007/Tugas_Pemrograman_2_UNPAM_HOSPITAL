<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\ListFilterRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Filter daftar album galeri (F4A-R14): `q` pada `title`, `status` terbit/draf, `urut` urutan/judul.
 * Parameter yang tidak valid diabaikan oleh basis (12 V-45), tidak pernah menjadi galat 500.
 */
class GalleryIndexRequest extends ListFilterRequest
{
    /**
     * @return list<string>
     */
    protected function statusValues(): array
    {
        return ['terbit', 'draf'];
    }

    /**
     * @return list<string>
     */
    protected function sortable(): array
    {
        return ['urutan', 'judul'];
    }

    /**
     * Menerapkan pencarian, status, dan urutan. Tanpa `urut` dipakai scope ordered() (sort_order lalu id).
     * Karakter `%`, `_`, dan `\` pada kata kunci di-escape.
     *
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public function applyTo(Builder $query): Builder
    {
        $filters = $this->filters();
        $model = $query->getModel();

        if (isset($filters['q'])) {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $filters['q']).'%';
            $query->where($model->qualifyColumn('title'), 'like', $like);
        }

        $status = $filters['status'] ?? null;

        if ($status === 'terbit' || $status === 'draf') {
            $query->where($model->qualifyColumn('is_published'), $status === 'terbit');
        }

        $sort = $filters['urut'] ?? null;

        if ($sort === null) {
            return $query->ordered();
        }

        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';

        if (ltrim($sort, '-') === 'judul') {
            return $query->orderBy($model->qualifyColumn('title'), $direction)->orderBy($model->qualifyColumn('id'));
        }

        return $query->orderBy($model->qualifyColumn('sort_order'), $direction)->orderBy($model->qualifyColumn('id'));
    }
}
