<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\ListFilterRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * Filter daftar artikel admin (F4A-R14): `q` pada `title`, `status` draf/terbit, `kategori` (slug yang ada),
 * `urut` terbaru/judul (awalan "-" = kebalikan). Parameter yang tidak valid diabaikan oleh basis (12 V-45).
 */
class ArticleIndexRequest extends ListFilterRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'kategori' => ['nullable', 'string', 'max:150', Rule::exists('article_categories', 'slug')],
        ]);
    }

    /**
     * `trim()` PHP membuang NUL, sehingga nilai seperti "\0" dianggap kosong oleh validator dan aturan `exists`
     * dilewati. Nilai semacam itu dibuang di sini agar tidak lolos sebagai filter kategori.
     *
     * @return array<string, string>
     */
    public function filters(): array
    {
        $filters = parent::filters();

        if (isset($filters['kategori']) && trim($filters['kategori']) === '') {
            unset($filters['kategori']);
        }

        return $filters;
    }

    /**
     * @return list<string>
     */
    protected function statusValues(): array
    {
        return ['draf', 'terbit'];
    }

    /**
     * @return list<string>
     */
    protected function sortable(): array
    {
        return ['terbaru', 'judul'];
    }

    /**
     * Tanpa `urut` dipakai scope newestFirst() (published_at menurun, lalu id menurun). Karakter `%`, `_`, dan `\`
     * pada kata kunci di-escape.
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

        if ($status === 'draf' || $status === 'terbit') {
            $query->where($model->qualifyColumn('status'), $status === 'draf' ? 'draft' : 'published');
        }

        if (isset($filters['kategori'])) {
            $query->inCategory($filters['kategori']);
        }

        $sort = $filters['urut'] ?? null;

        if ($sort === null || $sort === 'terbaru') {
            return $query->newestFirst();
        }

        if ($sort === '-terbaru') {
            return $query->orderBy($model->qualifyColumn('published_at'))->orderBy($model->qualifyColumn('id'));
        }

        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';

        return $query->orderBy($model->qualifyColumn('title'), $direction)->orderBy($model->qualifyColumn('id'));
    }
}
