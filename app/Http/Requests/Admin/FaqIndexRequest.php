<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\ListFilterRequest;
use App\Models\Faq;
use App\Support\LikePattern;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * Filter daftar FAQ (F4A-R14): `q` pada `question`, `status` terbit/draf, `kategori` salah satu `Faq::CATEGORIES`,
 * `urut` urutan/pertanyaan. Parameter yang tidak valid diabaikan oleh basis (12 V-45), tidak pernah galat 500.
 */
class FaqIndexRequest extends ListFilterRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'kategori' => ['nullable', 'string', Rule::in(Faq::CATEGORIES)],
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'kategori.in' => 'Pilihan filter tidak valid.',
        ]);
    }

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
        return ['urutan', 'pertanyaan'];
    }

    /**
     * Menerapkan pencarian, kategori, status, dan urutan. Tanpa `urut` dipakai scope ordered() (sort_order lalu id).
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
            $like = LikePattern::contains($filters['q']);
            $query->where($model->qualifyColumn('question'), 'like', $like);
        }

        if (isset($filters['kategori']) && in_array($filters['kategori'], Faq::CATEGORIES, true)) {
            $query->where($model->qualifyColumn('category'), $filters['kategori']);
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

        if (ltrim($sort, '-') === 'pertanyaan') {
            return $query->orderBy($model->qualifyColumn('question'), $direction)->orderBy($model->qualifyColumn('id'));
        }

        return $query->orderBy($model->qualifyColumn('sort_order'), $direction)->orderBy($model->qualifyColumn('id'));
    }
}
